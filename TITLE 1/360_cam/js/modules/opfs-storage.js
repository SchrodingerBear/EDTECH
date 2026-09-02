/**
 * OPFS (Origin Private File System) Storage Module
 * 
 * Provides persistent storage for panorama images using the File System API.
 * Uses a Web Worker to ensure compatibility with Safari/iOS which only supports
 * createSyncAccessHandle in workers.
 * 
 * Benefits over IndexedDB:
 * - Better persistence guarantees (not subject to browser storage eviction)
 * - More efficient for large binary data
 * - Protected from Safari's 7-day ITP deletion policy
 */

class OPFSStorage {
    constructor() {
        this.worker = null;
        this.messageId = 0;
        this.pendingMessages = new Map();
        this.initialized = false;
        this.isSupported = OPFSStorage.isSupported();
    }
    
    /**
     * Check if OPFS is supported in the current browser
     */
    static isSupported() {
        return 'storage' in navigator && 
               'getDirectory' in navigator.storage;
    }
    
    /**
     * Initialize OPFS storage with worker
     */
    async init() {
        if (!this.isSupported) {
            console.log('OPFS not supported in this browser, will use IndexedDB fallback');
            return false;
        }
        
        if (this.initialized) {
            return true;
        }
        
        try {
            // Create and initialize worker - use relative path
            // From js/modules/ to js/workers/ is ../../workers/
            const workerPath = './opfs-worker.js';
            this.worker = new Worker(workerPath);
            
            // Set up message handler
            this.worker.addEventListener('message', (event) => {
                const { id, success, result, error } = event.data;
                const pending = this.pendingMessages.get(id);
                
                if (pending) {
                    this.pendingMessages.delete(id);
                    if (success) {
                        pending.resolve(result);
                    } else {
                        pending.reject(new Error(error));
                    }
                }
            });
            
            // Initialize OPFS in worker with timeout
            const timeoutPromise = new Promise((_, reject) => 
                setTimeout(() => reject(new Error('Worker operation timed out')), 5000)
            );
            
            const initialized = await Promise.race([
                this.sendMessage('init'),
                timeoutPromise
            ]);
            
            this.initialized = initialized;
            
            if (initialized) {
                console.log('OPFS storage initialized with worker (Safari-compatible)');
            } else {
                console.log('OPFS worker initialization failed, will use IndexedDB fallback');
            }
            
            return initialized;
        } catch (error) {
            console.error('Failed to initialize OPFS worker:', error);
            console.log('Will use IndexedDB fallback for storage');
            return false;
        }
    }
    
    /**
     * Send message to worker and wait for response
     */
    sendMessage(action, payload = {}, transferables = []) {
        return new Promise((resolve, reject) => {
            const id = ++this.messageId;
            
            this.pendingMessages.set(id, { resolve, reject });
            
            // Post message with optional transferables
            if (transferables.length > 0) {
                this.worker.postMessage({
                    id,
                    action,
                    payload
                }, transferables);
            } else {
                this.worker.postMessage({
                    id,
                    action,
                    payload
                });
            }
            
            // Timeout after 30 seconds
            setTimeout(() => {
                if (this.pendingMessages.has(id)) {
                    this.pendingMessages.delete(id);
                    reject(new Error('Worker operation timed out'));
                }
            }, 30000);
        });
    }
    
    /**
     * Save a panorama image to OPFS
     */
    async savePanorama(id, blob, metadata = {}) {
        if (!this.initialized) {
            await this.init();
        }
        
        if (!this.initialized) {
            throw new Error('OPFS not available');
        }
        
        try {
            const fileName = `${id}.jpg`;
            
            // Convert blob to ArrayBuffer for transfer
            const arrayBuffer = await blob.arrayBuffer();
            
            // Save via worker
            await this.sendMessage('save', {
                fileName,
                data: arrayBuffer
            }, [arrayBuffer]); // Transfer ownership for performance
            
            console.log(`Saved panorama ${id} to OPFS (${(blob.size / 1024).toFixed(1)}KB)`);
            
            // Return metadata with OPFS reference
            return {
                ...metadata,
                id,
                opfsPath: fileName,
                size: blob.size,
                type: blob.type
            };
        } catch (error) {
            console.error('Failed to save panorama to OPFS:', error);
            throw error;
        }
    }
    
    /**
     * Load a panorama image from OPFS
     */
    async loadPanorama(id) {
        if (!this.initialized) {
            await this.init();
        }
        
        if (!this.initialized) {
            return null;
        }
        
        try {
            const fileName = `${id}.jpg`;
            const arrayBuffer = await this.sendMessage('load', { fileName });
            
            if (!arrayBuffer) {
                return null;
            }
            
            // Convert ArrayBuffer back to Blob
            const blob = new Blob([arrayBuffer], { type: 'image/jpeg' });
            console.log(`Loaded panorama ${id} from OPFS (${(blob.size / 1024).toFixed(1)}KB)`);
            
            return blob;
        } catch (error) {
            console.error(`Failed to load panorama ${id}:`, error);
            return null;
        }
    }
    
    /**
     * Delete a panorama from OPFS
     */
    async deletePanorama(id) {
        if (!this.initialized) {
            await this.init();
        }
        
        if (!this.initialized) {
            return false;
        }
        
        try {
            const fileName = `${id}.jpg`;
            const deleted = await this.sendMessage('delete', { fileName });
            
            if (deleted) {
                console.log(`Deleted panorama ${id} from OPFS`);
            }
            
            return deleted;
        } catch (error) {
            console.error(`Failed to delete panorama ${id}:`, error);
            return false;
        }
    }
    
    /**
     * List all panoramas in OPFS
     */
    async listPanoramas() {
        if (!this.initialized) {
            await this.init();
        }
        
        if (!this.initialized) {
            return [];
        }
        
        try {
            const files = await this.sendMessage('list');
            return files.filter(file => file.name.endsWith('.jpg'));
        } catch (error) {
            console.error('Failed to list panoramas:', error);
            return [];
        }
    }
    
    /**
     * Get storage usage information
     */
    async getStorageInfo() {
        if (!this.initialized) {
            await this.init();
        }
        
        if (!this.initialized) {
            return { usage: 0, quota: 0 };
        }
        
        try {
            return await this.sendMessage('storage');
        } catch (error) {
            console.error('Failed to get storage info:', error);
            return { usage: 0, quota: 0 };
        }
    }
    
    /**
     * Clear all panoramas from OPFS
     */
    async clearAll() {
        if (!this.initialized) {
            await this.init();
        }
        
        if (!this.initialized) {
            return 0;
        }
        
        try {
            const files = await this.listPanoramas();
            let deletedCount = 0;
            
            for (const file of files) {
                const id = file.name.replace('.jpg', '');
                if (await this.deletePanorama(id)) {
                    deletedCount++;
                }
            }
            
            console.log(`Cleared ${deletedCount} panoramas from OPFS`);
            return deletedCount;
        } catch (error) {
            console.error('Failed to clear OPFS:', error);
            return 0;
        }
    }
}

// Export singleton instance
export const opfsStorage = new OPFSStorage();