// Camera module for handling camera operations
export class Camera {
    constructor(app = null) {
        this.app = app; // Reference to app for dialog access
        this.stream = null;
        this.videoElement = null;
        this.captureCanvas = null;
        this.ctx = null;
        this.currentDeviceId = null;
        this.devices = [];
    }

    async init(videoElement, captureCanvas, autoStart = true) {
        this.videoElement = videoElement;
        this.captureCanvas = captureCanvas;
        this.ctx = captureCanvas.getContext('2d');
        
        // Don't enumerate cameras yet - it might trigger permission prompt
        // We'll do it when actually starting the camera
        
        // Only start camera if autoStart is true
        if (autoStart) {
            // Get available cameras
            await this.enumerateCameras();
            // Start with default camera
            await this.startCamera();
        }
    }

    async enumerateCameras() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            this.devices = devices.filter(device => device.kind === 'videoinput');
            console.log(`Found ${this.devices.length} cameras`);
            return this.devices;
        } catch (error) {
            console.error('Error enumerating cameras:', error);
            return [];
        }
    }

    async startCamera(deviceId = null) {
        console.log('Starting camera...');
        try {
            // Stop existing stream
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
            }

            // Camera constraints
            const constraints = {
                video: {
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                    facingMode: deviceId ? undefined : 'environment'
                }
            };

            if (deviceId) {
                constraints.video.deviceId = { exact: deviceId };
            }

            // Get camera stream
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.videoElement.srcObject = this.stream;
            
            // Get actual device ID
            const videoTrack = this.stream.getVideoTracks()[0];
            const settings = videoTrack.getSettings();
            this.currentDeviceId = settings.deviceId;
            
            console.log('Camera started:', settings.width + 'x' + settings.height);
            
            return true;
        } catch (error) {
            console.error('Error accessing camera:', error);
            if (this.app && this.app.cardUI) {
                await this.app.cardUI.alert('Unable to access camera. Please ensure camera permissions are granted.', 'Camera Error');
            } else {
                alert('Unable to access camera. Please ensure camera permissions are granted.');
            }
            return false;
        }
    }

    async switchCamera() {
        if (this.devices.length < 2) return;
        
        const currentIndex = this.devices.findIndex(d => d.deviceId === this.currentDeviceId);
        const nextIndex = (currentIndex + 1) % this.devices.length;
        const nextDevice = this.devices[nextIndex];
        
        await this.startCamera(nextDevice.deviceId);
    }

    /**
     * Encode canvas to blob with fallback support
     * Tries AVIF, then WebP, then JPEG
     */
    async encodeCanvas(canvas, quality = 0.85) {
        // Use JPEG everywhere for consistency and reliability
        // iOS Safari returns PNG for AVIF/WebP (1360KB vs 195KB for JPEG!)
        // The overhead of trying formats isn't worth it for a mobile-first app
        const tryTypes = [
            ['image/jpeg', 0.85],    // JPEG - universal, reliable, small enough
        ];
        
        for (const [type, q] of tryTypes) {
            console.log(`Attempting to encode as ${type} with quality ${q}`);
            const blob = await new Promise(resolve => 
                canvas.toBlob(resolve, type, q)
            );
            
            if (blob && blob.size > 0) {
                console.log(`Result: ${blob.type}, size: ${(blob.size / 1024).toFixed(1)}KB`);
                // If we got what we asked for, or if it's our last option, use it
                if (blob.type === type || type === 'image/jpeg') {
                    return blob;
                }
                // Otherwise, Safari gave us something else, try next format
                console.log(`Safari returned ${blob.type} instead of ${type}, trying next format...`);
            }
        }
        
        throw new Error('Failed to encode image');
    }

    async capturePhoto(maxWidth = 768, maxHeight = 1024) {
        console.log('capturePhoto called', {
            videoElement: this.videoElement,
            srcObject: this.videoElement?.srcObject,
            readyState: this.videoElement?.readyState,
            targetResolution: `${maxWidth}x${maxHeight}`
        });
        
        if (!this.videoElement || !this.videoElement.srcObject) {
            throw new Error('Camera not initialized');
        }

        // Ensure video has loaded
        if (this.videoElement.readyState !== this.videoElement.HAVE_ENOUGH_DATA) {
            console.log('Waiting for video data...');
            await new Promise(resolve => {
                this.videoElement.addEventListener('loadeddata', resolve, { once: true });
            });
        }

        // Ensure video is playing
        if (this.videoElement.paused) {
            console.warn('Video element is paused, attempting to play');
            await this.videoElement.play();
            // Wait a bit for video to start playing
            await new Promise(resolve => setTimeout(resolve, 100));
        }

        // For first capture or if video time is very low, wait for fresh frames
        if (this.videoElement.currentTime < 0.5) {
            console.log('Waiting for fresh frames...');
            await new Promise(resolve => setTimeout(resolve, 500));
        }

        // Use requestAnimationFrame to ensure we capture the current frame
        return new Promise((resolve, reject) => {
            requestAnimationFrame(() => {
                // Get original video dimensions
                const videoWidth = this.videoElement.videoWidth;
                const videoHeight = this.videoElement.videoHeight;
                
                // Calculate scaled dimensions maintaining aspect ratio
                const scale = Math.min(maxWidth / videoWidth, maxHeight / videoHeight);
                const scaledWidth = Math.round(videoWidth * scale);
                const scaledHeight = Math.round(videoHeight * scale);
                
                console.log(`Original: ${videoWidth}x${videoHeight}, Scaled: ${scaledWidth}x${scaledHeight}, Scale: ${scale.toFixed(2)}`);
                
                // Set canvas to scaled size
                this.captureCanvas.width = scaledWidth;
                this.captureCanvas.height = scaledHeight;
                
                // Clear canvas first to ensure fresh capture
                this.ctx.clearRect(0, 0, scaledWidth, scaledHeight);
                
                // Draw scaled frame from video
                this.ctx.drawImage(this.videoElement, 0, 0, scaledWidth, scaledHeight);
                
                // Encode to blob with fallback
                this.encodeCanvas(this.captureCanvas, 0.85)
                    .then(blob => {
                        // Log capture details
                        console.log(`Captured frame at video time: ${this.videoElement.currentTime}s`);
                        console.log(`Blob size: ${(blob.size / 1024).toFixed(1)}KB, type: ${blob.type}`);
                        resolve(blob);
                    })
                    .catch(reject);
            });
        });
    }

    stop() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
    }
}