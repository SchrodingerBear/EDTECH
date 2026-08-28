// Debug utilities for testing without capturing
export class Debug {
    constructor(database) {
        this.database = database;
        this.enabled = false;
    }

    enable(showUI = true) {
        this.enabled = true;
        console.log('Debug mode enabled');
        if (showUI) {
            this.addDebugUI();
        }
    }

    addDebugUI() {
        const debugPanel = document.createElement('div');
        debugPanel.id = 'debug-panel';
        debugPanel.innerHTML = `
            <div style="position: fixed; top: calc(env(safe-area-inset-top, 0px) + 50px); left: 10px; background: rgba(0,0,0,0.8); 
                        color: white; padding: 10px; border-radius: 5px; font-family: monospace;
                        font-size: 12px; z-index: 10000; min-width: 200px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                    <h3 style="margin: 0;">Debug Tools</h3>
                    <button id="debug-toggle" style="background: none; border: none; color: white; 
                                                     font-size: 16px; cursor: pointer; padding: 0 5px;
                                                     transition: transform 0.2s;">
                        ▼
                    </button>
                </div>
                <div id="debug-buttons" style="display: block; overflow: hidden; transition: all 0.3s ease;">
                    <button id="debug-load-sample" class="debug-btn">
                        Load Sample Images (36)
                    </button>
                    <button id="debug-toggle-gain" class="debug-btn toggle-on">
                        Gain Compensation: ON
                    </button>
                    <button id="debug-toggle-multiband" class="debug-btn toggle-on">
                        Multi-band Blending: ON
                    </button>
                    <button id="debug-toggle-vignette" class="debug-btn toggle-on">
                        Vignette Correction: 0.8
                    </button>                    
                    <button id="debug-test-stitch" class="debug-btn primary">
                        Test Stitching
                    </button>
                    <button id="debug-simulate-interrupt" class="debug-btn danger">
                        Simulate Interrupted Stitch
                    </button>
                    <button id="debug-clear" class="debug-btn">
                        Clear All Data
                    </button>
                    <button id="debug-export" class="debug-btn">
                        Export Images (ZIP)
                    </button>                    
                </div>
                <hr style="margin: 10px 0;">
                <div id="debug-info">Ready</div>
            </div>
        `;
        document.body.appendChild(debugPanel);

        // Add collapse/expand functionality
        const toggleBtn = document.getElementById('debug-toggle');
        const buttonsDiv = document.getElementById('debug-buttons');
        let isCollapsed = localStorage.getItem('debugPanelCollapsed') === 'true';
        
        // Apply initial collapsed state
        if (isCollapsed) {
            buttonsDiv.style.display = 'none';
            toggleBtn.textContent = '▲';
        }
        
        toggleBtn.addEventListener('click', () => {
            isCollapsed = !isCollapsed;
            if (isCollapsed) {
                buttonsDiv.style.display = 'none';
                toggleBtn.textContent = '▲';
            } else {
                buttonsDiv.style.display = 'block';
                toggleBtn.textContent = '▼';
            }
            // Save preference
            localStorage.setItem('debugPanelCollapsed', isCollapsed.toString());
        });

        // Add event listeners for buttons
        document.getElementById('debug-load-sample').addEventListener('click', () => this.loadSampleImages());
        document.getElementById('debug-test-stitch').addEventListener('click', () => this.testStitching());
        document.getElementById('debug-clear').addEventListener('click', () => this.clearAllData());
        document.getElementById('debug-export').addEventListener('click', () => this.exportImages());
        document.getElementById('debug-simulate-interrupt').addEventListener('click', () => this.simulateInterruptedStitch());
        
        // Gain compensation toggle
        const gainBtn = document.getElementById('debug-toggle-gain');
        const gainEnabled = sessionStorage.getItem('disableGainCompensation') !== 'true';
        gainBtn.textContent = `Gain Compensation: ${gainEnabled ? 'ON' : 'OFF'}`;
        gainBtn.classList.remove('toggle-on', 'toggle-off');
        gainBtn.classList.add(gainEnabled ? 'toggle-on' : 'toggle-off');
        
        gainBtn.addEventListener('click', () => {
            const currentState = sessionStorage.getItem('disableGainCompensation') !== 'true';
            const newState = !currentState;
            sessionStorage.setItem('disableGainCompensation', newState ? 'false' : 'true');
            gainBtn.textContent = `Gain Compensation: ${newState ? 'ON' : 'OFF'}`;
            gainBtn.classList.remove('toggle-on', 'toggle-off');
            gainBtn.classList.add(newState ? 'toggle-on' : 'toggle-off');
            this.updateDebugInfo(`Gain compensation ${newState ? 'enabled' : 'disabled'}`);
        });
        
        // Multi-band blending toggle
        const multibandBtn = document.getElementById('debug-toggle-multiband');
        const multibandEnabled = sessionStorage.getItem('disableMultibandBlending') !== 'true';
        multibandBtn.textContent = `Multi-band Blending: ${multibandEnabled ? 'ON' : 'OFF'}`;
        multibandBtn.classList.remove('toggle-on', 'toggle-off');
        multibandBtn.classList.add(multibandEnabled ? 'toggle-on' : 'toggle-off');
        
        multibandBtn.addEventListener('click', () => {
            const currentState = sessionStorage.getItem('disableMultibandBlending') !== 'true';
            const newState = !currentState;
            sessionStorage.setItem('disableMultibandBlending', newState ? 'false' : 'true');
            multibandBtn.textContent = `Multi-band Blending: ${newState ? 'ON' : 'OFF'}`;
            multibandBtn.classList.remove('toggle-on', 'toggle-off');
            multibandBtn.classList.add(newState ? 'toggle-on' : 'toggle-off');
            this.updateDebugInfo(`Multi-band blending ${newState ? 'enabled' : 'disabled'}`);
        });
        
        // Vignetting correction slider
        const vignetteBtn = document.getElementById('debug-toggle-vignette');
        const currentVignette = parseFloat(sessionStorage.getItem('vignetteCorrection') || '0.8');
        vignetteBtn.textContent = `Vignette Correction: ${currentVignette.toFixed(1)}`;
        vignetteBtn.classList.remove('toggle-on', 'toggle-off');
        vignetteBtn.classList.add(currentVignette > 0 ? 'toggle-on' : 'toggle-off');
        
        vignetteBtn.addEventListener('click', () => {
            // Cycle through values: 0.0, 0.4, 0.8, 1.2
            const current = parseFloat(sessionStorage.getItem('vignetteCorrection') || '0.8');
            let newValue;
            if (current === 0) newValue = 0.4;
            else if (current === 0.4) newValue = 0.8;
            else if (current === 0.8) newValue = 1.2;
            else newValue = 0.0;
            
            sessionStorage.setItem('vignetteCorrection', newValue.toString());
            vignetteBtn.textContent = `Vignette Correction: ${newValue.toFixed(1)}`;
            vignetteBtn.classList.remove('toggle-on', 'toggle-off');
            vignetteBtn.classList.add(newValue > 0 ? 'toggle-on' : 'toggle-off');
            this.updateDebugInfo(`Vignette correction set to ${newValue.toFixed(1)}`);
        });
    }

    async loadSampleImages() {
        console.log('Loading sample images from /sample-images...');
        const infoDiv = document.getElementById('debug-info');
        infoDiv.textContent = 'Loading...';
        
        try {
            // Clear existing data first - with error handling
            try {
                await this.database.clearAllImages();
            } catch (clearError) {
                console.warn('Could not clear images, continuing anyway:', clearError);
                // Try to continue anyway
            }
            
            let loadedCount = 0;
            const totalImages = 36;
            
            // Load all 36 images
            for (let i = 1; i <= 36; i++) {
                const paddedId = String(i).padStart(2, '0');
                
                // Determine pitch and yaw from hotspot ID
                let pitch, yaw;
                if (i <= 12) {
                    // Upper ring (pitch +45)
                    pitch = 45;
                    yaw = (i - 1) * 30;
                } else if (i <= 24) {
                    // Middle ring (pitch 0)
                    pitch = 0;
                    yaw = (i - 13) * 30;
                } else {
                    // Lower ring (pitch -45)
                    pitch = -45;
                    yaw = (i - 25) * 30;
                }
                
                // Build the correct filename based on the actual format in sample-images
                // Try AVIF first, then fall back to JPEG
                const avifFilename = `hotspot_${paddedId}_p${pitch}_y${yaw}.avif`;
                const jpgFilename = `hotspot_${paddedId}_p${pitch}_y${yaw}.jpg`;
                const avifUrl = `./sample-images/${avifFilename}`;
                const jpgUrl = `./sample-images/${jpgFilename}`;
                
                let filename = avifFilename;  // Define filename in outer scope
                
                try {
                    // Try AVIF first
                    let response = await fetch(avifUrl);
                    
                    if (!response.ok) {
                        // Fall back to JPEG
                        response = await fetch(jpgUrl);
                        filename = jpgFilename;
                        if (!response.ok) {
                            console.error(`Failed to load ${avifUrl} or ${jpgUrl}: ${response.status}`);
                            continue;
                        }
                    }
                    
                    const blob = await response.blob();
                    
                    // Calculate FOV based on pitch (same as in Hotspots.calculateFOV)
                    const fov = pitch === 0 ? 67 : 
                               Math.abs(pitch) === 45 ? 54 : 67;
                    
                    // Store in database with blob (new format)
                    // NOTE: actualPitch and actualYaw should be in DEGREES, not radians!
                    await this.database.saveImage({
                        imageBlob: blob,       // Save blob directly
                        hotspotId: i,
                        pitch: pitch,          // Ideal pitch in degrees
                        yaw: yaw,              // Ideal yaw in degrees
                        actualPitch: pitch,    // Actual pitch in DEGREES (same as ideal for samples)
                        actualYaw: yaw,        // Actual yaw in DEGREES (same as ideal for samples)
                        pitchDelta: 0,         // No delta for sample images
                        yawDelta: 0,           // No delta for sample images
                        roll: 0,               // No tilt for sample images
                        fov: fov,              // Field of view based on pitch
                        timestamp: new Date().toISOString()
                    });
                    
                    loadedCount++;
                    console.log(`Loaded ${filename} (${i}/${totalImages})`);
                    
                    // Update UI if app is available
                    if (window.app) {
                        window.app.capturedHotspots.add(i);
                        window.app.updateProgress();
                        
                        // Update 3D visualization
                        if (window.app.scene) {
                            window.app.scene.markHotspotCaptured(i);
                            const hotspot = window.app.hotspots.find(h => h.id === i);
                            if (hotspot) {
                                // Create blob URL for visualization
                                const blobUrl = URL.createObjectURL(blob);
                                window.app.scene.addCapturedPatch(hotspot, blobUrl);
                                // Note: blob URL will be cleaned up when scene resets
                            }
                        }
                    }
                    
                    infoDiv.textContent = `Loading ${loadedCount}/${totalImages}...`;
                } catch (error) {
                    console.error(`Error loading ${filename}:`, error);
                }
            }
            
            infoDiv.textContent = `Loaded ${loadedCount}/${totalImages}`;
            console.log(`Successfully loaded ${loadedCount} sample images`);
            
            // Hide start screen if visible
            const startScreen = document.getElementById('start-screen');
            if (startScreen) {
                startScreen.style.display = 'none';
            }
            
            // Enable capturing
            if (window.app) {
                window.app.setCapturingEnabled(true);
                if (window.app.scene) {
                    window.app.scene.showCaptureState();
                }
            }
            
        } catch (error) {
            console.error('Error loading sample images:', error);
            infoDiv.textContent = 'Error loading';
        }
    }
    
    blobToDataURL(blob) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.readAsDataURL(blob);
        });
    }

    async clearAllData() {
        const infoDiv = document.getElementById('debug-info');
        await this.database.clearAllImages();
        
        // Clear UI state
        if (window.app) {
            window.app.capturedHotspots.clear();
            window.app.updateProgress();
            if (window.app.scene) {
                window.app.scene.clearCapturedPatches();
            }
        }
        
        infoDiv.textContent = 'Cleared all data';
        console.log('Cleared all data from database');
    }

    async testStitching() {
        console.log('Testing stitching with current data...');
        const infoDiv = document.getElementById('debug-info');
        
        // Check if we have images
        const images = await this.database.loadCapturedImages();
        if (images.length === 0) {
            infoDiv.textContent = 'No images to stitch';
            return;
        }
        
        infoDiv.textContent = `Stitching ${images.length} images...`;
        
        // Trigger stitching in the app
        if (window.sphereCapture && window.sphereCapture.app && window.sphereCapture.app.startBestPixelStitching) {
            window.sphereCapture.app.startBestPixelStitching();
        } else {
            console.error('App not available or stitching method not found');
            infoDiv.textContent = 'Error: App not ready';
        }
    }

    async testEnhancedStitching() {
        console.log('Testing enhanced stitching with OpenCV...');
        const infoDiv = document.getElementById('debug-info');
        
        // Check if we have images
        const images = await this.database.loadCapturedImages();
        if (images.length === 0) {
            infoDiv.textContent = 'No images to stitch';
            return;
        }
        
        infoDiv.textContent = `Enhanced stitching ${images.length} images...`;
        
        // Trigger enhanced stitching in the app
        if (window.app && window.app.startEnhancedStitching) {
            window.app.startEnhancedStitching();
        } else {
            console.error('App not available or enhanced stitching method not found');
            infoDiv.textContent = 'Error: App not ready';
        }
    }

    updateDebugInfo(message) {
        const infoDiv = document.getElementById('debug-info');
        if (infoDiv) {
            infoDiv.textContent = message;
        }
        console.log(`Debug: ${message}`);
    }

    async exportImages() {
        const infoDiv = document.getElementById('debug-info');
        infoDiv.textContent = 'Loading images...';
        
        try {
            // Load JSZip if not already loaded
            if (typeof JSZip === 'undefined') {
                await this.loadJSZip();
            }
            
            const images = await this.database.loadCapturedImages();
            
            if (images.length === 0) {
                infoDiv.textContent = 'No images to export';
                return;
            }
            
            infoDiv.textContent = `Creating ZIP with ${images.length} images...`;
            
            const zip = new JSZip();
            const imgFolder = zip.folder("photosphere_images");
            
            let processedCount = 0;
            
            for (const img of images) {
                try {
                    // Set default dimensions if missing
                    if (!img.width || !img.height) {
                        img.width = 720;
                        img.height = 1280;
                    }
                    
                    let imageBlob;
                    let fileExt = 'jpg';
                    
                    if (img.imageBlob) {
                        // New format: blob already available
                        imageBlob = img.imageBlob;
                        // Determine extension from blob type
                        if (img.imageBlob.type === 'image/avif') fileExt = 'avif';
                        else if (img.imageBlob.type === 'image/webp') fileExt = 'webp';
                    } else if (img.imageData) {
                        // Legacy format: base64 or raw data
                        const expectedRawLength = img.width * img.height * 4;
                        
                        if (img.imageData.length < expectedRawLength / 2) {
                            // Base64 data
                            let base64String;
                            if (typeof img.imageData === 'string') {
                                base64String = img.imageData;
                            } else if (img.imageData instanceof Array) {
                                base64String = img.imageData.map(c => String.fromCharCode(c)).join('');
                            } else {
                                const uint8Array = new Uint8Array(img.imageData);
                                const chunks = [];
                                const chunkSize = 8192;
                                for (let i = 0; i < uint8Array.length; i += chunkSize) {
                                    chunks.push(String.fromCharCode.apply(null, uint8Array.slice(i, i + chunkSize)));
                                }
                                base64String = chunks.join('');
                            }
                            
                            // Remove data URL prefix to get pure base64
                            if (base64String.startsWith('data:')) {
                                const commaIndex = base64String.indexOf(',');
                                base64String = base64String.substring(commaIndex + 1);
                            }
                            
                            // Convert base64 to blob
                            const byteCharacters = atob(base64String);
                            const byteNumbers = new Array(byteCharacters.length);
                            for (let i = 0; i < byteCharacters.length; i++) {
                                byteNumbers[i] = byteCharacters.charCodeAt(i);
                            }
                            const byteArray = new Uint8Array(byteNumbers);
                            imageBlob = new Blob([byteArray], {type: 'image/jpeg'});
                            
                        } else {
                            // Raw pixel data - convert to blob via canvas
                            const imageData = new ImageData(
                                new Uint8ClampedArray(img.imageData),
                                img.width,
                                img.height
                            );
                            const canvas = document.createElement('canvas');
                            canvas.width = img.width;
                            canvas.height = img.height;
                            const ctx = canvas.getContext('2d');
                            ctx.putImageData(imageData, 0, 0);
                            
                            // Convert canvas to blob
                            imageBlob = await new Promise(resolve => {
                                canvas.toBlob(resolve, 'image/jpeg', 0.9);
                            });
                        }
                    }
                    
                    // Add to ZIP
                    const pitch = Math.round(img.pitch || 0);
                    const yaw = Math.round(img.yaw || 0);
                    const filename = `hotspot_${String(img.hotspotId).padStart(2, '0')}_p${pitch}_y${yaw}.${fileExt}`;
                    
                    imgFolder.file(filename, imageBlob);
                    processedCount++;
                    infoDiv.textContent = `Processing: ${processedCount}/${images.length}`;
                    
                } catch (error) {
                    console.error(`Error processing image ${img.hotspotId} for ZIP:`, error);
                }
            }
            
            // Add metadata JSON file
            const metadata = images.map(img => ({
                hotspotId: img.hotspotId,
                pitch: img.pitch,
                yaw: img.yaw,
                actualPitch: img.actualPitch,
                actualYaw: img.actualYaw,
                roll: img.roll,
                fov: img.fov,
                width: img.width || 720,
                height: img.height || 1280,
                timestamp: img.timestamp
            }));
            
            imgFolder.file("metadata.json", JSON.stringify(metadata, null, 2));
            
            infoDiv.textContent = 'Generating ZIP file...';
            
            // Generate ZIP file
            const content = await zip.generateAsync({
                type: "blob",
                compression: "DEFLATE",
                compressionOptions: {
                    level: 6
                }
            });
            
            // Create filename with timestamp
            const now = new Date();
            const timestamp = now.toISOString().replace(/[:.]/g, '-').slice(0, -5);
            const filename = `photosphere_${timestamp}.zip`;
            
            // Download the file
            const url = URL.createObjectURL(content);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Clean up
            setTimeout(() => URL.revokeObjectURL(url), 5000);
            
            infoDiv.textContent = `Exported ${processedCount} images`;
            console.log(`Successfully exported ${processedCount} images to ${filename}`);
            
        } catch (error) {
            console.error('Error exporting images:', error);
            infoDiv.textContent = 'Export failed';
        }
    }
    
    async loadJSZip() {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async simulateInterruptedStitch() {
        console.log('Simulating interrupted stitch...');
        const infoDiv = document.getElementById('debug-info');
        
        try {
            // Check if we have images
            const images = await this.database.loadCapturedImages();
            if (images.length === 0) {
                infoDiv.textContent = 'No images - load sample images first';
                return;
            }
            
            // Generate a fake job ID
            const jobId = 'debug-' + Date.now();
            
            // Create a stitch job in "running" state
            const job = {
                id: jobId,
                imageIds: images.map(img => String(img.hotspotId)),
                params: { debug: true },
                status: 'running',
                startedAt: Date.now() - 60000, // Started 1 minute ago
                updatedAt: Date.now(),
                retries: 0
            };
            
            // Save the job to database
            await this.database.saveStitchJob(job);
            
            // Set localStorage flags to indicate stitch in progress
            localStorage.setItem('stitch_in_progress', '1');
            localStorage.setItem('stitch_job_id', jobId);
            
            infoDiv.textContent = `Created interrupted stitch job: ${jobId}`;
            console.log('Simulated interrupted stitch job:', job);
            
            // Alert user to reload the page
            if (window.app && window.app.cardUI) {
                await window.app.cardUI.alert(
                    'Simulated an interrupted stitch. Reload the page to test recovery.',
                    'Test Setup Complete'
                );
            } else {
                alert('Simulated an interrupted stitch. Reload the page to test recovery.');
            }
            
        } catch (error) {
            console.error('Failed to simulate interrupted stitch:', error);
            infoDiv.textContent = 'Simulation failed';
        }
    }
}