// Worker Client - Manages communication with Web Worker for OpenCV processing
export class WorkerClient {
    constructor(workerPath) {
        this.workerPath = workerPath;
        this.worker = null;
        this.pendingCallbacks = new Map();
        this.messageId = 0;
        this.isReady = false;
        this.listeners = new Map();
        
        // Progress tracking
        this.currentProgress = 0;
        this.progressCallback = null;
        
        // Initialize worker
        this.init();
    }

    init() {
        try {
            this.worker = new Worker(this.workerPath);
            this.setupEventHandlers();
        } catch (error) {
            console.error('Failed to create Web Worker:', error);
            throw new Error('Web Worker initialization failed. Check browser compatibility.');
        }
    }

    setupEventHandlers() {
        this.worker.onmessage = (e) => this.handleMessage(e);
        this.worker.onerror = (e) => this.handleError(e);
    }

    handleMessage(e) {
        const { id, msg, result, error, type, payload } = e.data;
        
        // Handle different message types
        switch (type) {
            case 'console':
                // Forward console messages from worker
                console.log('[Worker]:', ...payload);
                break;
                
            case 'progress':
                // Handle progress updates
                this.handleProgress(payload);
                break;
                
            case 'ready':
                // Worker is ready
                this.isReady = true;
                this.emit('ready');
                break;
                
            case 'memory':
                // Memory usage update
                this.emit('memory', payload);
                break;
                
            default:
                // Handle response to request
                if (id && this.pendingCallbacks.has(id)) {
                    const { resolve, reject } = this.pendingCallbacks.get(id);
                    this.pendingCallbacks.delete(id);
                    
                    if (error) {
                        reject(new Error(error));
                    } else {
                        resolve(result);
                    }
                }
        }
    }

    handleError(error) {
        console.error('Worker error:', error);
        this.emit('error', error);
        
        // Reject all pending callbacks
        for (const [id, { reject }] of this.pendingCallbacks) {
            reject(new Error('Worker crashed'));
        }
        this.pendingCallbacks.clear();
    }

    handleProgress(data) {
        const { current, total, message } = data;
        this.currentProgress = (current / total) * 100;
        
        if (this.progressCallback) {
            this.progressCallback({
                percentage: this.currentProgress,
                current,
                total,
                message
            });
        }
        
        this.emit('progress', {
            percentage: this.currentProgress,
            current,
            total,
            message
        });
    }

    // Send message and wait for response
    async postMessage(msg, payload, transferables = []) {
        return new Promise((resolve, reject) => {
            const id = ++this.messageId;
            const timeout = setTimeout(() => {
                this.pendingCallbacks.delete(id);
                reject(new Error(`Worker timeout for message: ${msg}`));
            }, 300000); // 5 minute timeout
            
            this.pendingCallbacks.set(id, { 
                resolve: (result) => {
                    clearTimeout(timeout);
                    resolve(result);
                },
                reject: (error) => {
                    clearTimeout(timeout);
                    reject(error);
                }
            });
            
            try {
                if (transferables.length > 0) {
                    this.worker.postMessage({ id, msg, payload }, transferables);
                } else {
                    this.worker.postMessage({ id, msg, payload });
                }
            } catch (error) {
                this.pendingCallbacks.delete(id);
                clearTimeout(timeout);
                reject(error);
            }
        });
    }

    // Load OpenCV library
    async load(opencvPath) {
        const result = await this.postMessage('load', { 
            opencvPath: opencvPath || 'opencv_3_4_custom_O3.js' 
        });
        
        if (result.success) {
            this.isReady = true;
            this.emit('opencvLoaded');
        }
        
        return result;
    }

    // Initialize multistitcher with images
    async multiStitchInit(images) {
        if (!this.isReady) {
            throw new Error('OpenCV not loaded. Call load() first.');
        }
        
        // Just send ImageData objects directly - OpenCV.js can handle them
        // No need to transfer buffers, we'll let the structured clone handle it
        return this.postMessage('multiStitchInit', { 
            images: images 
        });
    }

    // Start stitching process
    async multiStitchStart(fieldsOfView, settings) {
        if (!this.isReady) {
            throw new Error('OpenCV not loaded');
        }
        
        const result = await this.postMessage('multiStitchStart', { 
            fieldsOfView, 
            settings 
        });
        
        // Convert result back to ImageData if present
        if (result.imageData) {
            result.imageData = this.bufferToImageData(result.imageData);
        }
        
        return result;
    }

    // Continue stitching next image
    async multiStitchNext() {
        if (!this.isReady) {
            throw new Error('OpenCV not loaded');
        }
        
        const result = await this.postMessage('multiStitchNext', {});
        
        // Convert results back to ImageData
        if (result.imageData) {
            result.imageData = this.bufferToImageData(result.imageData);
        }
        if (result.imageDataSmall) {
            result.imageDataSmall = this.bufferToImageData(result.imageDataSmall);
        }
        
        return result;
    }

    // Reset worker state
    async reset() {
        return this.postMessage('multiStitchReset', {});
    }

    // Cancel current operation
    async cancel() {
        return this.postMessage('cancel', {});
    }

    // Get memory usage
    async getMemoryUsage() {
        return this.postMessage('getMemoryUsage', {});
    }

    // Simple stitch (for testing)
    async simpleStitch(images, settings = {}) {
        if (!this.isReady) {
            throw new Error('OpenCV not loaded');
        }
        
        // Just send ImageData objects directly - OpenCV.js can handle them
        const result = await this.postMessage('simpleStitch', { 
            images: images,
            settings 
        });
        
        if (result.success && result.imageData) {
            result.imageData = this.bufferToImageData(result.imageData);
        }
        
        return result;
    }

    // Convert buffer to ImageData
    bufferToImageData(bufferData) {
        if (!bufferData) return null;
        
        const { data, width, height } = bufferData;
        return new ImageData(new Uint8ClampedArray(data), width, height);
    }

    // Set progress callback
    onProgress(callback) {
        this.progressCallback = callback;
    }

    // Event emitter functionality
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }

    off(event, callback) {
        if (this.listeners.has(event)) {
            const callbacks = this.listeners.get(event);
            const index = callbacks.indexOf(callback);
            if (index > -1) {
                callbacks.splice(index, 1);
            }
        }
    }

    emit(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in worker client event listener for ${event}:`, error);
                }
            });
        }
    }

    // Terminate worker
    terminate() {
        if (this.worker) {
            // Clear pending callbacks
            for (const [id, { reject }] of this.pendingCallbacks) {
                reject(new Error('Worker terminated'));
            }
            this.pendingCallbacks.clear();
            
            // Terminate worker
            this.worker.terminate();
            this.worker = null;
            this.isReady = false;
            
            this.emit('terminated');
        }
    }

    // Restart worker
    async restart() {
        this.terminate();
        this.messageId = 0;
        this.currentProgress = 0;
        this.init();
        
        // Reload OpenCV
        if (this.opencvPath) {
            await this.load(this.opencvPath);
        }
    }

    // Check if worker is ready
    checkReady() {
        return this.isReady;
    }

    // Static method to check Web Worker support
    static isSupported() {
        return typeof Worker !== 'undefined';
    }

    // Static method to check for required features
    static checkCompatibility() {
        const issues = [];
        
        if (!WorkerClient.isSupported()) {
            issues.push('Web Workers not supported');
        }
        
        if (typeof WebAssembly === 'undefined') {
            issues.push('WebAssembly not supported');
        }
        
        if (!window.ImageData) {
            issues.push('ImageData API not supported');
        }
        
        return {
            compatible: issues.length === 0,
            issues
        };
    }
}

export default WorkerClient;