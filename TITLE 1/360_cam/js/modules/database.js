/**
 * IndexedDB Database Module - Persistent Storage for Photosphere Data
 * 
 * This module manages all persistent storage for the VFTCam app using IndexedDB,
 * with optional OPFS (Origin Private File System) integration for better persistence.
 * Provides a reliable way to store captured images and completed panoramas locally
 * on the user's device. No data is sent to external servers.
 * 
 * ARCHITECTURE:
 * The database uses IndexedDB, a low-level API for client-side storage of significant
 * amounts of structured data, including files/blobs. It provides transaction-based
 * storage with indexes for efficient querying.
 * 
 * DATABASE STRUCTURE:
 * - Database Name: 'PhotosphereDB'
 * - Version: 4 (upgraded to use blob storage)
 * - Object Stores:
 *   1. 'captures': Stores individual captured images during a session
 *   2. 'panoramas': Stores completed stitched photospheres
 * 
 * CAPTURES STORE:
 * Stores temporary images captured during a photosphere session.
 * Schema:
 * {
 *   hotspotId: number,      // Primary key (1-36), identifies capture position
 *   imageBlob: Blob,        // AVIF/WebP blob of captured image
 *   yaw: number,            // Horizontal angle in degrees (0-360)
 *   pitch: number,          // Vertical angle in degrees (-90 to 90)
 *   actualYaw: number,      // Actual device yaw when captured (may differ from ideal)
 *   actualPitch: number,    // Actual device pitch when captured
 *   roll: number,           // Device roll/tilt angle
 *   fov: number,            // Camera field of view in degrees (typically 67)
 *   timestamp: string       // ISO timestamp of capture
 * }
 * 
 * PANORAMAS STORE:
 * Stores completed photospheres that have been stitched from captured images.
 * Schema:
 * {
 *   id: number,             // Auto-incremented primary key
 *   imageBlob: Blob,        // JPEG blob of panorama (4096×2048)
 *   timestamp: string,      // ISO timestamp of creation
 *   imageCount: number,     // Number of source images used (typically 36)
 *   type: string,           // Stitching method ('equirectangular' or 'best-pixel')
 *   width: number,          // Panorama width in pixels
 *   height: number          // Panorama height in pixels
 * }
 * 
 * KEY CONCEPTS:
 * - SINGLETON PATTERN: Only one database instance exists, shared across modules
 * - BLOB STORAGE: Direct blob storage eliminates base64 overhead
 * - TRANSACTIONS: All operations use transactions for data integrity
 * - ASYNC/AWAIT: All methods are async to handle IndexedDB's asynchronous nature
 * - ERROR HANDLING: Promise-based error handling for all operations
 * - AUTO-INCREMENT: Panoramas use auto-incrementing IDs for unique keys
 * 
 * STORAGE LIFECYCLE:
 * 1. Session Start: User begins capturing, captures store is populated
 * 2. During Capture: Images saved as blobs with hotspot metadata
 * 3. Stitching: Blobs are read, decoded via createImageBitmap
 * 4. Save Panorama: Completed panorama saved as JPEG blob
 * 5. Clear Session: Captures cleared, panorama persists
 * 
 * MEMORY CONSIDERATIONS:
 * - Images stored as blobs (25-40% smaller than base64)
 * - Each captured image: ~150-300KB as AVIF/WebP blob
 * - Each panorama: ~1.5-3MB as JPEG blob
 * - Browser storage limits: Typically 50% of free disk space
 * 
 * UPGRADE HANDLING:
 * The onupgradeneeded event handles database schema migrations when version changes.
 * Version 4 migrates from base64 strings to blob storage.
 * 
 * @module Database
 */
import { opfsStorage } from './opfs-storage.js';

export class Database {
    /**
     * Initialize database configuration
     * Note: Actual database connection happens in init() method
     */
    constructor() {
        this.db = null;                    // IndexedDB database instance
        this.DB_NAME = 'PhotosphereDB';    // Database name (never change this)
        this.DB_VERSION = 5;                // Schema version (added stitch_jobs store)
        this.STORE_NAME = 'captures';       // Primary store for captured images
        this.useOPFS = false;               // Whether to use OPFS for panoramas
        this.opfs = opfsStorage;            // OPFS storage instance
    }

    /**
     * Initialize database connection and create/upgrade schema if needed
     * Must be called before any other database operations
     * @returns {Promise<void>} Resolves when database is ready
     * @throws {Error} If database cannot be opened or upgraded
     */
    async init() {
        // Check for private browsing mode
        this.isPrivateBrowsing = await this.detectPrivateBrowsing();
        if (this.isPrivateBrowsing) {
            console.warn('Private browsing detected - storage may be limited or unavailable');
        }
        
        // Try to initialize OPFS for better persistence
        try {
            this.useOPFS = await this.opfs.init();
            if (this.useOPFS) {
                console.log('OPFS storage available for panoramas via worker');
            } else {
                console.log('OPFS not available, using IndexedDB only');
            }
        } catch (error) {
            console.log('OPFS initialization failed, using IndexedDB only:', error);
            this.useOPFS = false;
        }
        
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.DB_NAME, this.DB_VERSION);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                console.log('IndexedDB initialized');
                resolve();
            };
            
            // Handle database creation and version upgrades
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Create captures store if it doesn't exist (for session images)
                if (!db.objectStoreNames.contains(this.STORE_NAME)) {
                    const store = db.createObjectStore(this.STORE_NAME, { keyPath: 'hotspotId' });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                }
                
                // Create panoramas store if it doesn't exist (for completed photospheres)
                if (!db.objectStoreNames.contains('panoramas')) {
                    const panoramaStore = db.createObjectStore('panoramas', { 
                        keyPath: 'id',           // Use 'id' as primary key
                        autoIncrement: true      // Auto-generate unique IDs
                    });
                    panoramaStore.createIndex('timestamp', 'timestamp', { unique: false });
                }
                
                // Create stitch_jobs store for tracking stitch progress (v5)
                if (!db.objectStoreNames.contains('stitch_jobs')) {
                    const stitchJobStore = db.createObjectStore('stitch_jobs', {
                        keyPath: 'id'            // Use job ID as primary key
                    });
                    stitchJobStore.createIndex('status', 'status', { unique: false });
                    stitchJobStore.createIndex('startedAt', 'startedAt', { unique: false });
                }
            };
        });
    }

    /**
     * Save a captured image to the database
     * Uses put() to insert or update if hotspotId already exists
     * @param {Object} hotspotData - Captured image data
     * @param {number} hotspotData.hotspotId - Unique position ID (1-36)
     * @param {Blob} hotspotData.imageBlob - AVIF/WebP blob of captured image
     * @param {number} hotspotData.yaw - Horizontal angle in degrees
     * @param {number} hotspotData.pitch - Vertical angle in degrees
     * @param {number} hotspotData.actualYaw - Actual device yaw when captured
     * @param {number} hotspotData.actualPitch - Actual device pitch when captured
     * @param {number} hotspotData.roll - Device roll angle
     * @param {number} hotspotData.fov - Camera field of view
     * @param {string} hotspotData.timestamp - ISO timestamp
     * @returns {Promise<void>} Resolves when image is saved
     */
    async saveImage(hotspotData) {
        // Validate blob size (max 10MB for individual captures)
        if (hotspotData.imageBlob && hotspotData.imageBlob.size) {
            const maxSizeBytes = 10 * 1024 * 1024; // 10MB
            
            if (hotspotData.imageBlob.size > maxSizeBytes) {
                const sizeMB = (hotspotData.imageBlob.size / (1024 * 1024)).toFixed(1);
                console.error(`Image too large: ${sizeMB}MB exceeds 10MB limit`);
                throw new Error(`Image too large (${sizeMB}MB). Maximum size is 10MB.`);
            }
        }
        
        const transaction = this.db.transaction([this.STORE_NAME], 'readwrite');
        const store = transaction.objectStore(this.STORE_NAME);
        
        return new Promise((resolve, reject) => {
            // put() will update if key exists, add if it doesn't
            const request = store.put(hotspotData);
            request.onsuccess = () => {
                console.log(`Saved image for hotspot ${hotspotData.hotspotId}`);
                resolve();
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Load all captured images from the current session
     * Images are sorted by hotspotId to maintain capture order
     * @returns {Promise<Array>} Array of captured image objects sorted by hotspotId
     */
    async loadCapturedImages() {
        const transaction = this.db.transaction([this.STORE_NAME], 'readonly');
        const store = transaction.objectStore(this.STORE_NAME);
        
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => {
                const images = request.result;
                // Sort by hotspotId to ensure correct stitching order (1-36)
                images.sort((a, b) => (a.hotspotId || 0) - (b.hotspotId || 0));
                console.log(`Loaded ${images.length} images from IndexedDB (sorted by hotspotId)`);
                console.log('Image order:', images.map(img => img.hotspotId));
                resolve(images);
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Clear all captured images from the current session
     * Used when starting a new capture or clearing incomplete session
     * Note: This does NOT delete saved panoramas
     * @returns {Promise<void>} Resolves when all captures are cleared
     */
    async clearAllImages() {
        const transaction = this.db.transaction([this.STORE_NAME], 'readwrite');
        const store = transaction.objectStore(this.STORE_NAME);
        
        return new Promise((resolve, reject) => {
            const request = store.clear();
            request.onsuccess = () => {
                console.log('Cleared all captured images');
                resolve();
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get a specific captured image by hotspot ID
     * @param {number} hotspotId - The hotspot ID to retrieve (1-36)
     * @returns {Promise<Object|undefined>} Image data object or undefined if not found
     */
    async getImageByHotspot(hotspotId) {
        const transaction = this.db.transaction([this.STORE_NAME], 'readonly');
        const store = transaction.objectStore(this.STORE_NAME);
        
        return new Promise((resolve, reject) => {
            const request = store.get(hotspotId);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Delete a specific captured image by hotspot ID
     * Used when user wants to recapture a specific position
     * @param {number} hotspotId - The hotspot ID to delete (1-36)
     * @returns {Promise<void>} Resolves when image is deleted
     */
    async deleteImage(hotspotId) {
        const transaction = this.db.transaction([this.STORE_NAME], 'readwrite');
        const store = transaction.objectStore(this.STORE_NAME);
        
        return new Promise((resolve, reject) => {
            const request = store.delete(hotspotId);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Save a completed panorama to persistent storage
     * Panoramas are kept even after clearing capture session
     * @param {Object} panoramaData - Stitched panorama data
     * @param {Blob} panoramaData.imageBlob - JPEG blob of panorama
     * @param {string} panoramaData.timestamp - ISO timestamp of creation
     * @param {number} panoramaData.imageCount - Number of source images used
     * @param {string} panoramaData.type - Stitching method used
     * @param {number} panoramaData.width - Panorama width in pixels
     * @param {number} panoramaData.height - Panorama height in pixels
     * @returns {Promise<number>} The auto-generated ID of the saved panorama
     */
    async savePanorama(panoramaData) {
        // Validate panorama blob size (max 50MB for stitched panoramas)
        if (panoramaData.imageBlob && panoramaData.imageBlob.size) {
            const maxSizeBytes = 50 * 1024 * 1024; // 50MB for panoramas
            
            if (panoramaData.imageBlob.size > maxSizeBytes) {
                const sizeMB = (panoramaData.imageBlob.size / (1024 * 1024)).toFixed(1);
                console.error(`Panorama too large: ${sizeMB}MB exceeds 50MB limit`);
                throw new Error(`Panorama too large (${sizeMB}MB). Maximum size is 50MB.`);
            }
        }
        
        // Generate unique ID for the panorama
        const panoramaId = `pano_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        
        // If OPFS is available, store blob there and metadata in IndexedDB
        if (this.useOPFS && panoramaData.imageBlob) {
            try {
                // Save blob to OPFS
                const metadata = await this.opfs.savePanorama(panoramaId, panoramaData.imageBlob, {
                    timestamp: panoramaData.timestamp,
                    imageCount: panoramaData.imageCount,
                    type: panoramaData.type,
                    width: panoramaData.width,
                    height: panoramaData.height
                });
                
                // Save metadata to IndexedDB (without blob)
                const metadataToStore = {
                    ...panoramaData,
                    opfsId: panoramaId,
                    imageBlob: null  // Don't store blob in IndexedDB
                };
                
                const transaction = this.db.transaction(['panoramas'], 'readwrite');
                const store = transaction.objectStore('panoramas');
                
                return new Promise((resolve, reject) => {
                    const request = store.add(metadataToStore);
                    request.onsuccess = () => {
                        console.log('Saved panorama to OPFS with metadata in IndexedDB');
                        resolve(request.result);
                    };
                    request.onerror = () => reject(request.error);
                });
            } catch (error) {
                console.error('Failed to save to OPFS, falling back to IndexedDB:', error);
                // Fall through to regular IndexedDB storage
            }
        }
        
        // Fallback to IndexedDB-only storage
        const transaction = this.db.transaction(['panoramas'], 'readwrite');
        const store = transaction.objectStore('panoramas');
        
        return new Promise((resolve, reject) => {
            // add() creates new record with auto-incremented ID
            const request = store.add(panoramaData);
            request.onsuccess = () => {
                console.log('Saved panorama to IndexedDB');
                resolve(request.result); // Returns the generated ID
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Load all saved panoramas from persistent storage
     * Uses cursor to iterate through all records and attach IDs
     * @returns {Promise<Array>} Array of panorama objects sorted by timestamp (newest first)
     */
    async loadAllPanoramas() {
        const transaction = this.db.transaction(['panoramas'], 'readonly');
        const store = transaction.objectStore('panoramas');
        
        // First, get all panoramas from IndexedDB
        const panoramas = await new Promise((resolve, reject) => {
            const request = store.openCursor();
            const results = [];
            
            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    // Attach the IndexedDB key as 'id' for later reference
                    const pano = cursor.value;
                    pano.id = cursor.key; // Store the actual IndexedDB key for deletion
                    results.push(pano);
                    cursor.continue();
                } else {
                    resolve(results);
                }
            };
            request.onerror = () => reject(request.error);
        });
        
        // Then, load blobs from OPFS for panoramas that need them
        for (const pano of panoramas) {
            if (pano.opfsId && this.useOPFS && !pano.imageBlob) {
                try {
                    const blob = await this.opfs.loadPanorama(pano.opfsId);
                    pano.imageBlob = blob;
                    // OPFS module already logs the loading
                } catch (error) {
                    console.error(`Failed to load panorama ${pano.opfsId} from OPFS:`, error);
                    // Panorama data might be missing, but we'll still show the metadata
                }
            }
        }
        
        console.log(`Loaded ${panoramas.length} panoramas from database`);
        // Sort by timestamp, newest first for display in camera roll
        panoramas.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
        return panoramas;
    }

    /**
     * Delete a saved panorama by ID
     * @param {number} id - The auto-generated ID of the panorama to delete
     * @returns {Promise<void>} Resolves when panorama is deleted
     */
    async deletePanorama(id) {
        // First, check if panorama has OPFS data
        const transaction = this.db.transaction(['panoramas'], 'readwrite');
        const store = transaction.objectStore('panoramas');
        
        // Get the panorama to check for OPFS ID
        const getRequest = store.get(id);
        
        return new Promise((resolve, reject) => {
            getRequest.onsuccess = async () => {
                const pano = getRequest.result;
                
                // Delete from OPFS if it exists there
                if (pano && pano.opfsId && this.useOPFS) {
                    try {
                        await this.opfs.deletePanorama(pano.opfsId);
                        console.log('Deleted panorama from OPFS');
                    } catch (error) {
                        console.error('Failed to delete from OPFS:', error);
                        // Continue with IndexedDB deletion even if OPFS fails
                    }
                }
                
                // Delete from IndexedDB
                const deleteRequest = store.delete(id);
                deleteRequest.onsuccess = () => {
                    console.log('Deleted panorama from IndexedDB');
                    resolve();
                };
                deleteRequest.onerror = () => reject(deleteRequest.error);
            };
            getRequest.onerror = () => reject(getRequest.error);
        });
    }
    
    /**
     * Check if storage is persistent
     */
    async isPersistent() {
        return this.opfs.isPersistent();
    }
    
    /**
     * Request persistent storage
     */
    async requestPersistence() {
        return this.opfs.requestPersistence();
    }
    
    // ============================================
    // STITCH JOB TRACKING METHODS (v5)
    // ============================================
    
    /**
     * Save a stitch job to track progress
     * @param {Object} job - Job data
     * @returns {Promise<string>} Job ID
     */
    async saveStitchJob(job) {
        if (!this.db) await this.init();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['stitch_jobs'], 'readwrite');
            const store = transaction.objectStore('stitch_jobs');
            
            // Generate UUID if not provided
            if (!job.id) {
                job.id = this.generateUUID();
            }
            
            // Set timestamps
            if (!job.startedAt) {
                job.startedAt = Date.now();
            }
            job.updatedAt = Date.now();
            
            const request = store.put(job);
            
            request.onsuccess = () => resolve(job.id);
            request.onerror = () => reject(request.error);
        });
    }
    
    /**
     * Get a stitch job by ID
     * @param {string} jobId - Job ID
     * @returns {Promise<Object|null>} Job data or null
     */
    async getStitchJob(jobId) {
        if (!this.db) await this.init();
        if (!jobId) return null;
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['stitch_jobs'], 'readonly');
            const store = transaction.objectStore('stitch_jobs');
            const request = store.get(jobId);
            
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }
    
    /**
     * Update stitch job status
     * @param {string} jobId - Job ID
     * @param {Object} updates - Fields to update
     * @returns {Promise<void>}
     */
    async updateStitchJob(jobId, updates) {
        const job = await this.getStitchJob(jobId);
        if (!job) throw new Error('Job not found');
        
        Object.assign(job, updates, { updatedAt: Date.now() });
        await this.saveStitchJob(job);
    }
    
    /**
     * Delete old stitch jobs (cleanup)
     * @param {number} maxAge - Max age in milliseconds (default 7 days)
     * @returns {Promise<number>} Number of deleted jobs
     */
    async cleanupOldStitchJobs(maxAge = 7 * 24 * 60 * 60 * 1000) {
        if (!this.db) await this.init();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['stitch_jobs'], 'readwrite');
            const store = transaction.objectStore('stitch_jobs');
            const index = store.index('startedAt');
            const cutoff = Date.now() - maxAge;
            let deleted = 0;
            
            const request = index.openCursor(IDBKeyRange.upperBound(cutoff));
            
            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    cursor.delete();
                    deleted++;
                    cursor.continue();
                } else {
                    resolve(deleted);
                }
            };
            
            request.onerror = () => reject(request.error);
        });
    }
    
    /**
     * Generate a UUID v4
     * @returns {string} UUID
     */
    generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    /**
     * Detect if running in private browsing mode
     * @returns {Promise<boolean>} True if private browsing detected
     */
    async detectPrivateBrowsing() {
        try {
            // Try to use OPFS as a test - it fails in private browsing
            if ('storage' in navigator && 'getDirectory' in navigator.storage) {
                try {
                    await navigator.storage.getDirectory();
                    return false; // OPFS worked, not private browsing
                } catch (e) {
                    // OPFS failed, might be private browsing
                    if (e.message && e.message.includes('transient')) {
                        return true;
                    }
                }
            }
            
            // Try to store a test blob in IndexedDB
            const testDB = await new Promise((resolve) => {
                const request = indexedDB.open('test-private', 1);
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => resolve(null);
                request.onupgradeneeded = (e) => {
                    e.target.result.createObjectStore('test');
                };
            });
            
            if (testDB) {
                try {
                    const transaction = testDB.transaction(['test'], 'readwrite');
                    const store = transaction.objectStore('test');
                    const testBlob = new Blob(['test']);
                    await new Promise((resolve, reject) => {
                        const request = store.put(testBlob, 'test');
                        request.onsuccess = resolve;
                        request.onerror = reject;
                    });
                    // Clean up
                    testDB.close();
                    indexedDB.deleteDatabase('test-private');
                    return false; // Blob storage worked
                } catch (e) {
                    testDB.close();
                    indexedDB.deleteDatabase('test-private');
                    return true; // Blob storage failed, likely private browsing
                }
            }
            
            return false;
        } catch (error) {
            console.warn('Error detecting private browsing:', error);
            return false;
        }
    }
}