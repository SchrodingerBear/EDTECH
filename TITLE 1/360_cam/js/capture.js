// Main entry point for the photosphere capture app
import { PhotosphereApp } from './modules/app.js';
// OpenCV removed - using WebGL2 for all stitching

// Global app instance
window.sphereCapture = null;

// Global dialog helper for use before app is initialized
window.showAlert = async (message, title = 'Alert') => {
    if (window.sphereCapture && window.sphereCapture.cardUI) {
        return window.sphereCapture.cardUI.alert(message, title);
    } else {
        alert(message);
    }
};

window.showConfirm = async (message, title = 'Confirm') => {
    if (window.sphereCapture && window.sphereCapture.cardUI) {
        return window.sphereCapture.cardUI.confirm(message, title);
    } else {
        return confirm(message);
    }
};

// OpenCV removed - no longer needed

// Loading screen management
const LoadingScreen = {
    element: null,
    progress: null,
    progressIndeterminate: null,
    status: null,
    substatus: null,
    progressValue: 0,
    
    init() {
        this.element = document.getElementById('app-loading');
        this.progress = document.getElementById('app-progress');
        this.progressIndeterminate = document.getElementById('app-progress-indeterminate');
        this.status = document.getElementById('app-status');
        this.substatus = document.getElementById('app-substatus');
    },
    
    updateProgress(value, status, substatus = null) {
        if (!this.element) return;
        
        if (value >= 0) {
            // Determinate progress
            this.progressValue = Math.min(100, value);
            if (this.progress) {
                this.progress.style.width = `${this.progressValue}%`;
            }
            if (this.progressIndeterminate) {
                this.progressIndeterminate.style.display = 'none';
            }
        } else {
            // Indeterminate progress
            if (this.progressIndeterminate) {
                this.progressIndeterminate.style.display = 'block';
            }
        }
        
        if (status && this.status) {
            this.status.textContent = status;
        }
        
        if (substatus && this.substatus) {
            this.substatus.textContent = substatus;
        }
    },
    
    hide() {
        if (this.element) {
            // Fade out animation
            this.element.style.transition = 'opacity 0.3s ease';
            this.element.style.opacity = '0';
            
            setTimeout(() => {
                if (this.element) {
                    this.element.style.display = 'none';
                }
            }, 300);
        }
    }
};

// Initialize loading screen as soon as possible
LoadingScreen.init();

// Export LoadingScreen globally for app.js to use
window.LoadingScreen = LoadingScreen;

// Service Worker registration disabled - causing 404 errors
// App now works offline without service worker since all files are local
// if ('serviceWorker' in navigator) {
//     // Service worker code commented out for now
// }

// Handle PWA install prompt
window.deferredPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent default install prompt
    e.preventDefault();
    // Store the event for later use
    window.deferredPrompt = e;
    console.log('Install prompt captured');
    
    // Show custom install button if app is initialized
    if (window.sphereCapture && window.sphereCapture.cardUI) {
        window.sphereCapture.cardUI.showInstallButton();
    }
});

// Track PWA installation
window.addEventListener('appinstalled', () => {
    console.log('PWA installed successfully');
    window.deferredPrompt = null;
    
    // Hide install button
    if (window.sphereCapture && window.sphereCapture.cardUI) {
        window.sphereCapture.cardUI.hideInstallButton();
    }
});

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Initializing Photosphere Capture App...');
    LoadingScreen.updateProgress(30, 'Starting application...', 'Setting up interface');
    
    // Ensure grid background is hidden at start (only shows during capture)
    const gridBackground = document.getElementById('grid-background');
    if (gridBackground) {
        gridBackground.style.display = 'none';
        gridBackground.classList.remove('visible');
    }
    
    LoadingScreen.updateProgress(40, 'Creating app instance...', 'Initializing modules');
    
    // Create app instance
    window.sphereCapture = new PhotosphereApp();
    window.app = window.sphereCapture; // Alias for debug tools
    
    LoadingScreen.updateProgress(50, 'Loading components...', 'Setting up 3D scene and database');
    
    // Initialize app
    try {
        await window.sphereCapture.init();
        console.log('App initialized successfully');
        
        LoadingScreen.updateProgress(90, 'Almost ready...', 'Finalizing setup');
        
        // Small delay to ensure everything is rendered
        setTimeout(() => {
            LoadingScreen.updateProgress(100, 'Ready!', '');
            setTimeout(() => {
                LoadingScreen.hide();
            }, 200);
        }, 100);
    } catch (error) {
        console.error('Failed to initialize app:', error);
        LoadingScreen.updateProgress(100, 'Error', 'Failed to initialize');
        await window.showAlert('Failed to initialize app. Please refresh the page.', 'Initialization Error');
    }
});

// Handle page visibility
document.addEventListener('visibilitychange', () => {
    // Don't handle visibility changes during initial permission flow
    // The permission dialogs cause visibility changes that shouldn't trigger camera restarts
    if (window.sphereCapture && window.sphereCapture.isHandlingPermissions) {
        console.log('Ignoring visibility change during permission flow');
        return;
    }
    
    if (document.hidden && window.sphereCapture) {
        // Pause camera when page is hidden
        window.sphereCapture.camera.stop();
    } else if (!document.hidden && window.sphereCapture && window.sphereCapture.isCapturingEnabled) {
        // Only resume camera when page is visible AND we're in capture mode
        window.sphereCapture.camera.startCamera();
    }
});

// Global functions for UI buttons
window.openGallery = async function() {
    if (window.sphereCapture && window.sphereCapture.cameraRoll) {
        await window.sphereCapture.cameraRoll.show();
    } else {
        console.error('Camera roll not initialized');
    }
};

window.openSettings = function() {
    if (window.sphereCapture && window.sphereCapture.cardUI) {
        window.sphereCapture.cardUI.showSettingsMenu();
    } else {
        console.error('CardUI not initialized');
    }
};

window.startApp = async () => {
    // Set flag to prevent visibility handler from interfering during permissions
    if (window.sphereCapture) {
        window.sphereCapture.isHandlingPermissions = true;
    }
    
    // Request device orientation permission FIRST (needs user gesture on iOS)
    if (typeof DeviceOrientationEvent !== 'undefined') {
        if (typeof DeviceOrientationEvent.requestPermission === 'function') {
            // iOS 13+ requires permission and must be done with user gesture
            console.log('Requesting iOS device orientation permission...');
            try {
                const permission = await DeviceOrientationEvent.requestPermission();
                console.log('Device orientation permission result:', permission);
                // Store the permission state for the permissions UI
                localStorage.setItem('motionPermissionState', permission);
                
                if (permission === 'denied') {
                    await window.showAlert('Device orientation access was denied. You can enable it in Settings > Safari > Motion & Orientation Access.', 'Permission Denied');
                    // Continue anyway - app can work without orientation
                }
            } catch (error) {
                console.error('Error requesting device orientation permission:', error);
                // Continue anyway
            }
        } else {
            // Non-iOS devices don't need permission request
            console.log('Device orientation available without permission request');
            localStorage.setItem('motionPermissionState', 'granted');
        }
    }
    
    // Request camera permission second
    if (window.sphereCapture && window.sphereCapture.camera) {
        try {
            console.log('Requesting camera permission...');
            await window.sphereCapture.camera.enumerateCameras();
            // Only start camera if we don't already have a stream
            if (!window.sphereCapture.camera.stream) {
                await window.sphereCapture.camera.startCamera();
                console.log('Camera permission granted and started');
            } else {
                console.log('Camera already started, skipping');
            }
        } catch (error) {
            console.error('Error requesting camera permission:', error);
            await window.showAlert('Camera access is required to capture 360° photos. Please grant camera permission and try again.', 'Camera Required');
            return; // Don't continue if camera permission is denied
        }
    }
    
    // Request geolocation permission
    if ('geolocation' in navigator) {
        console.log('Requesting geolocation permission...');
        try {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: false,  // Changed to false for faster response
                    timeout: 10000,  // Increased timeout
                    maximumAge: 0
                });
            });
            console.log('Geolocation permission granted:', position.coords);
            // Store location in app instance
            if (window.sphereCapture) {
                window.sphereCapture.captureLocation = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    altitude: position.coords.altitude,
                    accuracy: position.coords.accuracy
                };
                // Store permission state for the permissions UI
                localStorage.setItem('locationPermissionState', 'granted');
                console.log('Location permission state saved as granted');
                
                // Update permissions UI if it's open
                if (window.sphereCapture.permissions) {
                    await window.sphereCapture.permissions.checkAllPermissions();
                }
            }
        } catch (error) {
            console.error('Error requesting geolocation permission:', error);
            // Store permission state based on error
            if (error.code === 1) { // PERMISSION_DENIED
                localStorage.setItem('locationPermissionState', 'denied');
                console.log('Location permission state saved as denied');
            }
            // Don't show alert - geolocation is optional
            console.log('Continuing without geolocation data');
        }
    } else {
        console.log('Geolocation not available in this browser');
    }
    
    // Clear the permissions flag now that we're done with permissions
    if (window.sphereCapture) {
        window.sphereCapture.isHandlingPermissions = false;
    }
    
    // Only clear session if there are existing images (continuing a partial capture)
    // Don't clear on first start as it causes the start screen to reappear
    // Don't clear if we're recovering from an interrupted stitch
    if (window.sphereCapture && 
        window.sphereCapture.capturedHotspots && 
        window.sphereCapture.capturedHotspots.size > 0 &&
        !window.sphereCapture.isRecoveringStitch) {
        await window.sphereCapture.clearSession();
        console.log('Cleared previous session for new capture');
    }
    
    // Enable capturing mode FIRST to prevent any other code from showing start screen
    if (window.sphereCapture) {
        window.sphereCapture.setCapturingEnabled(true);
        // Also show capture state in the 3D scene
        if (window.sphereCapture.scene) {
            window.sphereCapture.scene.showCaptureState();
        }
    }
    
    // Hide the start screen properly using the card UI system
    const startScreen = document.getElementById('start-screen');
    if (startScreen && startScreen.classList.contains('visible')) {
        // Animate the start screen away
        startScreen.classList.remove('visible');
        startScreen.classList.add('pressing-down');
        
        setTimeout(() => {
            startScreen.classList.remove('pressing-down');
            startScreen.style.display = 'none'; // Ensure it's fully hidden
        }, 300);
    } else if (startScreen) {
        // Fallback: just hide it if it doesn't have the visible class
        startScreen.style.display = 'none';
    }
    
    // Show the capture UI elements
    document.getElementById('scene-container').style.display = 'block';
    document.getElementById('camera-viewport').style.display = 'block';
    document.getElementById('alignment-indicator').style.display = 'block';
    document.getElementById('instructions').style.display = 'block';
    
    // Show grid background for capture mode
    const gridBackground = document.getElementById('grid-background');
    if (gridBackground) {
        gridBackground.style.display = 'block';
    }
    
    const bottomControls = document.querySelector('.bottom-controls');
    if (bottomControls) {
        bottomControls.style.display = 'flex';
    }
    
    // Start camera if not already started
    if (window.sphereCapture && window.sphereCapture.camera && !window.sphereCapture.camera.stream) {
        await window.sphereCapture.camera.startCamera();
    }
    
    // Enable capturing
    if (window.sphereCapture) {
        window.sphereCapture.setCapturingEnabled(true);
    }
    
    // Show capture UI elements and ensure scene is properly rendered
    if (window.sphereCapture && window.sphereCapture.scene) {
        // Show the hotspots immediately
        window.sphereCapture.scene.showCaptureState();
        // Then ensure proper sizing after DOM updates
        requestAnimationFrame(() => {
            window.sphereCapture.scene.handleResize();
            window.sphereCapture.scene.render();
            console.log('Scene initialized for capture mode');
        });
    }
};

window.manualCapture = async () => {
    if (window.sphereCapture) {
        // If we have any captured images, start stitching
        const capturedCount = window.sphereCapture.capturedHotspots.size;
        if (capturedCount > 0) {
            console.log(`Starting stitch with ${capturedCount} images...`);
            await window.sphereCapture.startStitching();
        } else if (window.sphereCapture.currentHotspot && window.sphereCapture.isAligned) {
            // Otherwise, capture current hotspot if aligned
            window.sphereCapture.handleCapture();
        }
    }
};

window.startStitching = () => {
    if (window.sphereCapture) {
        window.sphereCapture.startStitching();
    }
};

window.goBack = async () => {
    if (await window.showConfirm('Are you sure you want to exit? Any unsaved captures will be lost.', 'Exit App?')) {
        window.history.back();
    }
};

window.stitchExisting = async () => {
    if (!window.sphereCapture) {
        await window.showAlert('App not initialized yet. Please wait.', 'Loading...');
        return;
    }
    
    // Check for existing images in database
    const existingImages = await window.sphereCapture.database.loadCapturedImages();
    
    if (existingImages.length === 0) {
        await window.showAlert('No existing images found in database.', 'No Images');
        return;
    }
    
    // Hide start screen
    const startScreen = document.getElementById('start-screen');
    if (startScreen) {
        startScreen.style.display = 'none';
    }
    
    // Make sure all capture UI elements are visible
    const sceneContainer = document.getElementById('scene-container');
    const cameraViewport = document.getElementById('camera-viewport');
    const alignmentIndicator = document.getElementById('alignment-indicator');
    const instructions = document.getElementById('instructions');
    const bottomControls = document.querySelector('.bottom-controls');
    
    if (sceneContainer) sceneContainer.style.display = 'block';
    if (cameraViewport) cameraViewport.style.display = 'block';
    if (alignmentIndicator) alignmentIndicator.style.display = 'block';
    if (instructions) instructions.style.display = 'block';
    if (bottomControls) bottomControls.style.display = 'flex';
    
    // Load existing images into the app state
    console.log(`Found ${existingImages.length} existing images, loading...`);
    
    // Update captured hotspots set and UI
    existingImages.forEach(data => {
        window.sphereCapture.capturedHotspots.add(data.hotspotId);
        const hotspot = window.sphereCapture.hotspots.find(h => h.id === data.hotspotId);
        if (hotspot && window.sphereCapture.scene) {
            window.sphereCapture.scene.markHotspotCaptured(data.hotspotId);
            window.sphereCapture.scene.addCapturedPatch(hotspot, data.imageData);
        }
    });
    
    // Update progress display
    window.sphereCapture.updateProgress();
    
    // Show the capture UI with existing images loaded
    console.log(`Loaded ${existingImages.length} existing images. Ready to stitch!`);
    
    // User can now see the loaded images and click the stitch button when ready
};

// Export for debugging
window.PhotosphereApp = PhotosphereApp;
