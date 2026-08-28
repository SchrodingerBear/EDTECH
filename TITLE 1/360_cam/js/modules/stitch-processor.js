/**
 * Resilient Stitch Processor with Worker Management
 * Handles worker creation, retry logic, fallback processing, and error recovery
 */

export class StitchProcessor {
    constructor(app) {
        this.app = app;
        this.worker = null;
        this.maxWorkerRetries = 2;
        this.currentAttempt = 0;
        this.isProcessing = false;
        this.isCancelled = false;
        this.fallbackToMainThread = false;
        
        // Callbacks for UI updates
        this.onProgress = null;
        this.onStatus = null;
        this.onError = null;
        
        // Performance metrics
        this.startTime = null;
        this.metrics = {
            workerAttempts: 0,
            mainThreadFallback: false,
            emergencyMode: false,
            totalTime: 0
        };
    }
    
    /**
     * Main entry point - processes images with automatic retry and fallback
     */
    async process(captures) {
        if (this.isProcessing) {
            throw new Error('Already processing');
        }
        
        this.isProcessing = true;
        this.isCancelled = false;
        this.startTime = Date.now();
        this.metrics = { workerAttempts: 0, mainThreadFallback: false, emergencyMode: false };
        
        try {
            // Check memory before starting
            if (!this.checkMemoryAvailable(captures)) {
                const useReduced = await this.app.cardUI.confirm(
                    'Limited memory detected. Process with reduced quality?',
                    'Memory Warning'
                );
                if (!useReduced) {
                    throw new Error('Processing cancelled due to memory constraints');
                }
                // Will use emergency mode
                this.metrics.emergencyMode = true;
            }
            
            let result = null;
            
            // Try worker processing with retries
            if (!this.fallbackToMainThread && !this.metrics.emergencyMode) {
                for (let attempt = 0; attempt <= this.maxWorkerRetries; attempt++) {
                    try {
                        this.currentAttempt = attempt;
                        this.metrics.workerAttempts++;
                        
                        if (attempt > 0) {
                            this.updateStatus(`Retrying... (Attempt ${attempt + 1}/${this.maxWorkerRetries + 1})`);
                            await this.delay(1000 * attempt); // Progressive backoff
                        }
                        
                        result = await this.processWithWorker(captures);
                        break; // Success!
                        
                    } catch (error) {
                        console.warn(`Worker attempt ${attempt + 1} failed:`, error);
                        this.terminateWorker();
                        
                        if (attempt === this.maxWorkerRetries) {
                            // All worker attempts failed
                            this.updateStatus('Switching to alternative processing method...');
                            this.metrics.mainThreadFallback = true;
                        }
                    }
                }
            }
            
            // Fallback to main thread if worker failed
            if (!result && !this.isCancelled) {
                if (this.metrics.emergencyMode) {
                    result = await this.processEmergencyMode(captures);
                } else {
                    result = await this.processOnMainThread(captures);
                }
            }
            
            if (this.isCancelled) {
                throw new Error('Processing cancelled by user');
            }
            
            if (!result || result.length === 0) {
                throw new Error('No images could be processed');
            }
            
            // Record metrics
            this.metrics.totalTime = Date.now() - this.startTime;
            console.log('Processing complete:', this.metrics);
            
            return result;
            
        } finally {
            this.isProcessing = false;
            this.terminateWorker();
        }
    }
    
    /**
     * Process images using web worker
     */
    async processWithWorker(captures) {
        return new Promise((resolve, reject) => {
            // Create fresh worker
            this.worker = new Worker('/js/workers/stitch-worker.js', { type: 'module' });
            
            let timeoutId;
            let hasCompleted = false;
            
            const cleanup = () => {
                clearTimeout(timeoutId);
                this.terminateWorker();
                hasCompleted = true;
            };
            
            // Set initial timeout
            timeoutId = setTimeout(() => {
                if (!hasCompleted) {
                    cleanup();
                    reject(new Error('Worker timeout - no response'));
                }
            }, 30000);
            
            // Handle worker messages
            this.worker.onmessage = (e) => {
                const { type, data } = e.data;
                
                switch (type) {
                    case 'READY':
                        console.log('Worker ready');
                        break;
                        
                    case 'STARTED':
                        this.updateStatus('Processing images...');
                        break;
                        
                    case 'PROGRESS':
                        // Reset timeout on progress
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => {
                            if (!hasCompleted) {
                                cleanup();
                                reject(new Error('Worker stalled'));
                            }
                        }, 30000);
                        
                        if (this.onProgress) {
                            this.onProgress(e.data.percent, e.data.message);
                        }
                        break;
                        
                    case 'WARNING':
                        console.warn('Worker warning:', e.data.message);
                        break;
                        
                    case 'HEARTBEAT':
                        // Worker is still alive
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => {
                            if (!hasCompleted) {
                                cleanup();
                                reject(new Error('Worker stopped responding'));
                            }
                        }, 30000);
                        break;
                        
                    case 'COMPLETE':
                        cleanup();
                        resolve(e.data.layers);
                        break;
                        
                    case 'ERROR':
                        cleanup();
                        reject(new Error(e.data.message));
                        break;
                        
                    case 'CANCELLED':
                        cleanup();
                        reject(new Error('Processing cancelled'));
                        break;
                }
            };
            
            this.worker.onerror = (error) => {
                if (!hasCompleted) {
                    cleanup();
                    reject(new Error(`Worker error: ${error.message}`));
                }
            };
            
            this.worker.onmessageerror = (error) => {
                if (!hasCompleted) {
                    cleanup();
                    reject(new Error('Worker message error'));
                }
            };
            
            // Get target dimensions from capture resolution
            const targetWidth = this.app.CAPTURE_RESOLUTION.width;
            const targetHeight = this.app.CAPTURE_RESOLUTION.height;
            
            // Start processing
            this.worker.postMessage({
                type: 'PROCESS',
                data: {
                    captures: captures,
                    targetWidth: targetWidth,
                    targetHeight: targetHeight
                }
            });
        });
    }
    
    /**
     * Fallback: Process on main thread with chunking
     */
    async processOnMainThread(captures) {
        console.log('Processing on main thread with chunking');
        this.updateStatus('Using alternative processing method...');
        
        const CHUNK_SIZE = 3;
        const layers = [];
        
        for (let i = 0; i < captures.length; i += CHUNK_SIZE) {
            if (this.isCancelled) {
                throw new Error('Processing cancelled');
            }
            
            const chunk = captures.slice(i, i + CHUNK_SIZE);
            
            for (const capture of chunk) {
                try {
                    const layer = await this.processImageOnMainThread(capture);
                    layers.push(layer);
                    
                    if (this.onProgress) {
                        const percent = (layers.length / captures.length) * 100;
                        this.onProgress(percent, `Processing ${layers.length}/${captures.length}`);
                    }
                } catch (error) {
                    console.warn(`Skipping image ${capture.hotspotId}:`, error);
                }
            }
            
            // Yield to browser
            await this.delay(50);
        }
        
        if (layers.length < 12) {
            throw new Error(`Only ${layers.length} images processed. Need at least 12.`);
        }
        
        return layers;
    }
    
    /**
     * Process single image on main thread
     */
    async processImageOnMainThread(capture) {
        const targetWidth = this.app.CAPTURE_RESOLUTION.width;
        const targetHeight = this.app.CAPTURE_RESOLUTION.height;
        
        let imageBitmap = null;
        
        try {
            // Decode image with timeout - handle both blob and imageBlob fields
            let imagePromise;
            if (capture.blob || capture.imageBlob) {
                imagePromise = createImageBitmap(capture.blob || capture.imageBlob);
            } else if (capture.imageData) {
                imagePromise = this.decodeBase64Image(capture.imageData);
            } else {
                throw new Error('No image data found in capture');
            }
                
            imageBitmap = await Promise.race([
                imagePromise,
                this.timeout(5000, 'Image decode timeout')
            ]);
            
            // Create canvas and draw
            const canvas = document.createElement('canvas');
            canvas.width = targetWidth;
            canvas.height = targetHeight;
            const ctx = canvas.getContext('2d');
            
            // Calculate scaling
            const scale = Math.min(
                targetWidth / imageBitmap.width,
                targetHeight / imageBitmap.height
            );
            
            const scaledWidth = imageBitmap.width * scale;
            const scaledHeight = imageBitmap.height * scale;
            const x = (targetWidth - scaledWidth) / 2;
            const y = (targetHeight - scaledHeight) / 2;
            
            ctx.clearRect(0, 0, targetWidth, targetHeight);
            ctx.drawImage(imageBitmap, x, y, scaledWidth, scaledHeight);
            
            // Clean up source
            imageBitmap.close();
            
            // Return layer data
            return {
                canvas: canvas,
                hotspotId: capture.hotspotId,
                yaw: ((capture.yaw % 360) + 360) % 360,
                pitch: capture.actualPitch !== undefined ? capture.actualPitch : capture.pitch,
                roll: capture.roll || 0
            };
            
        } catch (error) {
            if (imageBitmap) imageBitmap.close();
            throw error;
        }
    }
    
    /**
     * Emergency mode - reduced quality for stability
     */
    async processEmergencyMode(captures) {
        console.log('Emergency processing mode activated');
        this.updateStatus('Processing in reduced quality mode...');
        
        await this.app.cardUI.alert(
            'Using reduced quality mode for stability. The panorama will have lower resolution.',
            'Emergency Mode'
        );
        
        const layers = [];
        
        // Process ALL captures at reduced resolution
        for (let i = 0; i < captures.length; i++) {
            if (this.isCancelled) break;
            
            const capture = captures[i];
            
            try {
                // Handle both blob and imageBlob fields
                let imageSource;
                if (capture.blob || capture.imageBlob) {
                    imageSource = capture.blob || capture.imageBlob;
                } else if (capture.imageData) {
                    imageSource = await this.base64ToBlob(capture.imageData);
                } else {
                    throw new Error('No image data found');
                }
                
                // Process at reduced resolution
                const bitmap = await createImageBitmap(imageSource, {
                    resizeWidth: 540,
                    resizeHeight: 960,
                    resizeQuality: 'low'
                });
                
                // Create smaller canvas
                const canvas = document.createElement('canvas');
                canvas.width = 540;
                canvas.height = 960;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(bitmap, 0, 0);
                bitmap.close();
                
                layers.push({
                    canvas: canvas,
                    hotspotId: capture.hotspotId,
                    yaw: ((capture.yaw % 360) + 360) % 360,
                    pitch: capture.actualPitch !== undefined ? capture.actualPitch : capture.pitch,
                    roll: capture.roll || 0
                });
                
                if (this.onProgress) {
                    const percent = ((i + 1) / captures.length) * 100;
                    this.onProgress(percent, `Emergency mode: ${i + 1}/${captures.length}`);
                }
                
                // Extra yielding every 3 images
                if (i % 3 === 0) {
                    await this.delay(100);
                }
                
            } catch (error) {
                console.error(`Emergency mode - failed to process image ${capture.hotspotId}:`, error);
                // In emergency mode, we still need all images, so this is critical
                throw new Error(`Failed to process image ${capture.hotspotId} in emergency mode: ${error.message}`);
            }
        }
        
        if (layers.length !== captures.length) {
            throw new Error(`Emergency mode: Only processed ${layers.length}/${captures.length} images. All images are required for panorama.`);
        }
        
        return layers;
    }
    
    /**
     * Cancel current processing
     */
    cancel() {
        this.isCancelled = true;
        if (this.worker) {
            this.worker.postMessage({ type: 'CANCEL' });
        }
    }
    
    /**
     * Terminate worker
     */
    terminateWorker() {
        if (this.worker) {
            this.worker.terminate();
            this.worker = null;
        }
    }
    
    /**
     * Check available memory
     */
    checkMemoryAvailable(captures) {
        if (!performance.memory) return true;
        
        const estimatedSize = captures.length * 5 * 1024 * 1024; // 5MB per image
        const available = performance.memory.jsHeapSizeLimit - performance.memory.usedJSHeapSize;
        
        return available > estimatedSize * 1.5; // 50% safety margin
    }
    
    /**
     * Update status callback
     */
    updateStatus(message) {
        console.log('Status:', message);
        if (this.onStatus) {
            this.onStatus(message);
        }
    }
    
    /**
     * Utility functions
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    timeout(ms, message = 'Operation timeout') {
        return new Promise((_, reject) => {
            setTimeout(() => reject(new Error(message)), ms);
        });
    }
    
    async decodeBase64Image(base64Data) {
        const img = new Image();
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
            img.src = base64Data;
        });
        return await createImageBitmap(img);
    }
    
    async base64ToBlob(base64Data) {
        const base64 = base64Data.replace(/^data:image\/\w+;base64,/, '');
        const binaryString = atob(base64);
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return new Blob([bytes], { type: 'image/jpeg' });
    }
}