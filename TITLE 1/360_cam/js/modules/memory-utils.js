// Memory management utilities for OpenCV operations

/**
 * MatManager - Tracks OpenCV Mat objects to ensure proper cleanup
 */
export class MatManager {
    constructor() {
        this.mats = new Set();
        this.namedMats = new Map(); // For debugging - track mats by name
    }
    
    /**
     * Track a cv.Mat object
     * @param {cv.Mat} mat - The mat to track
     * @param {string} name - Optional name for debugging
     * @returns {cv.Mat} The same mat for chaining
     */
    track(mat, name = '') {
        if (!mat || mat.isDeleted()) {
            console.warn(`Attempted to track deleted or null mat: ${name}`);
            return mat;
        }
        
        this.mats.add(mat);
        if (name) {
            this.namedMats.set(name, mat);
        }
        return mat;
    }
    
    /**
     * Stop tracking a specific mat (usually because it was deleted elsewhere)
     */
    untrack(mat) {
        this.mats.delete(mat);
        // Remove from named mats if present
        for (const [name, m] of this.namedMats.entries()) {
            if (m === mat) {
                this.namedMats.delete(name);
                break;
            }
        }
    }
    
    /**
     * Delete and untrack a specific mat
     */
    delete(mat, name = '') {
        if (mat && !mat.isDeleted()) {
            mat.delete();
        }
        this.untrack(mat);
        if (name && this.namedMats.has(name)) {
            this.namedMats.delete(name);
        }
    }
    
    /**
     * Clean up all tracked mats
     */
    cleanup() {
        let cleaned = 0;
        let alreadyDeleted = 0;
        
        for (const mat of this.mats) {
            if (mat && !mat.isDeleted()) {
                mat.delete();
                cleaned++;
            } else {
                alreadyDeleted++;
            }
        }
        
        this.mats.clear();
        this.namedMats.clear();
        
        console.log(`MatManager cleanup: ${cleaned} mats deleted, ${alreadyDeleted} already deleted`);
    }
    
    /**
     * Get current tracking stats
     */
    getStats() {
        let active = 0;
        let deleted = 0;
        
        for (const mat of this.mats) {
            if (mat && !mat.isDeleted()) {
                active++;
            } else {
                deleted++;
            }
        }
        
        return { active, deleted, total: this.mats.size };
    }
}

/**
 * MemoryMonitor - Monitors memory usage and provides warnings
 */
export class MemoryMonitor {
    constructor() {
        this.warningThreshold = 0.8; // Warn at 80% memory usage
        this.criticalThreshold = 0.9; // Critical at 90% memory usage
        this.lastGC = 0;
        this.gcInterval = 30000; // Force GC every 30 seconds if needed
        
        // Track image loads for Safari/iOS
        this.loadedImages = [];
        this.estimatedMemoryUsage = 0;
        
        // iOS memory limits (updated for modern devices)
        this.iosMemoryLimits = {
            // Based on device capabilities and typical Safari limits
            small: 400,   // MB - older devices (iPhone 6-8)
            medium: 800,  // MB - mid-range devices (iPhone X-12)
            large: 1200   // MB - newer/pro devices (iPhone 13-15 Pro)
        };
        
        this.deviceMemoryLimit = this.detectDeviceMemoryLimit();
    }
    
    /**
     * Detect device memory limit for iOS/Safari
     */
    detectDeviceMemoryLimit() {
        // Use navigator.deviceMemory if available (some browsers)
        if (navigator.deviceMemory) {
            // deviceMemory is in GB, we typically get 10-15% for web apps
            const limitMB = navigator.deviceMemory * 1024 * 0.15; // Convert to MB, use 15%
            console.log(`Device memory detected: ${navigator.deviceMemory}GB, estimated limit: ${limitMB}MB`);
            return Math.max(limitMB, this.iosMemoryLimits.medium);
        }
        
        // For iOS devices, use a combination of checks
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
                     (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        
        // Check screen dimensions (logical pixels)
        const screenWidth = window.screen.width;
        const screenHeight = window.screen.height;
        const devicePixelRatio = window.devicePixelRatio || 1;
        
        console.log(`Device detection - Screen: ${screenWidth}x${screenHeight}, DPR: ${devicePixelRatio}, iOS: ${isIOS}`);
        
        if (isIOS) {
            // iPhone detection based on logical screen dimensions and DPR
            // Pro Max models: 430x932 @ 3x, Plus models: 414x896 @ 3x
            // Pro models: 393x852 @ 3x, Standard: 390x844 @ 3x, Mini: 375x812 @ 3x
            
            // Check for Pro Max models (newest high-end devices)
            if ((screenWidth >= 428 && screenHeight >= 926) || 
                (screenHeight >= 428 && screenWidth >= 926)) {
                console.log('Detected iPhone Pro Max model');
                return this.iosMemoryLimits.large;
            }
            
            // Check for Plus/Pro models
            if ((screenWidth >= 390 && devicePixelRatio >= 3) ||
                (screenHeight >= 390 && devicePixelRatio >= 3)) {
                console.log('Detected iPhone Pro/Plus model');
                return this.iosMemoryLimits.large;
            }
            
            // iPad detection
            if (screenWidth >= 768 || screenHeight >= 768) {
                console.log('Detected iPad');
                return this.iosMemoryLimits.large;
            }
            
            // Older or smaller iPhones
            console.log('Detected standard/older iPhone');
            return this.iosMemoryLimits.medium;
        }
        
        // For non-iOS devices, check total pixel count as fallback
        const totalPixels = screenWidth * screenHeight * devicePixelRatio * devicePixelRatio;
        console.log(`Non-iOS device, total pixels: ${totalPixels}`);
        
        if (totalPixels > 8000000) { // 8M+ pixels
            return this.iosMemoryLimits.large;
        } else if (totalPixels > 4000000) { // 4M+ pixels
            return this.iosMemoryLimits.medium;
        } else {
            return this.iosMemoryLimits.small;
        }
    }
    
    /**
     * Track image load for memory estimation
     */
    trackImageLoad(width, height, bytesPerPixel = 4) {
        const sizeMB = (width * height * bytesPerPixel) / 1048576;
        this.loadedImages.push({ width, height, sizeMB });
        this.estimatedMemoryUsage += sizeMB;
    }
    
    /**
     * Clear tracked images
     */
    clearTrackedImages() {
        this.loadedImages = [];
        this.estimatedMemoryUsage = 0;
    }
    
    /**
     * Get current memory usage stats
     */
    getMemoryStats() {
        // Try Chrome's performance.memory first
        if (performance.memory) {
            const used = performance.memory.usedJSHeapSize;
            const total = performance.memory.totalJSHeapSize;
            const limit = performance.memory.jsHeapSizeLimit;
            
            return {
                used: used / 1048576, // Convert to MB
                total: total / 1048576,
                limit: limit / 1048576,
                usedPercentage: used / limit,
                availableMB: (limit - used) / 1048576,
                source: 'chrome'
            };
        }
        
        // Fallback for Safari/iOS - use our estimates
        const limit = this.deviceMemoryLimit;
        const used = this.estimatedMemoryUsage;
        const usedPercentage = used / limit;
        
        return {
            used: used,
            total: used, // We don't know allocated, just used
            limit: limit,
            usedPercentage: usedPercentage,
            availableMB: Math.max(0, limit - used),
            source: 'estimate',
            deviceType: limit === this.iosMemoryLimits.large ? 'high-end' : 
                       limit === this.iosMemoryLimits.medium ? 'mid-range' : 'low-end'
        };
    }
    
    /**
     * Check memory and return status
     */
    checkMemory() {
        const stats = this.getMemoryStats();
        if (!stats) {
            return { status: 'unknown', stats: null };
        }
        
        let status = 'ok';
        if (stats.usedPercentage >= this.criticalThreshold) {
            status = 'critical';
        } else if (stats.usedPercentage >= this.warningThreshold) {
            status = 'warning';
        }
        
        return { status, stats };
    }
    
    /**
     * Log current memory usage with context
     */
    logMemoryUsage(context = '') {
        const stats = this.getMemoryStats();
        if (!stats) {
            console.log(`[Memory - ${context}] Unable to determine memory usage`);
            return;
        }
        
        const percentage = (stats.usedPercentage * 100).toFixed(1);
        const statusEmoji = stats.usedPercentage >= this.criticalThreshold ? '🔴' : 
                           stats.usedPercentage >= this.warningThreshold ? '🟡' : '🟢';
        
        const sourceInfo = stats.source === 'estimate' ? ` (${stats.deviceType} device estimate)` : '';
        
        console.log(
            `${statusEmoji} [Memory - ${context}] ` +
            `${stats.used.toFixed(1)}MB / ${stats.limit.toFixed(1)}MB (${percentage}%)${sourceInfo} ` +
            `Available: ${stats.availableMB.toFixed(1)}MB`
        );
    }
    
    /**
     * Attempt to trigger garbage collection if needed
     */
    async tryGarbageCollection() {
        const now = Date.now();
        if (now - this.lastGC < this.gcInterval) {
            return false;
        }
        
        const { status } = this.checkMemory();
        if (status === 'warning' || status === 'critical') {
            // Create and immediately destroy some large objects to trigger GC
            console.log('Attempting to trigger garbage collection...');
            const before = this.getMemoryStats();
            
            // This is a hack but can help trigger GC
            for (let i = 0; i < 10; i++) {
                let arr = new Uint8Array(1024 * 1024); // 1MB
                arr = null;
            }
            
            // Give GC time to run
            await new Promise(resolve => setTimeout(resolve, 100));
            
            const after = this.getMemoryStats();
            if (before && after) {
                const freed = before.used - after.used;
                console.log(`GC potentially freed ${freed.toFixed(1)}MB`);
            }
            
            this.lastGC = now;
            return true;
        }
        
        return false;
    }
    
    /**
     * Calculate safe image dimensions based on available memory
     */
    getSafeImageDimensions(originalWidth, originalHeight, bytesPerPixel = 4) {
        const stats = this.getMemoryStats();
        
        // CRITICAL: For feature matching, we need sufficient resolution
        // 405x720 is TOO LOW - we need at least 720x1280
        const MIN_WIDTH = 720;
        const MIN_HEIGHT = 1280;
        
        // If image is already small enough, keep it
        if (originalWidth <= MIN_WIDTH && originalHeight <= MIN_HEIGHT) {
            console.log(`Keeping original dimensions: ${originalWidth}x${originalHeight}`);
            if (stats && stats.source === 'estimate') {
                this.trackImageLoad(originalWidth, originalHeight, bytesPerPixel);
            }
            return { width: originalWidth, height: originalHeight };
        }
        
        if (!stats) {
            // Return minimum viable dimensions for feature matching
            console.log(`No memory stats, using minimum viable: ${MIN_WIDTH}x${MIN_HEIGHT}`);
            return { width: MIN_WIDTH, height: MIN_HEIGHT };
        }
        
        // Check if we have critical memory situation
        const originalSizeMB = (originalWidth * originalHeight * bytesPerPixel) / 1048576;
        
        // Only scale down if absolutely necessary (critical memory)
        if (stats.usedPercentage >= 0.85) {
            console.warn(`Critical memory (${(stats.usedPercentage * 100).toFixed(1)}%), scaling down from ${originalWidth}x${originalHeight}`);
            
            // But never go below minimum viable dimensions
            const scale = Math.sqrt(stats.availableMB / originalSizeMB);
            const newWidth = Math.max(MIN_WIDTH, Math.floor(originalWidth * scale));
            const newHeight = Math.max(MIN_HEIGHT, Math.floor(originalHeight * scale));
            
            if (stats.source === 'estimate') {
                this.trackImageLoad(newWidth, newHeight, bytesPerPixel);
            }
            
            console.log(`Scaled to: ${newWidth}x${newHeight}`);
            return { width: newWidth, height: newHeight };
        }
        
        // Otherwise, keep original dimensions for best feature matching
        console.log(`Memory OK (${(stats.usedPercentage * 100).toFixed(1)}%), keeping full resolution: ${originalWidth}x${originalHeight}`);
        if (stats.source === 'estimate') {
            this.trackImageLoad(originalWidth, originalHeight, bytesPerPixel);
        }
        return { width: originalWidth, height: originalHeight };
    }
}

/**
 * SmartImageLoader - Loads images with memory-aware caching and resolution management
 */
export class SmartImageLoader {
    constructor(maxCacheSize = 5) {
        this.cache = new Map();
        this.maxCacheSize = maxCacheSize;
        this.matManager = new MatManager();
        this.memoryMonitor = new MemoryMonitor();
    }
    
    /**
     * Load an image at specified resolution
     * @param {string} base64 - Base64 encoded image
     * @param {string} id - Unique identifier for caching
     * @param {string} resolution - 'thumbnail', 'medium', or 'full'
     */
    async loadImage(base64, id, resolution = 'medium') {
        const cacheKey = `${id}_${resolution}`;
        
        // Check cache first
        if (this.cache.has(cacheKey)) {
            const cached = this.cache.get(cacheKey);
            if (!cached.isDeleted()) {
                return cached;
            }
            // Remove deleted mat from cache
            this.cache.delete(cacheKey);
        }
        
        // Check memory before loading
        this.memoryMonitor.logMemoryUsage(`Before loading ${id} at ${resolution}`);
        const memCheck = this.memoryMonitor.checkMemory();
        
        // For iOS/Safari, be more aggressive about memory management
        const stats = this.memoryMonitor.getMemoryStats();
        if (stats && stats.source === 'estimate' && memCheck.status === 'warning') {
            // Clear cache more aggressively on iOS
            this.clearCache();
            this.memoryMonitor.clearTrackedImages();
        }
        
        if (memCheck.status === 'critical') {
            // Try to free memory
            await this.memoryMonitor.tryGarbageCollection();
            this.clearCache();
            if (stats && stats.source === 'estimate') {
                this.memoryMonitor.clearTrackedImages();
            }
        }
        
        // Load the image
        const mat = await this.base64ToMat(base64, resolution);
        
        // Track the mat
        this.matManager.track(mat, `${id}_${resolution}`);
        
        // Add to cache with size management
        if (this.cache.size >= this.maxCacheSize) {
            // Remove oldest entry
            const firstKey = this.cache.keys().next().value;
            const firstMat = this.cache.get(firstKey);
            this.matManager.delete(firstMat);
            this.cache.delete(firstKey);
        }
        
        this.cache.set(cacheKey, mat);
        this.memoryMonitor.logMemoryUsage(`After loading ${id} at ${resolution}`);
        
        return mat;
    }
    
    /**
     * Convert base64 to cv.Mat with resolution control
     */
    async base64ToMat(base64, resolution = 'full') {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                let width = img.width;
                let height = img.height;
                
                // Apply resolution limits
                switch (resolution) {
                    case 'thumbnail':
                        ({ width, height } = this.constrainSize(width, height, 256));
                        break;
                    case 'medium':
                        // Use larger size for better feature matching
                        // Need at least 1200px for good features
                        const maxDim = /iPad|iPhone|iPod/.test(navigator.userAgent) ? 1200 : 1600;
                        ({ width, height } = this.constrainSize(width, height, maxDim));
                        console.log(`Medium resolution: ${width}x${height} (max dim: ${maxDim})`);
                        break;
                    case 'full':
                        // For full resolution, use getSafeImageDimensions which now respects minimum requirements
                        const originalDims = { width, height };
                        ({ width, height } = this.memoryMonitor.getSafeImageDimensions(width, height));
                        if (width !== originalDims.width || height !== originalDims.height) {
                            console.log(`Full resolution adjusted: ${originalDims.width}x${originalDims.height} -> ${width}x${height}`);
                        } else {
                            console.log(`Full resolution maintained: ${width}x${height}`);
                        }
                        break;
                }
                
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                try {
                    const mat = cv.imread(canvas);
                    resolve(mat);
                } catch (error) {
                    reject(error);
                }
            };
            img.onerror = reject;
            img.src = base64;
        });
    }
    
    /**
     * Constrain image size while maintaining aspect ratio
     */
    constrainSize(width, height, maxDimension) {
        if (width <= maxDimension && height <= maxDimension) {
            return { width, height };
        }
        
        const scale = maxDimension / Math.max(width, height);
        return {
            width: Math.floor(width * scale),
            height: Math.floor(height * scale)
        };
    }
    
    /**
     * Clear the image cache
     */
    clearCache() {
        console.log('Clearing image cache...');
        for (const mat of this.cache.values()) {
            this.matManager.delete(mat);
        }
        this.cache.clear();
    }
    
    /**
     * Cleanup all resources
     */
    cleanup() {
        this.clearCache();
        this.matManager.cleanup();
    }
}

/**
 * withMemoryTracking - Decorator function for memory-safe OpenCV operations
 */
export async function withMemoryTracking(operation, context = '') {
    const matManager = new MatManager();
    const memoryMonitor = new MemoryMonitor();
    
    memoryMonitor.logMemoryUsage(`Start: ${context}`);
    
    try {
        // Pass matManager to the operation so it can track mats
        const result = await operation(matManager);
        return result;
    } finally {
        // Always cleanup tracked mats
        matManager.cleanup();
        memoryMonitor.logMemoryUsage(`End: ${context}`);
        
        // Try GC if memory is high
        await memoryMonitor.tryGarbageCollection();
    }
}
