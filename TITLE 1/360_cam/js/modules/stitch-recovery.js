/**
 * Stitch Recovery Module
 * Handles detection and recovery of interrupted stitching operations
 * Helps users resume work after iOS Safari memory crashes
 */

export class StitchRecovery {
    constructor() {
        // localStorage keys for tracking stitch state
        this.STITCH_IN_PROGRESS_KEY = 'stitch_in_progress';
        this.STITCH_JOB_ID_KEY = 'stitch_job_id';
        
        // Maximum age for stitch jobs (7 days)
        this.MAX_JOB_AGE = 7 * 24 * 60 * 60 * 1000;
        
        // References to be set by app
        this.database = null;
        this.app = null;
    }
    
    /**
     * Initialize with app and database references
     */
    init(app, database) {
        this.app = app;
        this.database = database;
    }
    
    // ============================================
    // LOCALSTORAGE HELPERS
    // ============================================
    
    /**
     * Check if a stitch is marked as in progress
     */
    isStitchInProgress() {
        return localStorage.getItem(this.STITCH_IN_PROGRESS_KEY) === '1';
    }
    
    /**
     * Check if there's an interrupted stitch WITHOUT showing dialog
     * Used during init to check state before UI is ready
     */
    async hasInterruptedStitch() {
        if (!this.isStitchInProgress()) {
            return false;
        }
        
        const jobId = localStorage.getItem(this.STITCH_JOB_ID_KEY);
        if (!jobId) {
            // Clear invalid state
            this.setStitchInProgress(false);
            return false;
        }
        
        // Load job from database to check its age
        try {
            const job = await this.database.getStitchJob(jobId);
            if (!job) {
                // Job doesn't exist in database
                this.setStitchInProgress(false);
                return false;
            }
            
            // Check if job is too old based on its startedAt timestamp
            const age = Date.now() - (job.startedAt || 0);
            if (age > this.MAX_JOB_AGE) {
                // Job is too old, clear it
                this.setStitchInProgress(false);
                return false;
            }
        } catch (error) {
            console.warn('Error checking stitch job:', error);
            this.setStitchInProgress(false);
            return false;
        }
        
        // Check if we have captures
        const captures = await this.database.loadCapturedImages();
        return captures && captures.length > 0;
    }
    
    /**
     * Set stitch in progress flag
     */
    setStitchInProgress(inProgress, jobId = null) {
        if (inProgress) {
            localStorage.setItem(this.STITCH_IN_PROGRESS_KEY, '1');
            if (jobId) {
                localStorage.setItem(this.STITCH_JOB_ID_KEY, jobId);
            }
        } else {
            localStorage.setItem(this.STITCH_IN_PROGRESS_KEY, '0');
        }
    }
    
    /**
     * Get current stitch job ID
     */
    getStitchJobId() {
        return localStorage.getItem(this.STITCH_JOB_ID_KEY) || null;
    }
    
    /**
     * Clear all stitch flags
     */
    clearStitchFlags() {
        localStorage.removeItem(this.STITCH_IN_PROGRESS_KEY);
        localStorage.removeItem(this.STITCH_JOB_ID_KEY);
    }
    
    // ============================================
    // JOB MANAGEMENT
    // ============================================
    
    /**
     * Create and save a new stitch job
     */
    async createStitchJob(imageIds, params = {}) {
        const job = {
            imageIds: imageIds,
            params: params,
            status: 'pending',
            startedAt: Date.now(),
            updatedAt: Date.now(),
            retries: 0
        };
        
        const jobId = await this.database.saveStitchJob(job);
        this.setStitchInProgress(true, jobId);
        
        console.log(`Created stitch job ${jobId} with ${imageIds.length} images`);
        return jobId;
    }
    
    /**
     * Mark job as started
     */
    async markJobStarted(jobId) {
        await this.database.updateStitchJob(jobId, {
            status: 'running',
            updatedAt: Date.now()
        });
    }
    
    /**
     * Mark job as completed
     */
    async markJobCompleted(jobId) {
        await this.database.updateStitchJob(jobId, {
            status: 'complete',
            completedAt: Date.now(),
            updatedAt: Date.now()
        });
        this.clearStitchFlags();
    }
    
    /**
     * Mark job as failed
     */
    async markJobFailed(jobId, error = null) {
        await this.database.updateStitchJob(jobId, {
            status: 'failed',
            error: error ? String(error) : 'Unknown error',
            updatedAt: Date.now()
        });
        // Keep flags set so user can retry on next load
    }
    
    // ============================================
    // RECOVERY DETECTION
    // ============================================
    
    /**
     * Check for interrupted stitch on app load
     * Should be called as early as possible
     */
    async checkForInterruptedStitch() {
        // Check if stitch was in progress
        if (!this.isStitchInProgress()) {
            return null;
        }
        
        const jobId = this.getStitchJobId();
        if (!jobId) {
            this.clearStitchFlags();
            return null;
        }
        
        // Load job from database
        let job;
        try {
            job = await this.database.getStitchJob(jobId);
            if (!job) {
                console.log('Stitch job not found in database, clearing flags');
                this.clearStitchFlags();
                return null;
            }
        } catch (error) {
            console.log('Error loading stitch job, clearing flags:', error);
            this.clearStitchFlags();
            return null;
        }
        
        // Add the job ID to the job object if it doesn't have it
        if (!job.id) {
            job.id = jobId;
        }
        
        // Check if job is too old
        if (this.isJobStale(job)) {
            console.log('Stitch job is too old, clearing flags');
            this.clearStitchFlags();
            await this.database.updateStitchJob(jobId, {
                status: 'expired',
                error: 'Job expired after 7 days'
            });
            return null;
        }
        
        // Check if all images still exist
        const imagesExist = await this.verifyImagesExist(job.imageIds);
        if (!imagesExist) {
            console.log('Some images missing, clearing flags');
            this.clearStitchFlags();
            await this.database.updateStitchJob(jobId, {
                status: 'failed',
                error: 'Source images no longer available'
            });
            return null;
        }
        
        console.log(`Found interrupted stitch job ${jobId} with ${job.imageIds.length} images`);
        return job;
    }
    
    /**
     * Check if job is older than MAX_JOB_AGE
     */
    isJobStale(job) {
        const age = Date.now() - (job.startedAt || job.updatedAt || 0);
        return age > this.MAX_JOB_AGE;
    }
    
    /**
     * Verify all images exist in database
     */
    async verifyImagesExist(imageIds) {
        if (!imageIds || imageIds.length === 0) {
            return false;
        }
        
        try {
            // Load all captured images
            const capturedImages = await this.database.loadCapturedImages();
            const capturedIds = new Set(capturedImages.map(img => 
                img.hotspotId ? String(img.hotspotId) : null
            ).filter(id => id !== null));
            
            // Check if all job images exist
            return imageIds.every(id => capturedIds.has(String(id)));
        } catch (error) {
            console.error('Error verifying images:', error);
            return false;
        }
    }
    
    // ============================================
    // RECOVERY UI
    // ============================================
    
    /**
     * Show recovery modal to user
     */
    async showRecoveryModal(job) {
        if (!this.app || !this.app.cardUI) {
            console.error('CardUI not available, using native confirm');
            // Fallback to native confirm if CardUI isn't ready
            const message = `Your last panorama stitch was interrupted (${job.imageIds.length} images).\n\nRetry the stitching process?`;
            return confirm(message) ? 'retry' : 'discard';
        }
        
        const message = `Your last panorama stitch was interrupted. You had captured ${job.imageIds.length} images.\n\n` +
                       `Would you like to retry the stitching process?`;
        
        const choice = await this.app.cardUI.showDialog({
            title: 'Stitch Interrupted',
            message: message,
            type: 'confirm',
            confirmText: 'Retry Stitch',
            cancelText: 'Discard & Start Over'
        });
        
        return choice ? 'retry' : 'discard';
    }
    
    // ============================================
    // RECOVERY ACTIONS
    // ============================================
    
    /**
     * Handle retry action
     */
    async handleRetry(job) {
        console.log('Retrying stitch job', job.id);
        
        // Update job retry count
        await this.database.updateStitchJob(job.id, {
            status: 'running',
            retries: (job.retries || 0) + 1,
            updatedAt: Date.now()
        });
        
        // Keep flags set
        this.setStitchInProgress(true, job.id);
        
        // Hard reset transient state
        await this.hardResetTransientState();
        
        // Hide start screen if visible
        const startScreen = document.getElementById('start-screen');
        if (startScreen) {
            startScreen.style.display = 'none';
            startScreen.classList.remove('visible');
        }
        
        // Load images and restart stitch
        const capturedData = await this.database.loadCapturedImages();
        
        // Filter to only the images in the job
        const jobImageIds = new Set(job.imageIds.map(id => parseInt(id)));
        const jobImages = capturedData.filter(img => 
            jobImageIds.has(img.hotspotId)
        );
        
        if (jobImages.length === 0) {
            throw new Error('No images found for retry');
        }
        
        // Start stitching through app
        if (this.app && this.app.startBestPixelStitchingWithData) {
            await this.app.startBestPixelStitchingWithData(jobImages, job.id);
        } else {
            throw new Error('App stitch method not available');
        }
    }
    
    /**
     * Handle discard action
     */
    async handleDiscard(job) {
        console.log('Discarding stitch job', job.id);
        
        // Mark job as discarded
        await this.database.updateStitchJob(job.id, {
            status: 'failed',
            error: 'User discarded after reload',
            updatedAt: Date.now()
        });
        
        // Clear flags
        this.clearStitchFlags();
        
        // Clear session and return to start if app is ready
        if (this.app && this.app.clearSession) {
            await this.app.clearSession();
            
            // Show start screen
            const startScreen = document.getElementById('start-screen');
            if (startScreen) {
                startScreen.style.display = 'block';
                startScreen.classList.add('visible');
            }
            
            // Hide capture UI
            document.getElementById('scene-container').style.display = 'none';
            document.getElementById('camera-viewport').style.display = 'none';
            document.getElementById('alignment-indicator').style.display = 'none';
            document.getElementById('instructions').style.display = 'none';
            const bottomControls = document.querySelector('.bottom-controls');
            if (bottomControls) {
                bottomControls.style.display = 'none';
            }
        }
    }
    
    /**
     * Hard reset transient WebGL state to free memory
     */
    async hardResetTransientState() {
        console.log('Performing hard reset of transient state');
        
        // Try to lose and restore WebGL context
        if (this.app && this.app.gl) {
            try {
                const gl = this.app.gl;
                const lose = gl.getExtension('WEBGL_lose_context');
                if (lose) {
                    lose.loseContext();
                    await new Promise(resolve => setTimeout(resolve, 100));
                    lose.restoreContext();
                }
            } catch (error) {
                console.warn('Could not reset WebGL context:', error);
            }
        }
        
        // Revoke any object URLs
        if (this.app && this.app.objectURLs) {
            this.app.objectURLs.forEach(url => {
                try {
                    URL.revokeObjectURL(url);
                } catch (e) {}
            });
            this.app.objectURLs = [];
        }
        
        // Clear any cached canvases or large arrays
        if (this.app) {
            if (this.app.webglLayers) {
                this.app.webglLayers = null;
            }
            if (this.app.tempCanvases) {
                this.app.tempCanvases = null;
            }
        }
        
        // Force garbage collection pause
        await new Promise(resolve => setTimeout(resolve, 0));
    }
    
    // ============================================
    // MAIN RECOVERY FLOW
    // ============================================
    
    /**
     * Main recovery check - call this on app load
     */
    async checkAndRecover() {
        try {
            // Check for interrupted stitch
            const job = await this.checkForInterruptedStitch();
            
            if (!job) {
                // No interrupted stitch
                return false;
            }
            
            // Show recovery modal
            const choice = await this.showRecoveryModal(job);
            
            if (choice === 'retry') {
                await this.handleRetry(job);
                return 'retry'; // Return specific action taken
            } else {
                await this.handleDiscard(job);
                return 'discard'; // Return specific action taken
            }
            
        } catch (error) {
            console.error('Error during stitch recovery:', error);
            this.clearStitchFlags();
            
            // Show error to user
            if (this.app && this.app.cardUI) {
                await this.app.cardUI.alert(
                    'Failed to recover the interrupted stitch. Please start over.',
                    'Recovery Failed'
                );
            } else {
                // Fallback to native alert
                alert('Failed to recover the interrupted stitch. Please start over.');
            }
            
            return false;
        }
    }
}

// Create singleton instance
export const stitchRecovery = new StitchRecovery();