/**
 * ============================================================================
 * MAIN APPLICATION CONTROLLER - VFTCam Photosphere Capture
 * ============================================================================
 * 
 * This is the central orchestrator for the entire 360° photosphere capture app.
 * It coordinates a complex pipeline between device sensors, camera, 3D graphics,
 * and GPU processing to create seamless panoramic images.
 * 
 * THE CAPTURE WORKFLOW:
 * ----------------------
 * 1. User holds phone in portrait orientation
 * 2. App shows 36 orange dots in a sphere around them
 * 3. As they turn, dots turn green when aligned
 * 4. Auto-captures when aligned and level for 1 second
 * 5. After 36 captures, stitches into equirectangular panorama
 * 
 * KEY TECHNICAL CONCEPTS:
 * -----------------------
 * - HOTSPOTS: 36 predefined capture points (3 rows × 12 columns)
 * - ALIGNMENT: Uses 3D angle calculation, not 2D projection
 * - AUTO-CAPTURE: Triggers when aligned (±4.6°) and level (±2°)
 * - BEST-PIXEL: GPU shader selects sharpest pixel from overlaps
 * - PORTRAIT MODE: 9:16 aspect ratio critical for proper coverage
 * 
 * DEPENDENCIES:
 * -------------
 * - Three.js: 3D visualization and camera management
 * - WebRTC: Camera access via getUserMedia
 * - DeviceOrientation/Motion APIs: Phone position tracking
 * - IndexedDB: Local storage for captured images
 * - WebGL2: GPU acceleration for stitching
 * 
 * MEMORY MANAGEMENT:
 * ------------------
 * - iOS: Limited to 720×1280 captures (Safari memory limits)
 * - Android: Full 1080×1920 captures
 * - Smart image loader with 50-image cache
 * - Immediate cleanup after GPU processing
 * 
 * @module app
 * @author Reuben Thiessen
 * @version 1.2.0
 */

// Core module imports
import { Database } from './database.js';
import { Camera } from './camera.js';
import { Scene } from './scene.js';
import { Hotspots } from './hotspots.js';
import { Stitcher } from './stitching.js';
import { SmartImageLoader, MemoryMonitor } from './memory-utils.js';
import { Debug } from './debug.js';
// OpenCV removed - using WebGL2 for all stitching operations
import { metadataUtils } from './metadata-utils.js';
import { CameraRoll } from './camera-roll.js';
import { CardUI } from './card-ui.js';
import { PixelatedTransition } from './pixelated-transition.js';
import { photoSphereSharer } from './share-utils.js';
import { DeviceDetector } from './device-detector.js';
import { fillPolesSimple } from './pole-fill-simple.js';
import { PermissionsManager } from './permissions.js';
import { StitchProcessor } from './stitch-processor.js';
const EQUATOR_PORTRAIT_FOV_DEG = 72; // try 72 first; if still squished, try 70; if stretched, try 75

export class PhotosphereApp {
    
    constructor() {
        // ============================================
        // CORE MODULE INSTANCES
        // ============================================
        
        // Database singleton for IndexedDB operations (stores captured images)
        this.database = new Database();
        
        // Camera module handles WebRTC getUserMedia and image capture
        this.camera = new Camera(this);
        
        // Three.js scene for 3D visualization (initialized after DOM ready)
        this.scene = null;
        
        // WebGL2 stitcher is now the only method
        this.stitcher = new Stitcher();
        
        // Worker-based stitch processor for heavy operations
        this.stitchProcessor = null; // Will be initialized after DOM ready
        
        // Smart memory management - caches up to 50 images to prevent iOS crashes
        this.imageLoader = new SmartImageLoader(50);
        this.memoryMonitor = new MemoryMonitor();
        
        // Debug panel for development/testing
        this.debug = new Debug(this.database);
        
        // UI components (initialized after DOM ready to ensure elements exist)
        this.cameraRoll = null;     // Gallery of saved photospheres
        this.cardUI = null;         // Glass-morphic card interface system
        this.deviceDetector = null; // Mobile device and orientation detection
        this.permissions = null;    // Permissions manager for camera, motion, location
        
        // ============================================
        // CAPTURE STATE MANAGEMENT
        // ============================================
        
        // The 36 predefined capture points arranged in a sphere
        // 3 rows: upper (+45°), equator (0°), lower (-45°)
        // 12 points per row at 30° intervals
        this.hotspots = Hotspots.getHotspots();
        
        // Tracks which of the 36 points have been captured
        this.capturedHotspots = new Set();
        
        // Currently targeted hotspot (null when not near any)
        this.currentHotspot = null;
        
        // True when device is aligned with a hotspot (within ~4.6°)
        this.isAligned = false;
        
        // Timer for auto-capture countdown (fires after 1 second of alignment)
        this.alignmentTimer = null;
        
        // Prevents multiple simultaneous captures
        this.isCapturing = false;
        
        // Master switch - disabled until user clicks "Start Capturing"
        this.isCapturingEnabled = false;
        
        // Flag to track if we're handling permission dialogs
        this.isHandlingPermissions = false;
        
        // Flag to track if we're recovering from an interrupted stitch
        this.isRecoveringStitch = false;
        
        // ============================================
        // DEVICE ORIENTATION TRACKING
        // ============================================
        
        // Current device orientation from DeviceOrientationEvent
        // alpha: rotation around z-axis (compass heading) 0-360°
        // beta: rotation around x-axis (tilt forward/back) -180 to 180°
        // gamma: rotation around y-axis (tilt left/right) -90 to 90°
        this.deviceOrientation = {
            alpha: 0,
            beta: 0,
            gamma: 0,
            absolute: false
        };
        
        // Compass tracking for real-world alignment
        // When absolute is true, alpha is the compass heading (0° = North)
        this.compassOffset = null; // Offset between device orientation and real compass
        this.firstCaptureCompassHeading = null; // Compass heading of first capture
        this.compassUtils = null; // Will be loaded when needed for better iOS support
        
        // Stitch recovery for handling iOS Safari crashes
        this.stitchRecovery = null; // Will be loaded in init()
        this.currentStitchJobId = null; // Track current stitch job
        
        // Smoothing for the roll/level indicator
        // We average the last 10 accelerometer readings to reduce jitter
        this.rollHistory = [];
        this.rollHistorySize = 10;
        
        // Current device tilt from accelerometer (more reliable than gamma)
        this.deviceTilt = 0;
        
        // ============================================
        // DOM ELEMENT REFERENCES
        // ============================================
        
        // Cache of frequently accessed DOM elements (populated in init())
        this.elements = {};
        
        // ============================================
        // CONFIGURATION CONSTANTS
        // ============================================
        
        // How long user must hold position before auto-capture (milliseconds)
        this.CAPTURE_DELAY = 1000;
        
        // ============================================
        // CAPTURE RESOLUTION CONFIGURATION
        // ============================================
        
        // Allow debug override via sessionStorage for testing different resolutions
        const testResolution = sessionStorage.getItem('captureResolution');
        if (testResolution) {
            try {
                this.CAPTURE_RESOLUTION = JSON.parse(testResolution);
                console.log('Using test resolution:', this.CAPTURE_RESOLUTION);
            } catch (e) {
                this.CAPTURE_RESOLUTION = { width: 1080, height: 1920 };
            }
        } else {
            // Portrait orientation (9:16 aspect ratio) is critical for proper overlap
            // The 36-point pattern assumes portrait FOV for adequate coverage
            
            // iOS devices have strict memory limits that cause crashes with large images
            // Safari on iOS will kill the page if memory usage exceeds ~1.4GB
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
                         (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            
            if (isIOS) {
                // 720p portrait for iOS - prevents memory crashes
                // Still produces good quality panoramas
                this.CAPTURE_RESOLUTION = {
                    width: 720,
                    height: 1280
                };
                console.log('Using reduced resolution for iOS:', this.CAPTURE_RESOLUTION);
            } else {
                // 4K portrait for Android/Desktop - maximum quality
                this.CAPTURE_RESOLUTION = {
                    width: 2160,
                    height: 3840
                };
            }
            console.log('Portrait capture resolution:', this.CAPTURE_RESOLUTION);
        }
    }

    /**
     * Initialize the application after DOM is ready
     * This is the main entry point called from capture.js
     * 
     * Initialization sequence:
     * 1. Set up IndexedDB for image storage
     * 2. Cache DOM element references
     * 3. Create Three.js 3D scene
     * 4. Initialize camera (but don't start it yet)
     * 5. Set up UI components
     * 6. Check for mobile device compatibility
     * 7. Start render loop
     * 
     * @async
     * @throws {Error} If critical elements are missing from DOM
     */
    async init() {
        // Update loading progress if available
        if (window.LoadingScreen) {
            window.LoadingScreen.updateProgress(55, 'Initializing database...', 'Setting up storage');
        }
        
        // Initialize IndexedDB first (needed for everything)
        await this.database.init();
        
        if (window.LoadingScreen) {
            window.LoadingScreen.updateProgress(60, 'Loading interface...', 'Preparing elements');
        }
        
        // Cache all DOM elements we'll need to manipulate
        this.elements = {
            sceneContainer: document.getElementById('scene-container'),
            cameraViewport: document.getElementById('camera-viewport'),
            alignmentIndicator: document.getElementById('alignment-indicator'),
            videoFull: document.getElementById('camera-full'),
            captureCanvas: document.getElementById('capture-canvas'),
            captureBtn: document.getElementById('capture-btn-container'),
            captureCheckmark: document.getElementById('captureCheckmark'),
            progressPath: document.getElementById('progressPath'),
            instructions: document.getElementById('instructions'),
            stitchBtn: document.getElementById('stitch-btn'),
            clearBtn: document.getElementById('clear-btn'),
            stitchingOverlay: document.getElementById('stitching-overlay'),
            progressBar: document.getElementById('stitch-progress-bar'),
            statusText: document.getElementById('stitch-status'),
            statusDetails: document.getElementById('stitch-details'),
            previewCanvas: document.getElementById('preview-canvas')
        };
        
        // Create Three.js 3D scene for visualizing capture progress
        // This shows a wireframe sphere with the 36 capture points
        if (!this.elements.sceneContainer) {
            console.error('Scene container not found!');
            throw new Error('Scene container element not found');
        }
        // Initialize 3D scene with error handling
        if (window.LoadingScreen) {
            window.LoadingScreen.updateProgress(65, 'Creating 3D scene...', 'Initializing visualization');
        }
        
        try {
            if (typeof THREE === 'undefined') {
                throw new Error('Three.js not loaded');
            }
            this.scene = new Scene(this.elements.sceneContainer);
        } catch (error) {
            console.error('Failed to initialize 3D scene:', error);
            // Continue without 3D visualization - app can still work
            this.scene = null;
        }
        
        // Initialize camera WITHOUT requesting permissions yet
        // Permissions will be requested when user clicks "Start Capturing"
        if (window.LoadingScreen) {
            window.LoadingScreen.updateProgress(70, 'Setting up camera...', 'Preparing capture system');
        }
        await this.camera.init(this.elements.videoFull, this.elements.captureCanvas, false);
        
        // Initialize UI components
        if (window.LoadingScreen) {
            window.LoadingScreen.updateProgress(75, 'Loading UI components...', 'Creating interface');
        }
        this.cameraRoll = new CameraRoll(this);      // Gallery of saved panoramas
        this.cardUI = new CardUI(this);              // Glass-morphic card system
        this.permissions = new PermissionsManager(this); // Permissions manager
        console.log('Permissions manager initialized:', this.permissions);
        
        // Show warning if private browsing detected
        if (this.database.isPrivateBrowsing) {
            await this.cardUI.alert(
                'Private browsing mode detected. The app may not be able to save images or panoramas. Please use a regular browser window for full functionality.',
                'Private Browsing Warning'
            );
        }
        
        // Initialize stitch processor for worker-based processing
        if (window.LoadingScreen) {
            window.LoadingScreen.updateProgress(78, 'Setting up processor...', 'Initializing stitch engine');
        }
        this.stitchProcessor = new StitchProcessor(this);
        
        // Check for interrupted stitches but DON'T show dialog yet
        // Just save the state for later
        let pendingStitchRecovery = false;
        try {
            const { stitchRecovery } = await import('./stitch-recovery.js');
            this.stitchRecovery = stitchRecovery;
            this.stitchRecovery.init(this, this.database);
            
            // Check if there's an interrupted stitch but don't act on it yet
            const hasInterrupted = await this.stitchRecovery.hasInterruptedStitch();
            if (hasInterrupted) {
                console.log('Interrupted stitch detected, will handle after init');
                pendingStitchRecovery = true;
            }
        } catch (error) {
            console.warn('Stitch recovery check failed:', error);
        }
        
        // Check if device is mobile and show warning if not
        // Also handles orientation detection and locking
        if (window.LoadingScreen) {
            window.LoadingScreen.updateProgress(80, 'Detecting device...', 'Checking capabilities');
        }
        this.deviceDetector = new DeviceDetector(this);
        await this.deviceDetector.init();
        
        // Wire up all event handlers (device orientation, buttons, etc.)
        if (window.LoadingScreen) {
            window.LoadingScreen.updateProgress(85, 'Setting up controls...', 'Binding event handlers');
        }
        this.setupEventListeners();
        
        // Start the Three.js render loop (60fps animation)
        this.animate();
        
        // Hide capture UI elements on start screen (unless we're recovering a stitch)
        if (this.scene && !this.isRecoveringStitch) {
            this.scene.showStartScreenState();
        }
        
        // Enable debug panel if ?debug=true in URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('debug') === 'true') {
            this.debug.enable();
        }
        
        // NOTE: We don't clear previous session on init anymore
        // This allows users to continue a partial capture after reload
        
        console.log('Photosphere app initialized');
        console.log('cardUI initialized:', !!this.cardUI);
        console.log('cameraRoll initialized:', !!this.cameraRoll);
        
        // App initialization complete - progress will be finalized in capture.js
        // Now handle any pending stitch recovery after init is complete
        if (pendingStitchRecovery) {
            // Let the loading screen finish hiding first
            setTimeout(async () => {
                const recoveryAction = await this.stitchRecovery.checkAndRecover();
                if (recoveryAction === 'retry') {
                    console.log('Retrying interrupted stitch');
                    this.isRecoveringStitch = true;
                } else if (recoveryAction === 'discard') {
                    console.log('Discarded interrupted stitch');
                }
            }, 500);
        }
        
        return pendingStitchRecovery;  // Return this so capture.js knows
    }

    /**
     * Set up all event listeners for user interaction and device sensors
     * 
     * Event categories:
     * 1. Device orientation - tracks phone rotation for alignment
     * 2. Device motion - detects if phone is level (for stable capture)
     * 3. Button clicks - capture, clear, stitch operations
     * 4. Debug events - for testing without real capture
     * 5. Keyboard shortcuts - spacebar to capture, Ctrl+S to stitch
     */
    setupEventListeners() {
        // ============================================
        // DEVICE SENSOR EVENTS
        // ============================================
        
        // DeviceOrientationEvent gives us the phone's rotation in 3D space
        // We use this to point the virtual camera at hotspots
        if (window.DeviceOrientationEvent) {
            window.addEventListener('deviceorientation', (e) => this.handleOrientation(e));
        }
        
        // DeviceMotionEvent gives us accelerometer data
        // We use this to detect if the phone is level (not tilted)
        // This is more reliable than using gamma from orientation
        if (window.DeviceMotionEvent) {
            window.addEventListener('devicemotion', (e) => this.handleMotion(e));
        }
        
        if (this.elements.stitchBtn) {
            this.elements.stitchBtn.addEventListener('click', () => this.startStitching());
        } else {
            console.warn('stitchBtn element not found');
        }
        
        if (this.elements.clearBtn) {
            this.elements.clearBtn.addEventListener('click', async () => { 
                console.log('Clear button clicked');
                console.log('cardUI available:', !!this.cardUI);
                
                if(!this.cardUI) {
                    console.error('cardUI not initialized, using fallback confirm');
                    const confirmed = confirm('Clear all captured images and return to start?');
                    if(confirmed) {
                        console.log('User confirmed clear session (fallback)');
                        await this.clearSession();
                    }
                    return;
                }
                
                if(await this.cardUI.confirm('Clear all captured images and return to start?', 'Clear Session?')) {
                    console.log('User confirmed clear session');
                    await this.clearSession();
                } else {
                    console.log('User cancelled clear session');
                }
            });
        } else {
            console.warn('clearBtn element not found');
        }
        // ============================================
        // CAPTURE BUTTON - DUAL PURPOSE
        // ============================================
        // 
        // The capture button (green circle) has two modes:
        // 1. During capture: exit current capture session and start stitching with images captured so far (partial capture)
        // 2. After capture: Start stitching process
        // 
        // The button shows a progress ring that fills as images are captured
        // At 36/36 it turns into a checkmark to indicate ready to stitch
        
        if (this.elements.captureBtn) {
            this.elements.captureBtn.addEventListener('click', async () => {
                console.log('Capture button clicked');
                console.log('cardUI available:', !!this.cardUI);
                const capturedCount = this.capturedHotspots.size;
                console.log('Captured count:', capturedCount);
                
                if (capturedCount > 0) {
                    // We have some images - button should start stitching
                    
                    if (capturedCount < 36) {
                        // Incomplete capture - warn user
                        let proceed = true;
                        if(this.cardUI) {
                            proceed = await this.cardUI.confirm(
                                `You have captured ${capturedCount} out of 36 images.\n\nThe panorama may be incomplete. Do you want to continue to stitching?`,
                                'Incomplete Capture'
                            );
                        } else {
                            console.warn('cardUI not available, using fallback confirm');
                            proceed = confirm(`You have captured ${capturedCount} out of 36 images.\n\nThe panorama may be incomplete. Do you want to continue to stitching?`);
                        }
                        
                        if (!proceed) {
                            // User wants to continue capturing
                            console.log('User chose to continue capturing');
                            this.handleCapture();
                            return;
                        }
                    }
                    
                    // Start the WebGL2 best-pixel stitching pipeline
                    console.log('Starting best pixel stitching');
                    this.startBestPixelStitching();
                } else {
                    // No images yet - try to capture at current position
                    console.log('No images captured yet, attempting manual capture');
                    this.handleCapture();
                }
            });
        } else {
            console.warn('captureBtn element not found');
        }
        
        // Add gallery button event listener
        const galleryBtn = document.getElementById('gallery-btn');
        if (galleryBtn) {
            galleryBtn.addEventListener('click', async (e) => {
                console.log('Gallery button clicked');
                e.preventDefault();
                if (this.cameraRoll) {
                    await this.cameraRoll.show();
                } else {
                    console.error('Camera roll not initialized');
                }
            });
        } else {
            console.warn('gallery-btn element not found');
        }
        // start-btn already has onclick in HTML
        document.getElementById('close-stitch-overlay-btn').addEventListener('click', async () => { 
            // Check if this is a partial capture
            const capturedCount = this.capturedHotspots.size;
            const totalCount = 36;
            
            if (capturedCount > 0 && capturedCount < totalCount) {
                // Partial capture - give user a choice
                const message = `You have captured ${capturedCount} of ${totalCount} images.\n\n` +
                    `Would you like to:\n` +
                    `• Continue capturing (only works well if you're still in the same location)\n` +
                    `• Start over with a new capture session\n\n` +
                    `Choose "Continue" to keep capturing, "Start Over" to begin fresh.`;
                
                const continueCapture = this.cardUI && await this.cardUI.showDialog({
                    title: 'Partial Capture',
                    message: message,
                    type: 'confirm',
                    confirmText: 'Continue',
                    cancelText: 'Start Over'
                });
                
                // Close the overlay with animation
                this.elements.stitchingOverlay.classList.remove('lifting-in');
                this.elements.stitchingOverlay.classList.add('pressing-down');
                setTimeout(() => {
                    this.elements.stitchingOverlay.classList.remove('visible', 'pressing-down');
                }, 300);
                
                if (continueCapture) {
                    // Continue capturing - re-enable capture mode
                    this.setCapturingEnabled(true);
                    
                    // Show the capture UI elements
                    document.getElementById('scene-container').style.display = 'block';
                    document.getElementById('camera-viewport').style.display = 'block';
                    document.getElementById('alignment-indicator').style.display = 'block';
                    document.getElementById('instructions').style.display = 'block';
                    const bottomControls = document.querySelector('.bottom-controls');
                    if (bottomControls) bottomControls.style.display = 'flex';
                    
                    // Show the hotspots and patches
                    if (this.scene) {
                        this.scene.showCaptureState();
                    }
                    
                    // Make sure camera is running
                    if (this.camera && !this.camera.stream) {
                        this.camera.startCamera();
                    }
                } else {
                    // Start over - clear session and show start screen
                    this.clearSession();
                    // Show start screen
                    const startScreen = document.getElementById('start-screen');
                    if (startScreen) {
                        startScreen.style.display = 'block';
                    }
                    // Hide capture UI
                    document.getElementById('scene-container').style.display = 'none';
                    document.getElementById('camera-viewport').style.display = 'none';
                    document.getElementById('alignment-indicator').style.display = 'none';
                    document.getElementById('instructions').style.display = 'none';
                    document.querySelector('.bottom-controls').style.display = 'none';
                }
            } else {
                // Full capture or no images - just close
                this.elements.stitchingOverlay.classList.remove('lifting-in');
                this.elements.stitchingOverlay.classList.add('pressing-down');
                setTimeout(() => {
                    this.elements.stitchingOverlay.classList.remove('visible', 'pressing-down');
                }, 300);
                
                if (capturedCount === totalCount) {
                    // Full capture complete - clear session and go to start
                    this.clearSession();
                    const startScreen = document.getElementById('start-screen');
                    if (startScreen) {
                        startScreen.style.display = 'block';
                    }
                    document.getElementById('scene-container').style.display = 'none';
                    document.getElementById('camera-viewport').style.display = 'none';
                    document.getElementById('alignment-indicator').style.display = 'none';
                    document.getElementById('instructions').style.display = 'none';
                    document.querySelector('.bottom-controls').style.display = 'none';
                }
            }
        });
                
        // ============================================
        // DEBUG PANEL EVENTS
        // ============================================
        
        // Fired when debug panel clears all data
        window.addEventListener('debug-data-cleared', () => {
            this.capturedHotspots.clear();
            this.scene.clearCapturedPatches();
            this.updateProgress();
        });
        
        // Fired when debug panel wants to test stitching
        window.addEventListener('debug-test-stitch', () => {
            this.startStitching();
        });
        
        // ============================================
        // KEYBOARD SHORTCUTS (Desktop Testing)
        // ============================================
        
        document.addEventListener('keydown', (e) => {
            if (e.key === ' ') {
                // Spacebar - manual capture
                e.preventDefault();
                this.handleCapture();
            } else if (e.key === 's' && e.ctrlKey) {
                // Ctrl+S - start stitching
                e.preventDefault();
                this.startStitching();
            }
        });
    }

    // Helper method for screen reader announcements
    announceToScreenReader(message) {
        const announcer = document.getElementById('sr-announcements');
        if (announcer) {
            announcer.textContent = message;
            // Clear after a short delay to allow re-announcement of same message
            setTimeout(() => {
                announcer.textContent = '';
            }, 100);
        }
    }

    async handleOrientation(event) {
        // Store raw device orientation
        this.deviceOrientation = {
            alpha: event.alpha || 0,
            beta: event.beta || 0,
            gamma: event.gamma || 0,
            absolute: event.absolute
        };
        
        // Initialize CompassUtils for better iOS support if not already done
        if (!this.compassUtils) {
            try {
                const { CompassUtils } = await import('./compass-utils.js');
                this.compassUtils = new CompassUtils();
                // Don't auto-start listening here - let permissions card handle it
            } catch (err) {
                console.warn('CompassUtils not available, using basic orientation');
            }
        }
        
        // Check for iOS webkitCompassHeading
        if (typeof event.webkitCompassHeading === 'number' && !isNaN(event.webkitCompassHeading)) {
            // iOS provides true compass heading directly
            this.deviceOrientation.absolute = true;
            this.deviceOrientation.compassHeading = event.webkitCompassHeading;
            
            if (this.compassOffset === null) {
                this.compassOffset = event.webkitCompassHeading;
                console.log(`iOS Compass calibrated: heading=${event.webkitCompassHeading.toFixed(1)}°`);
            }
        } else if (event.absolute && this.compassOffset === null) {
            // Android/desktop with absolute orientation
            this.compassOffset = event.alpha || 0;
            console.log(`Compass calibrated: offset=${this.compassOffset.toFixed(1)}° (absolute orientation available)`);
        }
        
        // Update camera rotation
        if (this.scene) {
            this.scene.updateCameraRotation(
                this.deviceOrientation.alpha,
                this.deviceOrientation.beta,
                this.deviceOrientation.gamma
            );
        }
        
        // Note: Roll indicator is now updated by handleMotion using accelerometer
        
        // Check alignment with hotspots
        this.checkAlignment();
    }
    
    handleMotion(event) {
        // Use accelerometer data for more reliable tilt detection
        const accel = event.accelerationIncludingGravity;
        if (!accel) return;
        
        // When phone is upright (portrait mode):
        // x-axis: left/right tilt (what we need for roll)
        // y-axis: gravity pulls down (~-9.8 when upright)
        // z-axis: forward/backward tilt
        
        // Calculate tilt angle from x-axis acceleration
        // Gravity is ~9.8 m/s², x component indicates tilt
        // sin(angle) = x / 9.8
        const tiltAngle = Math.asin(Math.max(-1, Math.min(1, accel.x / 9.8))) * (180 / Math.PI);
        
        // Update roll indicator with accelerometer-based tilt
        this.updateRollIndicator(tiltAngle);
    }
    
    updateRollIndicator(tiltAngle) {
        const rollLine = document.getElementById('roll-line-moving');
        if (!rollLine) return;
        
        // Add to history for smoothing
        this.rollHistory.push(tiltAngle);
        if (this.rollHistory.length > this.rollHistorySize) {
            this.rollHistory.shift();
        }
        
        // Calculate smoothed average
        const smoothedTilt = this.rollHistory.reduce((a, b) => a + b, 0) / this.rollHistory.length;
        this.deviceTilt = smoothedTilt;
        
        // Apply counter-rotation to keep line level with horizon
        const displayRotation = -smoothedTilt;
        rollLine.style.transform = `translateY(-50%) rotate(${displayRotation}deg)`;
        
        // Change color based on how level the device is
        const isLevel = Math.abs(smoothedTilt) < 2; // Within 2 degrees
        const isTilted = Math.abs(smoothedTilt) > 5; // More than 5 degrees
        
        rollLine.classList.toggle('level', isLevel);
        rollLine.classList.toggle('tilted', isTilted && !isLevel);
    }
    
    /**
     * Check if device is level enough for capture
     * 
     * We require the phone to be within 2 degrees of level
     * This ensures sharp, properly aligned captures
     * Uses accelerometer data which is more reliable than gyro
     * 
     * @returns {boolean} True if device tilt is within acceptable range
     */
    isDeviceLevel() {
        // 2 degree tolerance in radians (tight for quality)
        return Math.abs(this.deviceTilt) < 2;
    }
    
    normalizeAngleDelta(delta) {
        // Normalize angle difference to -180 to 180 range
        while (delta > 180) delta -= 360;
        while (delta < -180) delta += 360;
        return delta;
    }

    checkAlignment() {
        // Don't check alignment if capturing is disabled
        if (!this.isCapturingEnabled) {
            return;
        }
        
        // Get camera direction vector
        const cameraDirection = new THREE.Vector3(0, 0, -1);
        cameraDirection.applyQuaternion(this.scene.camera.quaternion);
        
        let nearestHotspot = null;
        let nearestDistance = Infinity;
        
        // Find nearest uncaptured hotspot using 3D angle
        this.hotspots.forEach(hotspot => {
            if (!this.capturedHotspots.has(hotspot.id)) {
                const hotspotPosition = this.scene.hotspotToPosition(hotspot.yaw, hotspot.pitch);
                const direction = hotspotPosition.clone().normalize();
                const angle = cameraDirection.angleTo(direction);
                
                if (angle < nearestDistance) {
                    nearestDistance = angle;
                    nearestHotspot = hotspot;
                }
            }
        });
        
        // Update alignment state
        // APPROACH_THRESHOLD - when to show the indicator (orange)
        // ALIGNMENT_THRESHOLD - when to allow capture (green)
        const APPROACH_THRESHOLD = 0.25; // ~14 degrees - show indicator
        const ALIGNMENT_THRESHOLD = 0.08; // ~4.6 degrees - much tighter for proper overlap
        
        if (nearestHotspot && nearestDistance < APPROACH_THRESHOLD) {
            this.currentHotspot = nearestHotspot;
            this.isAligned = nearestDistance < ALIGNMENT_THRESHOLD;
            
            // Update visual feedback
            if (this.scene) {
                this.scene.highlightHotspot(this.currentHotspot.id, this.isAligned);
            }
            
            // Show alignment indicator
            this.elements.alignmentIndicator.classList.add('visible');
            
            if (this.isAligned) {
                this.elements.alignmentIndicator.classList.add('aligned');
                this.elements.alignmentIndicator.classList.remove('approaching');
                
                if (!this.capturedHotspots.has(this.currentHotspot.id)) {
                    // Check if device is level
                    const isDeviceLevel = this.isDeviceLevel();
                    
                    if (!isDeviceLevel) {
                        this.elements.instructions.textContent = 'Level your device';
                        this.clearAlignmentTimer();
                    } else {
                        this.elements.instructions.textContent = 'Hold steady...';
                        
                        // Start auto-capture timer if not already started and device is level
                        if (!this.alignmentTimer && !this.isCapturing) {
                            // Add countdown visual feedback
                            let countdown = this.CAPTURE_DELAY / 1000;
                            const countdownInterval = setInterval(() => {
                                countdown--;
                                if (countdown > 0) {
                                    // Check if still level during countdown
                                    if (!this.isDeviceLevel()) {
                                        this.elements.instructions.textContent = 'Level your device';
                                        clearInterval(countdownInterval);
                                        this.clearAlignmentTimer();
                                    } else {
                                        this.elements.instructions.textContent = `Capturing in ${countdown}...`;
                                    }
                                } else {
                                    clearInterval(countdownInterval);
                                }
                            }, 1000);
                            
                            this.alignmentTimer = setTimeout(() => {
                                clearInterval(countdownInterval);
                                if (this.isAligned && this.currentHotspot && 
                                    this.currentHotspot.id === nearestHotspot.id &&
                                    !this.capturedHotspots.has(this.currentHotspot.id) &&
                                    this.isDeviceLevel()) {
                                    this.handleCapture();
                                }
                            }, this.CAPTURE_DELAY);
                        }
                    }
                } else {
                    this.elements.instructions.textContent = 'Already captured';
                }
            } else {
                this.elements.alignmentIndicator.classList.add('approaching');
                this.elements.alignmentIndicator.classList.remove('aligned');
                this.elements.instructions.textContent = 'Almost there...';
                
                // Update color based on proximity
                const proximity = 1 - (nearestDistance / APPROACH_THRESHOLD);
                const orange = Math.floor(165 * proximity);
                this.elements.alignmentIndicator.style.borderColor = `rgb(255, ${orange}, 0)`;
                
                // Clear timer if we lose alignment
                this.clearAlignmentTimer();
            }
        } else {
            this.currentHotspot = null;
            this.isAligned = false;
            
            // Clear timer if we move away
            this.clearAlignmentTimer();
            
            // Reset all hotspots when none are near
            if (this.scene) {
                this.scene.resetAllHotspots();
                
                // Hide markers for captured hotspots
                this.capturedHotspots.forEach(hotspotId => {
                    this.scene.markHotspotCaptured(hotspotId);
                });
            }
            
            // Hide alignment indicator when not near a hotspot
            this.elements.alignmentIndicator.classList.remove('visible', 'aligned', 'approaching');
            
            const captured = this.capturedHotspots.size;
            if (captured === 0) {
                this.elements.instructions.textContent = 'Align to start';
            } else {
                this.elements.instructions.textContent = `${captured} / ${Hotspots.TOTAL_HOTSPOTS} captured`;
            }
        }
    }

    clearAlignmentTimer() {
        if (this.alignmentTimer) {
            clearTimeout(this.alignmentTimer);
            this.alignmentTimer = null;
        }
    }

    async handleCapture() {
        console.log('handleCapture called', { isAligned: this.isAligned, currentHotspot: this.currentHotspot });
        
        // Don't capture if start screen is visible
        const startScreen = document.getElementById('start-screen');
        if (startScreen && startScreen.style.display !== 'none') {
            console.log('Start screen is visible, capture disabled');
            return;
        }
        
        if (!this.isAligned || !this.currentHotspot || this.isCapturing) {
            console.log('Not aligned with a hotspot or already capturing');
            return;
        }
        
        if (this.capturedHotspots.has(this.currentHotspot.id)) {
            console.log('Hotspot already captured');
            return;
        }
        
        console.log('Capturing photo for hotspot', this.currentHotspot.id);
        
        this.isCapturing = true;
        this.clearAlignmentTimer();
        
        try {
            // Capture photo with reduced resolution
            const imageBlob = await this.camera.capturePhoto(
                this.CAPTURE_RESOLUTION.width,
                this.CAPTURE_RESOLUTION.height
            );
            
            // Capture current device orientation for precise alignment
            // Get the actual camera direction from the 3D scene
            const cameraDirection = new THREE.Vector3(0, 0, -1);
            cameraDirection.applyQuaternion(this.scene.camera.quaternion);
            
            // Convert camera direction to spherical coordinates (yaw and pitch)
            // Math.atan2 returns angle in radians from -PI to PI
            let actualYaw = Math.atan2(cameraDirection.x, cameraDirection.z) * 180 / Math.PI;
            // Normalize to 0-360 range
            if (actualYaw < 0) actualYaw += 360;
            
            // Calculate pitch from the y component
            const horizontalLength = Math.sqrt(cameraDirection.x * cameraDirection.x + cameraDirection.z * cameraDirection.z);
            const actualPitch = Math.atan2(cameraDirection.y, horizontalLength) * 180 / Math.PI;
            
            // Track compass heading of first capture for metadata
            let compassYaw = actualYaw;
            
            // Handle iOS compass heading
            if (this.deviceOrientation.compassHeading !== undefined) {
                // iOS provides true compass heading directly
                compassYaw = this.deviceOrientation.compassHeading;
                
                if (this.capturedHotspots.size === 0) {
                    this.firstCaptureCompassHeading = compassYaw;
                    console.log(`First capture iOS compass heading: ${compassYaw.toFixed(1)}° (will be used for InitialViewHeadingDegrees)`);
                }
                console.log(`Capture ${this.capturedHotspots.size + 1}: iOS compassYaw=${compassYaw.toFixed(1)}°`);
            } 
            // Handle Android/desktop absolute orientation
            else if (this.deviceOrientation.absolute && this.compassOffset !== null) {
                // Adjust yaw to be relative to true north instead of device start position
                compassYaw = (actualYaw + this.compassOffset) % 360;
                
                if (this.capturedHotspots.size === 0) {
                    this.firstCaptureCompassHeading = compassYaw;
                    console.log(`First capture compass heading: ${compassYaw.toFixed(1)}° (will be used for InitialViewHeadingDegrees)`);
                }
                console.log(`Capture ${this.capturedHotspots.size + 1}: compassYaw=${compassYaw.toFixed(1)}° (device yaw=${actualYaw.toFixed(1)}° + offset=${this.compassOffset.toFixed(1)}°)`);
            } 
            // No compass data available
            else {
                console.log(`Capture ${this.capturedHotspots.size + 1}: No compass data available (using device-relative yaw=${actualYaw.toFixed(1)}°)`);
            }
            
            // Calculate the delta between expected and actual orientation
            // This captures how the user's actual position differs from the ideal hotspot
            const yawDelta = this.normalizeAngleDelta(actualYaw - this.currentHotspot.yaw);
            const pitchDelta = actualPitch - this.currentHotspot.pitch;
            
            // Save to database with orientation data
            const hotspotData = {
                hotspotId: this.currentHotspot.id,
                imageBlob: imageBlob,
                // Expected/ideal position
                yaw: this.currentHotspot.yaw,
                pitch: this.currentHotspot.pitch,
                // Actual captured orientation
                actualYaw: actualYaw,
                actualPitch: actualPitch,
                compassYaw: compassYaw, // Real-world compass heading
                hasCompass: this.deviceOrientation.absolute, // Track if we have absolute orientation
                // Deltas for fine-tuning placement
                yawDelta: yawDelta,
                pitchDelta: pitchDelta,
                // Device tilt at capture (from accelerometer)
                roll: this.deviceTilt,
                // Field of view
                fov: Hotspots.calculateFOV(this.currentHotspot.pitch),
                timestamp: new Date().toISOString()
            };
            
            try {
                await this.database.saveImage(hotspotData);
            } catch (error) {
                console.error('Failed to save image:', error);
                if (this.cardUI) {
                    await this.cardUI.alert(
                        `Failed to save image: ${error.message}`,
                        'Storage Error'
                    );
                }
                return;
            }
            
            // Update UI
            this.capturedHotspots.add(this.currentHotspot.id);
            if (this.scene) {
                this.scene.markHotspotCaptured(this.currentHotspot.id);
                // Create blob URL for display (will be cleaned up when scene resets)
                const blobUrl = URL.createObjectURL(imageBlob);
                this.scene.addCapturedPatch(this.currentHotspot, blobUrl);
            }
            
            // Update progress
            this.updateProgress();
            
            console.log('Photo captured successfully for hotspot', this.currentHotspot.id);
            
            // Reset capture state after a delay
            setTimeout(() => {
                this.isCapturing = false;
            }, 500);
            
        } catch (error) {
            console.error('Capture error:', error);
            if (this.cardUI) {
                await this.cardUI.alert('Failed to capture photo', 'Capture Error');
            }
            this.isCapturing = false;
        }
    }

    /**
     * Update all progress indicators after capture state changes
     * 
     * Updates:
     * - Progress ring (fills as images are captured)
     * - Checkmark color (orange -> green when complete)
     * - Instruction text ("12/36 captured")
     * - Screen reader announcements at milestones
     * 
     * Called after each successful capture or when session is cleared
     */
    updateProgress() {
        const captured = this.capturedHotspots.size;
        const total = 36; // 36 capture points
        const progress = (captured / total) * 100;
        
        // Update counter
        // this.elements.progressCounter.textContent = `${captured} / ${total}`;
        
        // Update progress circle using stroke-dashoffset
        const strokeDashoffset = 100 - progress;
        this.elements.progressPath.style.strokeDashoffset = strokeDashoffset;
        
        // Update checkmark color and instructions based on completion
        if (captured === total) {
            // All 36 captured - turn checkmark green
            this.elements.captureCheckmark.style.fill = '#4CAF50';
            this.elements.instructions.textContent = 'Capture complete! Ready to stitch';
            this.announceToScreenReader('Capture complete! All 36 images captured. Ready to stitch panorama.');
        } else if (captured > 0) {
            // In progress - checkmark stays orange
            this.elements.captureCheckmark.style.fill = 'orange';
            this.elements.instructions.textContent = `${captured} / ${total} captured`;
            // Announce progress at key milestones
            if (captured === 1) {
                this.announceToScreenReader(`First image captured. ${captured} of ${total} complete.`);
            } else if (captured % 6 === 0 || captured === total - 1) {
                // Announce every 6 images and when nearly complete
                this.announceToScreenReader(`${captured} of ${total} images captured.`);
            }
        } else {
            // No captures yet - reset instructions
            this.elements.captureCheckmark.style.fill = 'orange';
            this.elements.instructions.textContent = 'Align with hotspots to capture';
        }
    }

    setCapturingEnabled(enabled) {
        // Note: isCapturing is managed by handleCapture() directly, not by this flag
        this.isCapturingEnabled = enabled;
        this.elements.captureBtn.disabled = !enabled;
    }

    async startStitching() {
        // OpenCV removed - proceeding with WebGL2 stitching
        
        this.isCapturing = false; // Reset any in-progress capture
        this.setCapturingEnabled(false);
        // Show stitching overlay with lift in animation
        this.elements.stitchingOverlay.classList.remove('pressing-down');
        this.elements.stitchingOverlay.classList.add('lifting-in', 'visible');
        setTimeout(() => {
            this.elements.stitchingOverlay.classList.remove('lifting-in');
        }, 250);
        this.elements.progressBar.style.width = '0%';
        this.elements.statusText.textContent = 'Loading captured images...';
        this.elements.statusDetails.textContent = 'Retrieving from IndexedDB...';
        
        try {
            // Load images from database
            const capturedData = await this.database.loadCapturedImages();
            
            if (capturedData.length < 2) {
                if (this.cardUI) {
                    await this.cardUI.alert('Need at least 2 images to create a photosphere', 'Not Enough Images');
                }
                // Close with press down animation
                this.elements.stitchingOverlay.classList.remove('lifting-in');
                this.elements.stitchingOverlay.classList.add('pressing-down');
                setTimeout(() => {
                    this.elements.stitchingOverlay.classList.remove('visible', 'pressing-down');
                }, 200);
                this.setCapturingEnabled(true);
                return;
            }
            
            console.log(`Starting stitch with ${capturedData.length} images`);
            
            // Convert database objects to cv.Mat objects and metadata
            const imageMats = [];
            const imageMetadata = [];
            
            for (const capture of capturedData) {
                // Load image as Mat
                const img = new Image();
                await new Promise((resolve, reject) => {
                    img.onload = resolve;
                    img.onerror = reject;
                    img.src = capture.imageData;
                });
                
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const mat = matFromImageData(imageData);
                imageMats.push(mat);
                
                // Extract metadata
                imageMetadata.push({
                    hotspotId: capture.hotspotId,
                    pitch: capture.pitch || 0,
                    yaw: capture.yaw || 0,
                    fov: capture.fov || 40
                });
            }

            const result = await this.stitcher.stitch(imageMats, imageMetadata, (progress, status) => {
                this.elements.progressBar.style.width = `${progress * 100}%`;
                this.elements.statusText.textContent = status;
            });

            if (result && !result.empty()) {
                const canvas = document.createElement('canvas');
                cv.imshow(canvas, result);
                
                // Clean up the result Mat
                result.delete();

                this.showStitchResult(canvas);
                
                // Create JPEG blob for storage
                const panoramaBlob = await new Promise(resolve => 
                    canvas.toBlob(resolve, 'image/jpeg', 0.9)
                );
                
                const panoramaData = {
                    imageBlob: panoramaBlob,
                    timestamp: new Date().toISOString(),
                    imageCount: capturedData.length,
                    type: 'equirectangular',
                    width: canvas.width,
                    height: canvas.height
                };
                
                await this.database.savePanorama(panoramaData);
                console.log('Panorama saved to database');
                
                // Upload to server so it appears in admin AI Tools page
                const _uploadResult1 = await this.uploadPanoramaToServer(panoramaBlob, canvas.width, canvas.height, capturedData.length);
                // Redirect to admin page with flash message after successful upload
                if (_uploadResult1.success) {
                    console.log('Panorama uploaded successfully - redirecting to admin page');
                    setTimeout(() => {
                        window.location.href = '../admin/institution/ai.php?flash_type=success&flash_message=' + encodeURIComponent('✅ Panorama saved to system! You can now download or attach it to a tour scene from the AI Tools page in the admin panel.');
                    }, 1000);
                } else {
                    console.log('Upload failed:', _uploadResult1.error);
                }
                
                // Mark stitch job as complete if we have one
                if (this.stitchRecovery && this.currentStitchJobId) {
                    await this.stitchRecovery.markJobCompleted(this.currentStitchJobId);
                    console.log(`Marked stitch job ${this.currentStitchJobId} as complete`);
                    this.currentStitchJobId = null; // Clear the job ID
                }
                
                this.elements.progressBar.style.width = '100%';
                this.elements.statusText.textContent = 'Photosphere Complete!';
                this.elements.statusDetails.textContent = `Successfully stitched ${capturedData.length} images`;
                
                // Announce completion to screen readers
                this.announceToScreenReader(`Panorama processing complete. Successfully stitched ${capturedData.length} images. View and share buttons are now available.`);
            } else {
                throw new Error('Stitching failed - no result returned');
            }
            
        } catch (error) {
            console.error('Stitching error:', error);
            if (this.cardUI) {
                // Sanitize error message to prevent XSS
                const safeMessage = String(error.message).replace(/[<>]/g, '');
                await this.cardUI.alert('Stitching failed: ' + safeMessage, 'Stitching Error');
            }
            this.setCapturingEnabled(true);
        }
        
        this.elements.progressBar.style.width = '100%';
        this.elements.statusText.textContent = 'Done!';
    }

    showStitchResult(canvas) {
        console.log('showStitchResult called with canvas:', canvas.width, 'x', canvas.height);
        
        // Make sure the overlay is visible
        const overlay = document.getElementById('stitching-overlay');
        if (overlay.style.display === 'none') {
            console.log('Making overlay visible');
            overlay.style.display = 'flex';
        }
        
        // Update preview
        const ctx = this.elements.previewCanvas.getContext('2d');
        console.log('Preview canvas size:', this.elements.previewCanvas.width, 'x', this.elements.previewCanvas.height);
        
        const scale = Math.min(
            this.elements.previewCanvas.width / canvas.width,
            this.elements.previewCanvas.height / canvas.height
        );
        
        const width = canvas.width * scale;
        const height = canvas.height * scale;
        const x = (this.elements.previewCanvas.width - width) / 2;
        const y = (this.elements.previewCanvas.height - height) / 2;
        
        console.log('Drawing at:', x, y, 'size:', width, 'x', height);
        
        ctx.clearRect(0, 0, this.elements.previewCanvas.width, this.elements.previewCanvas.height);
        ctx.drawImage(canvas, x, y, width, height);
        console.log('Preview updated');
        
        document.getElementById('close-stitch-overlay-btn').addEventListener('click', () => { 
            // Close with press down animation
            this.elements.stitchingOverlay.classList.remove('lifting-in');
            this.elements.stitchingOverlay.classList.add('pressing-down');
            setTimeout(() => {
                this.elements.stitchingOverlay.classList.remove('visible', 'pressing-down');
            }, 300); 
            this.setCapturingEnabled(true);
        });
        
        // Add download button
        const downloadBtn = document.getElementById('download-btn');
        if (downloadBtn) {
            downloadBtn.onclick = async () => {
                try {
                    // Convert canvas to blob
                    const blob = await new Promise(resolve => 
                        canvas.toBlob(resolve, 'image/jpeg', 0.9)
                    );
                    
                    // Generate timestamp filename
                    const now = new Date();
                    const timestamp = now.toISOString().replace(/[:.]/g, '-').slice(0, -5);
                    const filename = `photosphere_${timestamp}.jpg`;
                    
                    // Use share API
                    await photoSphereSharer.sharePhotosphere(blob, filename);
                } catch (error) {
                    console.error('Share failed:', error);
                }
            };
        }
        
        // Add view button for Pannellum
        const viewBtn = document.getElementById('view-btn');
        if (viewBtn) {
            viewBtn.onclick = () => {
                // Pass the first capture compass heading for proper orientation
                this.viewPanorama(canvas, this.firstCaptureCompassHeading);
            };
        }
    }

    async viewPanorama(canvas, compassHeading = null) {
        console.log(`Opening panorama viewer with compass heading: ${compassHeading !== null ? compassHeading.toFixed(1) + '°' : 'none'}`);
        
        // Convert canvas to blob URL
        canvas.toBlob((blob) => {
            const imageUrl = URL.createObjectURL(blob);
            
            // Calculate actual panorama coverage
            const expectedWidth = canvas.height * 2; // 2:1 ratio for full 360
            const horizontalCoverage = Math.min((canvas.width / expectedWidth) * 360, 360);
            const verticalCoverage = 180; // Assume full vertical coverage
            
            console.log(`Panorama coverage: ${horizontalCoverage.toFixed(1)}° horizontal`);
            
            // Create viewer container
            const viewerContainer = document.createElement('div');
            viewerContainer.id = 'panorama-viewer';
            viewerContainer.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 2000;
                background: black;
            `;
            
            // Add close button matching card style
            const closeBtn = document.createElement('button');
            closeBtn.className = 'card-close-btn';
            closeBtn.innerHTML = '<img src="./img/x.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt="Close">';
            closeBtn.style.cssText = `
                position: absolute;
                top: max(15px, calc(15px + env(safe-area-inset-top, 0px)));
                right: max(15px, env(safe-area-inset-right, 15px));
                width: 40px;
                height: 40px;
                border-radius: 50%;
                border: 1px solid rgba(255, 255, 255, 0.3);
                background: rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                color: white;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2001;
            `;
            
            closeBtn.onclick = () => {
                viewerContainer.remove();
                URL.revokeObjectURL(imageUrl);
            };
            
            viewerContainer.appendChild(closeBtn);
            document.body.appendChild(viewerContainer);
            
            // Configure Pannellum for partial panoramas
            const config = {
                type: 'equirectangular',
                panorama: imageUrl,
                autoLoad: true,
                // Disable auto rotation for partial panos
                autoRotate: horizontalCoverage > 300 ? -2 : 0,
                showFullscreenCtrl: true,
                mouseZoom: true,
                keyboardZoom: true,
                showZoomCtrl: true,
                showControls: true,
                // Field of view settings
                hfov: Math.min(horizontalCoverage * 0.8, 100), // Start zoomed appropriately
                minHfov: 30,
                maxHfov: 120,
                // No pitch/yaw limits
                minPitch: -90,
                maxPitch: 90,
                // For partial panoramas, limit the yaw range
                minYaw: horizontalCoverage < 360 ? -(horizontalCoverage / 2) : -180,
                maxYaw: horizontalCoverage < 360 ? (horizontalCoverage / 2) : 180,
                // Orient the view based on compass heading
                // If we have a compass heading, use it to set the initial view
                // The yaw in Pannellum is relative to the image center, not compass north
                // So we need to use northOffset to align the panorama with real-world directions
                pitch: 0,
                yaw: 0, // Start looking at the same direction as first capture
                // UI settings
                compass: horizontalCoverage > 300 && compassHeading !== null,
                // northOffset tells Pannellum how to align the compass
                // It's the angle from image center to north
                // If first capture was facing north (0°), northOffset should be 0
                // If first capture was facing east (90°), northOffset should be -90
                northOffset: compassHeading !== null ? -compassHeading : 0,
                // Background for missing areas
                backgroundColor: [0, 0, 0],
                // Performance
                disableKeyboardCtrl: false,
                friction: 0.15,
                // For partial panoramas, don't wrap around
                haov: horizontalCoverage,
                vaov: verticalCoverage,
                // Tell Pannellum this is a partial panorama
                ignoreGPanoXMP: true
            };
            
            // Add info overlay for partial panoramas
            if (horizontalCoverage < 360) {
                const infoOverlay = document.createElement('div');
                infoOverlay.style.cssText = `
                    position: absolute;
                    bottom: 20px;
                    left: 20px;
                    background: rgba(0,0,0,0.7);
                    color: white;
                    padding: 10px 15px;
                    border-radius: 5px;
                    font-size: 14px;
                `;
                infoOverlay.textContent = `Partial panorama: ${horizontalCoverage.toFixed(0)}° coverage`;
                viewerContainer.appendChild(infoOverlay);
            }
            
            // Initialize Pannellum
            pannellum.viewer('panorama-viewer', config);
            
            console.log('Pannellum initialized with config:', config);
        }, 'image/jpeg', 0.9);
    }

    /**
     * Reset capture and return to start screen
     * Called from the "Start New Capture" button in stitching overlay
     * 
     * @async
     */
    async resetAndStartNewCapture() {
        // Close the stitching overlay
        if (this.elements.stitchingOverlay) {
            this.elements.stitchingOverlay.classList.remove('lifting-in');
            this.elements.stitchingOverlay.classList.add('pressing-down');
            setTimeout(() => {
                this.elements.stitchingOverlay.classList.remove('visible', 'pressing-down');
            }, 300);
        }
        
        // Clear the session
        await this.clearSession();
        
        // Show start screen with visible class for proper animation
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
        
        // Hide grid background
        const gridBackground = document.getElementById('grid-background');
        if (gridBackground) {
            gridBackground.style.display = 'none';
            gridBackground.classList.remove('visible');
        }
    }

    /**
     * Clear all captured images and reset to initial state
     * 
     * This method:
     * - Deletes all images from IndexedDB
     * - Clears the 3D visualization
     * - Resets progress indicators
     * - Returns to start screen
     * 
     * Called when user taps the reset button
     * 
     * @async
     */
    /**
     * Start stitching with specific data (used for recovery)
     */
    async startBestPixelStitchingWithData(capturedData, jobId = null) {
        // Set the job ID if provided (for recovery)
        if (jobId) {
            this.currentStitchJobId = jobId;
        }
        
        // Show stitching overlay
        this.elements.stitchingOverlay.classList.remove('pressing-down');
        this.elements.stitchingOverlay.classList.add('lifting-in', 'visible');
        this.elements.stitchingOverlay.removeAttribute('aria-hidden');
        setTimeout(() => {
            this.elements.stitchingOverlay.classList.remove('lifting-in');
        }, 250);
        
        this.elements.progressBar.style.width = '0%';
        this.elements.statusText.textContent = 'Resuming stitch...';
        this.elements.statusDetails.textContent = `Processing ${capturedData.length} images...`;
        
        // Lock the processing card UI
        this.lockProcessingCard();
        
        // Hide capture UI
        document.getElementById('scene-container').style.display = 'none';
        document.getElementById('camera-viewport').style.display = 'none';
        document.getElementById('alignment-indicator').style.display = 'none';
        document.getElementById('instructions').style.display = 'none';
        const bottomControls = document.querySelector('.bottom-controls');
        if (bottomControls) {
            bottomControls.style.display = 'none';
        }
        
        // Perform the stitching
        await this.performBestPixelStitching(capturedData);
    }
    

    /**
     * Upload the stitched equirectangular panorama to the server.
     * Saves it to the institution's assets/panos/ folder and the
     * panoramas table so it appears on the AI Tools admin page.
     *
     * @param {Blob}   blob       - JPEG blob of the panorama
     * @param {number} width      - canvas width
     * @param {number} height     - canvas height
     * @param {number} imageCount - number of source captures
     */

    /**
     * Show a card-style success (or warning) modal after auto-syncing the
     * stitched panorama to the server.
     * @param {{ success: boolean, error?: string }} result
     */
    _showSyncSuccessModal(result) {
        if (!this.cardUI) return;
        if (!result) return;
        if (result.success) {
            this.cardUI.alert(
                '✅ Panorama saved to system!\n\nYou can now download or attach it to a tour scene from the AI Tools page in the admin panel.',
                'Synced to System'
            );
        } else {
            // Non-fatal warning — tell user how to retry manually
            this.cardUI.alert(
                `⚠️ Auto-sync failed: ${result.error || 'Unknown error'}\n\nYour panorama is saved locally. Open the Camera Roll and tap the upload (cloud) icon to retry.`,
                'Sync Skipped'
            );
        }
    }

    async uploadPanoramaToServer(blob, width, height, imageCount) {
        try {
            const apiUrl = '../api/save-panorama.php';
            const formData = new FormData();
            const filename = `panorama_${Date.now()}.jpg`;
            formData.append('panorama', blob, filename);
            formData.append('title', `360° Panorama – ${new Date().toLocaleString()}`);
            formData.append('description', `Captured with 360 Camera App (${imageCount} images)`);
            formData.append('capture_data', JSON.stringify({
                imageCount,
                width,
                height,
                capturedAt: new Date().toISOString()
            }));

            console.log('Uploading panorama to server…');
            const res = await fetch(apiUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (json.success) {
                console.log('Panorama uploaded to server successfully:', json);
                return { success: true, data: json };
            } else {
                console.warn('Server upload failed:', json.error);
                return { success: false, error: json.error };
            }
        } catch (err) {
            // Non-fatal — panorama is still saved locally in IndexedDB
            console.warn('Could not upload panorama to server (non-fatal):', err.message);
            return { success: false, error: err.message };
        }
    }

    async clearSession() {
        await this.database.clearAllImages();
        this.capturedHotspots.clear();
        if (this.scene) {
            this.scene.clearCapturedPatches();
            this.scene.resetAllHotspots();
        }
        
        // Reset compass tracking for new session
        this.compassOffset = null;
        this.firstCaptureCompassHeading = null;
        
        // Reset progress circle and checkmark if UI is ready
        if (this.elements) {
            if (this.elements.progressPath) {
                this.elements.progressPath.style.strokeDashoffset = '100';
            }
            if (this.elements.captureCheckmark) {
                this.elements.captureCheckmark.style.fill = 'orange';
            }
        }
        
        this.updateProgress();
        console.log('Cleared previous capture session');
        
        // Stop camera and disable capturing
        if (this.camera) {
            this.camera.stop();
        }
        this.isCapturing = false; // Reset any in-progress capture
        this.setCapturingEnabled(false);
        
        // Hide capture UI elements
        if (this.scene) {
            this.scene.showStartScreenState();
        }
        
        // Hide all capture UI elements
        const sceneContainer = document.getElementById('scene-container');
        const cameraViewport = document.getElementById('camera-viewport');
        const alignmentIndicator = document.getElementById('alignment-indicator');
        const instructions = document.getElementById('instructions');
        const bottomControls = document.querySelector('.bottom-controls');
        const gridBackground = document.getElementById('grid-background');
        
        if (sceneContainer) sceneContainer.style.display = 'none';
        if (cameraViewport) cameraViewport.style.display = 'none';
        if (alignmentIndicator) alignmentIndicator.style.display = 'none';
        if (instructions) instructions.style.display = 'none';
        if (bottomControls) bottomControls.style.display = 'none';
        if (gridBackground) {
            gridBackground.style.display = 'none';
            gridBackground.classList.remove('visible');
        }
        
        // Show start screen with proper animation
        const startScreen = document.getElementById('start-screen');
        if (startScreen) {
            startScreen.style.display = 'block';
            // Force reflow to ensure display change is applied
            startScreen.offsetHeight;
            // Add visible class for animation
            startScreen.classList.add('visible');
            startScreen.classList.remove('pressing-down');
        }
    }
    
    /**
     * Reset capture session and return to start screen
     * Used when leaving capture mode for camera roll
     */
    async resetCapture() {
        // Clear session already handles returning to start screen
        await this.clearSession();
    }

    async stitchExisting() {
        // Check for existing images in database
        const existingImages = await this.database.loadCapturedImages();
        
        if (existingImages.length === 0) {
            if (this.cardUI) {
                await this.cardUI.alert('No existing images found in database.', 'No Images');
            }
            return;
        }
        
        // Hide start screen
        const startScreen = document.getElementById('start-screen');
        if (startScreen) {
            startScreen.style.display = 'none';
        }
        
        // Load existing images into the app state
        console.log(`Found ${existingImages.length} existing images, loading...`);
        
        // Update captured hotspots set and UI
        existingImages.forEach(data => {
            this.capturedHotspots.add(data.hotspotId);
            const hotspot = this.hotspots.find(h => h.id === data.hotspotId);
            if (hotspot && this.scene) {
                this.scene.markHotspotCaptured(data.hotspotId);
                this.scene.addCapturedPatch(hotspot, data.imageData);
            }
        });
        
        // Update progress display
        this.updateProgress();
        
        // Show capture UI elements
        if (this.scene) {
            this.scene.showCaptureState();
        }
        
        // Enable capturing and start camera
        this.setCapturingEnabled(true);
        await this.camera.startCamera();
        
        // Show the capture UI with existing images loaded
        console.log(`Loaded ${existingImages.length} existing images. Ready to stitch!`);
    }

    async startBestPixelStitching() {
        this.isCapturing = false; // Reset any in-progress capture
        this.setCapturingEnabled(false);
        
        // Announce to screen readers
        this.announceToScreenReader('Starting panorama processing. Please wait.');
        
        // Hide capture UI elements when showing processor card
        document.getElementById('scene-container').style.display = 'none';
        document.getElementById('camera-viewport').style.display = 'none';
        document.getElementById('alignment-indicator').style.display = 'none';
        document.getElementById('instructions').style.display = 'none';
        const bottomControls = document.querySelector('.bottom-controls');
        if (bottomControls) {
            bottomControls.style.display = 'none';
        }
        
        // Show stitching overlay with lift in animation
        this.elements.stitchingOverlay.classList.remove('pressing-down');
        this.elements.stitchingOverlay.classList.add('lifting-in', 'visible');
        this.elements.stitchingOverlay.removeAttribute('aria-hidden');
        setTimeout(() => {
            this.elements.stitchingOverlay.classList.remove('lifting-in');
        }, 250);
        this.elements.progressBar.style.width = '0%';
        this.elements.statusText.textContent = 'Loading captured images...';
        this.elements.statusDetails.textContent = 'Retrieving from IndexedDB...';
        
        // Lock the processing card UI
        this.lockProcessingCard();
        
        try {
            // Load captured images
            const capturedData = await this.database.loadCapturedImages();
            
            if (capturedData.length === 0) {
                throw new Error('No captured images found');
            }
            
            // Create stitch job for recovery tracking
            if (this.stitchRecovery) {
                const imageIds = capturedData.map(img => String(img.hotspotId));
                const params = {
                    method: 'best-pixel',
                    imageCount: capturedData.length,
                    timestamp: new Date().toISOString()
                };
                this.currentStitchJobId = await this.stitchRecovery.createStitchJob(imageIds, params);
                console.log(`Created stitch job: ${this.currentStitchJobId}`);
            }
            
            // Continue with stitching
            await this.performBestPixelStitching(capturedData);
            
        } catch (error) {
            console.error('Stitching failed:', error);
            
            // Mark job as failed if we have one
            if (this.stitchRecovery && this.currentStitchJobId) {
                await this.stitchRecovery.markJobFailed(this.currentStitchJobId, error);
            }
            
            // Show error to user
            this.elements.statusText.textContent = 'Stitching Failed';
            this.elements.statusDetails.textContent = error.message;
            this.unlockProcessingCard();
            
            if (this.cardUI) {
                await this.cardUI.alert(
                    `Failed to create panorama: ${error.message}`,
                    'Stitching Error'
                );
            }
        }
    }
    
    /**
     * Perform the actual stitching (separated for recovery)
     */
    async performBestPixelStitching(capturedData) {
        try {
            if (!capturedData || capturedData.length === 0) {
                throw new Error('No captured images found');
            }
            
            console.log(`Starting best-pixel stitch with ${capturedData.length} images`);
            this.elements.statusDetails.textContent = `Processing ${capturedData.length} images...`;
            
            // Mark job as started if we have one
            if (this.stitchRecovery && this.currentStitchJobId) {
                await this.stitchRecovery.markJobStarted(this.currentStitchJobId);
            }
            
            // Set up progress callbacks for the stitch processor
            this.stitchProcessor.onProgress = (percent, message) => {
                // First 50% is for image processing
                const progress = percent * 0.5;
                this.elements.progressBar.style.width = `${progress}%`;
                if (message) {
                    this.elements.statusDetails.textContent = message;
                }
            };
            
            this.stitchProcessor.onStatus = (status) => {
                this.elements.statusText.textContent = status;
            };
            
            // Process images using worker (or fallback to main thread)
            this.elements.statusText.textContent = 'Processing images...';
            const layers = await this.stitchProcessor.process(capturedData);
            
            console.log(`Received ${layers.length} processed layers from worker`);
            
            // Update status for WebGL rendering
            this.elements.statusText.textContent = 'Rendering sharp panorama...';
            this.elements.statusDetails.textContent = 'Using best-pixel selection...';
            this.elements.progressBar.style.width = '50%';
            
            // Create equirectangular canvas for WebGL rendering
            const erpCanvas = document.createElement('canvas');
            erpCanvas.width = 4096;
            erpCanvas.height = 2048;
            
            // Initialize WebGL2 for best-pixel selection
            const gl = erpCanvas.getContext('webgl2', {
                premultipliedAlpha: false,
                preserveDrawingBuffer: true,
                failIfMajorPerformanceCaveat: false // Don't fail on software rendering
            });
            
            if (!gl) {
                throw new Error('WebGL2 not supported.');
            }
            
            // Handle context loss
            let contextLost = false;
            erpCanvas.addEventListener('webglcontextlost', (e) => {
                e.preventDefault();
                contextLost = true;
                console.error('WebGL context lost during stitching');
                this.elements.statusDetails.textContent = 'GPU error - retrying...';
            }, false);
            
            erpCanvas.addEventListener('webglcontextrestored', () => {
                contextLost = false;
                console.log('WebGL context restored');
            }, false);
            
            // Convert layers from worker (with bitmaps/canvases) to WebGL-ready format
            // The worker returns layers with either bitmap or canvas properties
            const webglLayers = [];
            for (const layer of layers) {
                if (layer.bitmap) {
                    // Convert ImageBitmap to canvas for WebGL
                    const canvas = document.createElement('canvas');
                    canvas.width = layer.width || this.CAPTURE_RESOLUTION.width;
                    canvas.height = layer.height || this.CAPTURE_RESOLUTION.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(layer.bitmap, 0, 0);
                    layer.bitmap.close(); // Clean up bitmap
                    
                    webglLayers.push({
                        name: `hotspot_${layer.hotspotId}`,
                        hotspotId: layer.hotspotId,  // Include hotspotId for gain compensation
                        yaw: layer.yaw,
                        pitch: layer.pitch,
                        roll: layer.roll,
                        canvas: canvas
                    });
                } else if (layer.canvas) {
                    // Already have a canvas from main thread fallback
                    webglLayers.push({
                        name: `hotspot_${layer.hotspotId}`,
                        hotspotId: layer.hotspotId,  // Include hotspotId for gain compensation
                        yaw: layer.yaw,
                        pitch: layer.pitch,
                        roll: layer.roll,
                        canvas: layer.canvas
                    });
                }
            }
            
            // Sort layers for consistent ordering
            webglLayers.sort((a, b) => (b.pitch - a.pitch) || (a.yaw - b.yaw));
            
            console.log(`Rendering best-pixel panorama with ${webglLayers.length} valid images...`);
            this.elements.statusDetails.textContent = `Combining ${webglLayers.length} images using best-pixel selection...`;
            
            await this.renderBestPixelPanorama(gl, erpCanvas, webglLayers);
            
            // Apply multi-band blending for smoother seams
            // DISABLED - Current implementation reduces contrast too much
            const skipMultiband = true;
            if (!skipMultiband) {
            try {
                const blendedCanvas = await this.applyMultiBandBlending(gl, erpCanvas, webglLayers);
                if (blendedCanvas && blendedCanvas !== erpCanvas) {
                    // The blended canvas is a 2D canvas with the result already drawn
                    // We need to copy it back to the WebGL canvas (erpCanvas)
                    console.log('Copying multi-band result back to WebGL canvas');
                    
                    // Create texture from the 2D canvas
                    const texture = gl.createTexture();
                    gl.bindTexture(gl.TEXTURE_2D, texture);
                    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, blendedCanvas);
                    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
                    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
                    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
                    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
                    
                    // Simple copy shader to draw texture to erpCanvas
                    const copyVs = `#version 300 es
                    layout(location=0) in vec2 pos;
                    out vec2 v_uv;
                    void main() { v_uv = 0.5 * pos + 0.5; gl_Position = vec4(pos, 0, 1); }`;
                    
                    const copyFs = `#version 300 es
                    precision highp float;
                    in vec2 v_uv;
                    out vec4 frag;
                    uniform sampler2D tex;
                    void main() { 
                        frag = texture(tex, vec2(v_uv.x, 1.0 - v_uv.y));
                    }`; // Flip Y for correct orientation
                    
                    const copyProg = this.compileProgram(gl, copyVs, copyFs);
                    if (copyProg) {
                        gl.useProgram(copyProg);
                        
                        // Set texture
                        gl.activeTexture(gl.TEXTURE0);
                        gl.bindTexture(gl.TEXTURE_2D, texture);
                        gl.uniform1i(gl.getUniformLocation(copyProg, 'tex'), 0);
                        
                        // Create and bind VAO/VBO
                        const vao = gl.createVertexArray();
                        gl.bindVertexArray(vao);
                        const vbo = gl.createBuffer();
                        gl.bindBuffer(gl.ARRAY_BUFFER, vbo);
                        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 3,-1, -1,3]), gl.STATIC_DRAW);
                        gl.enableVertexAttribArray(0);
                        gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
                        
                        // Clear and draw to default framebuffer
                        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
                        gl.viewport(0, 0, erpCanvas.width, erpCanvas.height);
                        gl.clearColor(0, 0, 0, 1);
                        gl.clear(gl.COLOR_BUFFER_BIT);
                        gl.drawArrays(gl.TRIANGLES, 0, 3);
                        
                        // Cleanup
                        gl.deleteProgram(copyProg);
                        gl.deleteBuffer(vbo);
                        gl.deleteVertexArray(vao);
                    } else {
                        console.error('Failed to compile copy shader for multi-band result');
                    }
                    
                    gl.deleteTexture(texture);
                }
            } catch (e) {
                console.warn('Multi-band blending failed:', e);
                // Continue with the existing result in erpCanvas
            }
            }
            
            // Force cleanup of layer canvases before pole fill to free memory
            webglLayers.forEach(layer => {
                if (layer.canvas) {
                    layer.canvas.width = 0;
                    layer.canvas.height = 0;
                    delete layer.canvas;
                }
            });
            
            // Force garbage collection if available (non-standard but helps if supported)
            if (window.gc) {
                window.gc();
            }
            
            // Small delay to allow memory cleanup
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Apply pole filling to remove black circles at top and bottom
            try {
                fillPolesSimple(gl, erpCanvas);
                console.log('Pole filling applied successfully');
            } catch (poleFillError) {
                console.error('Pole filling failed, continuing without it:', poleFillError);
                // Continue without pole filling if it fails
            }
            
            // CRITICAL: Extract the result from WebGL canvas to a 2D canvas
            // WebGL canvas can't be directly used with drawImage or toBlob reliably
            const finalCanvas = document.createElement('canvas');
            finalCanvas.width = erpCanvas.width;
            finalCanvas.height = erpCanvas.height;
            const finalCtx = finalCanvas.getContext('2d');
            
            // Ensure we're reading from the default framebuffer and flush operations
            gl.bindFramebuffer(gl.FRAMEBUFFER, null);
            gl.flush();
            gl.finish(); // Force all commands to complete
            
            // Add a small delay to ensure rendering is complete
            await new Promise(resolve => setTimeout(resolve, 10));
            
            // Read pixels from WebGL canvas
            const pixels = new Uint8Array(erpCanvas.width * erpCanvas.height * 4);
            gl.bindFramebuffer(gl.FRAMEBUFFER, null); // Make absolutely sure
            gl.readPixels(0, 0, erpCanvas.width, erpCanvas.height, gl.RGBA, gl.UNSIGNED_BYTE, pixels);
            
            
            
            // Create a new Uint8ClampedArray for the ImageData (avoids readonly issues)
            const flippedPixels = new Uint8ClampedArray(erpCanvas.width * erpCanvas.height * 4);
            
            // Flip Y-axis (WebGL has opposite Y orientation)
            for (let y = 0; y < erpCanvas.height; y++) {
                for (let x = 0; x < erpCanvas.width; x++) {
                    const srcIdx = (y * erpCanvas.width + x) * 4;
                    const dstIdx = ((erpCanvas.height - 1 - y) * erpCanvas.width + x) * 4;
                    flippedPixels[dstIdx] = pixels[srcIdx];
                    flippedPixels[dstIdx + 1] = pixels[srcIdx + 1];
                    flippedPixels[dstIdx + 2] = pixels[srcIdx + 2];
                    flippedPixels[dstIdx + 3] = pixels[srcIdx + 3];
                }
            }
            
            // Create ImageData with the flipped pixels
            const imageData = new ImageData(flippedPixels, erpCanvas.width, erpCanvas.height);
            finalCtx.putImageData(imageData, 0, 0);
            
            // Progress is at 50% after stitching completes
            this.elements.progressBar.style.width = '50%';
            
            // Store the FINAL 2D canvas for view/download buttons
            this.lastStitchedPanorama = finalCanvas;
            
            // Start pixelated transition from thumbnail to final panorama
            this.elements.statusText.textContent = 'Rendering panorama...';
            this.elements.statusDetails.textContent = 'Finalizing high-resolution output...';
            
            // Get thumbnail path from canvas attribute
            const thumbnailPath = this.elements.previewCanvas.getAttribute('data-thumbnail') || './img/360_blank_thumbnail.jpg';
            
            // Create a scaled version of the stitched panorama for the preview canvas
            const scaledCanvas = document.createElement('canvas');
            scaledCanvas.width = this.elements.previewCanvas.width;
            scaledCanvas.height = this.elements.previewCanvas.height;
            const scaledCtx = scaledCanvas.getContext('2d');
            
            // Calculate scaling to fit preview canvas (use finalCanvas dimensions)
            const scale = Math.min(
                this.elements.previewCanvas.width / finalCanvas.width,
                this.elements.previewCanvas.height / finalCanvas.height
            );
            const width = finalCanvas.width * scale;
            const height = finalCanvas.height * scale;
            const x = (this.elements.previewCanvas.width - width) / 2;
            const y = (this.elements.previewCanvas.height - height) / 2;
            
            // Draw scaled panorama to temporary canvas (use finalCanvas)
            scaledCtx.fillStyle = '#000';
            scaledCtx.fillRect(0, 0, scaledCanvas.width, scaledCanvas.height);
            scaledCtx.drawImage(finalCanvas, x, y, width, height);
            
            // Create and start pixelated transition
            const transition = new PixelatedTransition(
                this.elements.previewCanvas,
                thumbnailPath,
                scaledCanvas,
                1000, // 1 second
                16 // 16x16 pixel blocks
            );
            
            // Update progress bar during transition
            transition.setProgressCallback((progress) => {
                // Transition runs from 50% to 100% of total progress
                const totalProgress = 50 + (progress * 50);
                this.elements.progressBar.style.width = `${totalProgress}%`;
                
                if (progress >= 1) {
                    this.elements.statusText.textContent = 'Panorama complete!';
                    this.elements.statusDetails.textContent = `Successfully stitched ${layers.length} images`;
                }
            });
            
            // Make preview canvas visible and start transition
            this.elements.previewCanvas.style.display = 'block';
            await transition.start();
            
            // Gather metadata for the panorama
            const timestamps = capturedData.map(d => new Date(d.timestamp || Date.now()));
            const firstPhotoDate = timestamps.reduce((min, d) => d < min ? d : min);
            const lastPhotoDate = timestamps.reduce((max, d) => d > max ? d : max);
            
            // Get first captured image heading (yaw) - based on earliest timestamp
            const sortedByTime = [...capturedData].sort((a, b) => 
                new Date(a.timestamp || 0) - new Date(b.timestamp || 0)
            );
            const firstImage = sortedByTime[0];
            // Use compass-aligned yaw if available for initial view heading
            const firstImageYaw = firstImage ? (firstImage.compassYaw || firstImage.actualYaw || firstImage.yaw) : 0;
            const hasCompassData = firstImage && firstImage.hasCompass;
            
            if (hasCompassData) {
                console.log(`Using compass-aligned panorama: InitialViewHeadingDegrees=${firstImageYaw.toFixed(1)}°`);
            } else {
                console.log('No compass data available - using device-relative orientation');
            }
            
            // Prepare metadata
            const metadata = {
                width: finalCanvas.width,
                height: finalCanvas.height,
                captureDate: new Date(),
                firstPhotoDate: firstPhotoDate,
                lastPhotoDate: lastPhotoDate,
                sourcePhotosCount: capturedData.length,
                // Use compass heading if available, this aligns the panorama to real-world directions
                // When viewing the panorama, looking at this heading will show what was actually
                // in that compass direction when captured
                initialHeading: hasCompassData ? firstImageYaw : 0,
                poseHeading: hasCompassData ? firstImageYaw : 0,
                hasCompass: hasCompassData,
                // GPS location if available
                latitude: this.captureLocation?.latitude,
                longitude: this.captureLocation?.longitude,
                altitude: this.captureLocation?.altitude
            };
            
            // Create JPEG blob with metadata
            let panoramaBlob;
            
            try {
                // First create a plain blob
                const plainBlob = await new Promise(resolve => 
                    finalCanvas.toBlob(resolve, 'image/jpeg', 1.0)
                );
                
                // Then add metadata to it
                const dataUrl = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(plainBlob);
                });
                
                panoramaBlob = await metadataUtils.addMetadataToJPEG(dataUrl, metadata);
                console.log('Successfully added metadata to panorama');
            } catch (metadataError) {
                console.error('Error adding metadata:', metadataError);
                // Fall back to simple blob without metadata
                panoramaBlob = await new Promise(resolve => 
                    finalCanvas.toBlob(resolve, 'image/jpeg', 1.0)
                );
            }
            
            // Save panorama blob to database for camera roll
            const panoramaData = {
                imageBlob: panoramaBlob,
                timestamp: new Date().toISOString(),
                imageCount: capturedData.length,
                type: 'best-pixel',
                width: finalCanvas.width,
                height: finalCanvas.height,
                hasMetadata: true,
                metadata: metadata // Store metadata separately for later use
            };
            
            try {
                await this.database.savePanorama(panoramaData);
                console.log('Panorama saved to database');
                
                // Upload to server so it appears in admin AI Tools page
                const _uploadResult2 = await this.uploadPanoramaToServer(
                    panoramaData.imageBlob,
                    panoramaData.width,
                    panoramaData.height,
                    capturedData ? capturedData.length : (panoramaData.imageCount || 0)
                );
                // Redirect to admin page with flash message after successful upload
                if (_uploadResult2.success) {
                    console.log('Panorama uploaded successfully - redirecting to admin page');
                    setTimeout(() => {
                        window.location.href = '../admin/institution/ai.php?flash_type=success&flash_message=' + encodeURIComponent('✅ Panorama saved to system! You can now download or attach it to a tour scene from the AI Tools page in the admin panel.');
                    }, 1000);
                } else {
                    console.log('Upload failed:', _uploadResult2.error);
                }
                
                // Mark stitch job as complete if we have one
                if (this.stitchRecovery && this.currentStitchJobId) {
                    await this.stitchRecovery.markJobCompleted(this.currentStitchJobId);
                    console.log(`Marked stitch job ${this.currentStitchJobId} as complete`);
                    this.currentStitchJobId = null; // Clear the job ID
                }
            } catch (error) {
                console.error('Failed to save panorama:', error);
                if (this.cardUI) {
                    await this.cardUI.alert(
                        `Failed to save panorama: ${error.message}`,
                        'Storage Error'
                    );
                }
                throw error; // Re-throw to handle in calling code
            }
            
            // Set up view button
            const viewBtn = document.getElementById('view-btn');
            if (viewBtn) {
                viewBtn.onclick = () => {
                    // Pass the first capture compass heading for proper orientation
                    this.viewPanorama(finalCanvas, this.firstCaptureCompassHeading);
                };
            }
            
            // Set up download button with metadata
            const downloadBtn = document.getElementById('download-btn');
            if (downloadBtn) {
                downloadBtn.onclick = async () => {
                    try {
                        // Generate timestamp filename matching the saved panorama
                        const now = new Date();
                        const year = now.getFullYear();
                        const month = String(now.getMonth() + 1).padStart(2, '0');
                        const day = String(now.getDate()).padStart(2, '0');
                        const hours = String(now.getHours()).padStart(2, '0');
                        const minutes = String(now.getMinutes()).padStart(2, '0');
                        const seconds = String(now.getSeconds()).padStart(2, '0');
                        const filename = `photosphere_${year}${month}${day}_${hours}${minutes}${seconds}.jpg`;
                        
                        // Use share API with the blob that has metadata
                        await photoSphereSharer.sharePhotosphere(panoramaBlob, filename);
                    } catch (error) {
                        console.error('Share failed:', error);
                    }
                };
            }
            
            // Unlock UI after successful processing
            this.unlockProcessingCard();
            
        } catch (error) {
            console.error('Best-pixel stitching failed:', error);
            
            // Mark job as failed if we have one
            if (this.stitchRecovery && this.currentStitchJobId) {
                await this.stitchRecovery.markJobFailed(this.currentStitchJobId, error);
            }
            
            // Unlock UI on error
            this.unlockProcessingCard();
            
            this.elements.statusText.textContent = 'Stitching failed';
            
            // Handle different error types
            let errorMessage = 'Unknown error';
            let userFriendlyMessage = '';
            
            if (error instanceof Error) {
                errorMessage = error.message;
                
                // Provide user-friendly messages for common errors
                if (errorMessage.includes('cancelled')) {
                    // Processing was cancelled - already handled
                    return;
                } else if (errorMessage.includes('timeout')) {
                    userFriendlyMessage = 'Processing took too long. Try with fewer images or refresh the page.';
                } else if (errorMessage.includes('memory')) {
                    userFriendlyMessage = 'Not enough memory available. Close other apps and try again.';
                } else if (errorMessage.includes('WebGL2')) {
                    userFriendlyMessage = 'Your browser does not support WebGL2. Please update your browser.';
                } else if (errorMessage.includes('No captured images')) {
                    userFriendlyMessage = 'No images found. Please capture images first.';
                } else if (errorMessage.includes('Worker')) {
                    userFriendlyMessage = 'Image processing failed. The app will retry with an alternative method.';
                } else {
                    userFriendlyMessage = `Processing failed: ${errorMessage}`;
                }
            } else if (error instanceof Event) {
                errorMessage = 'Failed to load image data';
                userFriendlyMessage = 'Could not load images. Please try again.';
            } else if (typeof error === 'string') {
                errorMessage = error;
                userFriendlyMessage = error;
            }
            
            this.elements.statusDetails.textContent = errorMessage;
            
            // Show error to user
            if (this.cardUI) {
                await this.cardUI.alert(
                    userFriendlyMessage,
                    'Processing Error'
                );
            }
            
            // Offer recovery options
            if (errorMessage.includes('memory') || errorMessage.includes('timeout')) {
                const retry = await this.cardUI.confirm(
                    'Would you like to try again with reduced quality settings?',
                    'Retry Options'
                );
                
                if (retry) {
                    // Set emergency mode flag and retry
                    this.stitchProcessor.metrics.emergencyMode = true;
                    await this.startBestPixelStitching();
                    return;
                }
            }
            
            // Return to capture mode
            this.elements.stitchingOverlay.classList.add('pressing-down');
            setTimeout(() => {
                this.elements.stitchingOverlay.classList.remove('visible', 'pressing-down');
            }, 300);
            
            this.setCapturingEnabled(true);
            document.getElementById('scene-container').style.display = 'block';
            document.getElementById('camera-viewport').style.display = 'block';
            document.getElementById('alignment-indicator').style.display = 'block';
            document.getElementById('instructions').style.display = 'block';
            const bottomControls = document.querySelector('.bottom-controls');
            if (bottomControls) {
                bottomControls.style.display = 'flex';
            }
        }
    }
    
    async startEnhancedStitching() {
        console.log('Starting enhanced stitching with OpenCV for equator band...');
        this.isCapturing = false; // Reset any in-progress capture
        this.setCapturingEnabled(false);
        
        // Show stitching overlay
        this.elements.stitchingOverlay.classList.remove('pressing-down');
        this.elements.stitchingOverlay.classList.add('lifting-in', 'visible');
        setTimeout(() => {
            this.elements.stitchingOverlay.classList.remove('lifting-in');
        }, 250);
        this.elements.progressBar.style.width = '0%';
        this.elements.statusText.textContent = 'Starting enhanced stitching...';
        this.elements.statusDetails.textContent = 'Loading images from database...';
        
        try {
            // Load all captured images
            const capturedData = await this.database.loadCapturedImages();
            
            if (capturedData.length < 2) {
                if (this.cardUI) {
                    await this.cardUI.alert('Need at least 2 images to create a photosphere', 'Not Enough Images');
                }
                this.elements.stitchingOverlay.classList.remove('lifting-in');
                this.elements.stitchingOverlay.classList.add('pressing-down');
                setTimeout(() => {
                    this.elements.stitchingOverlay.classList.remove('visible', 'pressing-down');
                }, 200);
                this.setCapturingEnabled(true);
                return;
            }
            
            console.log(`Enhanced stitching with ${capturedData.length} images`);
            
            // Separate images into bands
            const equatorImages = [];  // pitch ≈ 0 (hotspots 13-24)
            const upperImages = [];    // pitch ≈ +45 (hotspots 1-12)
            const lowerImages = [];    // pitch ≈ -45 (hotspots 25-36)
            
            for (const capture of capturedData) {
                if (capture.hotspotId >= 13 && capture.hotspotId <= 24) {
                    equatorImages.push(capture);
                } else if (capture.hotspotId >= 1 && capture.hotspotId <= 12) {
                    upperImages.push(capture);
                } else if (capture.hotspotId >= 25 && capture.hotspotId <= 36) {
                    lowerImages.push(capture);
                }
            }
            
            console.log(`Separated: ${equatorImages.length} equator, ${upperImages.length} upper, ${lowerImages.length} lower`);
            
            // Load OpenCV if not already loaded
            if (typeof cv === 'undefined') {
                this.elements.statusText.textContent = 'Loading OpenCV...';
                this.elements.statusDetails.textContent = 'This may take a moment...';
                await this.loadOpenCV();
            }
            
            // Create the final ERP canvas with WebGL for rendering
            const erpCanvas = document.createElement('canvas');
            erpCanvas.width = 4096;
            erpCanvas.height = 2048;
            const gl = erpCanvas.getContext('webgl2', { 
                preserveDrawingBuffer: true,
                premultipliedAlpha: false,
                antialias: false
            });
            
            if (!gl) {
                throw new Error('WebGL2 not supported');
            }
            
            // Step 1: Stitch equator band with OpenCV if we have equator images
            let equatorStitched = null;
            let matchedIndices = [];
            
            if (equatorImages.length > 0) {
                this.elements.statusText.textContent = 'Stitching equator band...';
                this.elements.statusDetails.textContent = `Processing ${equatorImages.length} equator images with OpenCV...`;
                this.elements.progressBar.style.width = '20%';
                
                const result = await this.stitchEquatorWithOpenCV(equatorImages);
                equatorStitched = result.canvas;
                matchedIndices = result.matchedIndices;
                
                console.log(`OpenCV matched ${matchedIndices.length} of ${equatorImages.length} equator images`);
            }
            
            // OpenCV outputs exactly 360° when given 13 images (12 + duplicate)
            // The width of 5221px / 360° = 14.5 px/deg confirms this
            if (equatorStitched) {
                console.log('✅ OpenCV output is already 360°!');
                console.log(`OpenCV result: ${equatorStitched.width}x${equatorStitched.height}`);
                console.log(`Pixels/degree: ${(equatorStitched.width / 360).toFixed(1)}`);
                
                // Just scale to target width - no extraction needed!
                const targetWidth = 4096;
                const aspectRatio = equatorStitched.height / equatorStitched.width;
                const targetHeight = Math.round(targetWidth * aspectRatio);
                
                const scaledCanvas = document.createElement('canvas');
                scaledCanvas.width = targetWidth;
                scaledCanvas.height = targetHeight;
                const ctx = scaledCanvas.getContext('2d');
                
                // Simple scale - OpenCV already gave us exactly 360°
                ctx.drawImage(equatorStitched, 0, 0, targetWidth, targetHeight);
                
                equatorStitched = scaledCanvas;
                console.log(`Scaled from ${equatorStitched.width}x${equatorStitched.height} to ${targetWidth}x${targetHeight}`);
                
                // DEBUG: Show just the scaled equator band
                this.lastStitchedPanorama = equatorStitched;
                await this.displayStitchResult(equatorStitched);
                
                this.elements.statusText.textContent = 'Debug: Showing scaled OpenCV output';
                this.elements.statusDetails.textContent = `${targetWidth}x${targetHeight} - Full 360° panorama`;
                this.elements.progressBar.style.width = '100%';
                
                return; // Exit early for debugging
            }
            
            // Step 3: Add upper and lower bands with best-pixel approach
            this.elements.statusText.textContent = 'Adding upper and lower bands...';
            this.elements.statusDetails.textContent = 'Using best-pixel selection for remaining images...';
            this.elements.progressBar.style.width = '60%';
            
            // Combine all non-equator images and unmatched equator images
            const remainingImages = [...upperImages, ...lowerImages];
            
            // Add unmatched equator images to be rendered with best-pixel
            for (let i = 0; i < equatorImages.length; i++) {
                if (!matchedIndices.includes(i)) {
                    remainingImages.push(equatorImages[i]);
                    console.log(`Adding unmatched equator image ${equatorImages[i].hotspotId} to best-pixel rendering`);
                }
            }
            
            if (remainingImages.length > 0) {
                // Prepare layers for best-pixel rendering
                const layers = await this.prepareLayersForRendering(remainingImages);
                
                // Render remaining bands with best-pixel (but preserve equator area if we have one)
                await this.renderBestPixelWithMask(gl, erpCanvas, layers, equatorStitched !== null);
            }
            
            // Step 3b: Overlay the equator band using WebGL shader with linear longitude mapping
            if (equatorStitched) {
                this.elements.statusText.textContent = 'Placing equator band...';
                this.elements.statusDetails.textContent = 'Linear longitude mapping...';
                
                // Render equator band directly with WebGL using linear longitude mapping
                await this.renderEquatorBandLinear(gl, erpCanvas, equatorStitched);
            }
            
            // Step 4: Apply pole filling
            this.elements.statusText.textContent = 'Filling poles...';
            this.elements.statusDetails.textContent = 'Removing black regions at top and bottom...';
            this.elements.progressBar.style.width = '80%';
            
            try {
                const { fillPolesSimple } = await import('./pole-fill-simple.js');
                fillPolesSimple(gl, erpCanvas);
                console.log('Pole filling applied successfully');
            } catch (poleFillError) {
                console.error('Pole filling failed:', poleFillError);
            }
            
            // Complete!
            this.elements.progressBar.style.width = '100%';
            this.lastStitchedPanorama = erpCanvas;
            
            // Show result
            await this.displayStitchResult(erpCanvas);
            
        } catch (error) {
            console.error('Enhanced stitching error:', error);
            if (this.cardUI) {
                // Sanitize error message to prevent XSS
                const safeMessage = String(error.message).replace(/[<>]/g, '');
                await this.cardUI.alert('Enhanced stitching failed: ' + safeMessage, 'Stitching Error');
            }
            
            // Close overlay
            this.elements.stitchingOverlay.classList.remove('lifting-in');
            this.elements.stitchingOverlay.classList.add('pressing-down');
            setTimeout(() => {
                this.elements.stitchingOverlay.classList.remove('visible', 'pressing-down');
            }, 200);
            this.setCapturingEnabled(true);
        }
    }
    
    async loadOpenCV() {
        return new Promise((resolve, reject) => {
            if (typeof cv !== 'undefined') {
                resolve();
                return;
            }
            
            // Configure Module before loading
            window.Module = {
                onRuntimeInitialized: function() {
                    console.log('OpenCV.js loaded and initialized');
                    resolve();
                }
            };
            
            const script = document.createElement('script');
            script.src = 'js/opencv_3_4_custom_O3.js';
            script.onerror = () => reject(new Error('Failed to load OpenCV'));
            document.head.appendChild(script);
        });
    }
    
    async stitchEquatorWithOpenCV(equatorImages) {
        // This will use cv.ImgStitch with the same parameters as equator-stitch-test.html
        let mImgStitch = null;
        let imageVector = null;
        let fovVector = null;
        let outputCanvas = document.createElement('canvas');
        let matchedIndices = [];

        
        
        try {
            // Convert images to OpenCV Mats
            imageVector = new cv.MatVector();
            fovVector = new cv.FloatVector();
            
            console.log('Converting equator images to OpenCV format...');
            
            // First, sort equator images by yaw to ensure proper order
            equatorImages.sort((a, b) => {
                const yawA = a.yaw || a.actualYaw || 0;
                const yawB = b.yaw || b.actualYaw || 0;
                return yawA - yawB;
            });
            
            // Add duplicate of first image at the end for wrap-around
            // This is REQUIRED for OpenCV to properly match the image chain
            if (equatorImages.length > 0) {
                console.log('🔄 Adding duplicate of first image for wrap-around');
                const firstImage = equatorImages[0];
                const duplicateFirst = {
                    ...firstImage,
                    hotspotId: firstImage.hotspotId + 100, // Different ID
                    yaw: firstImage.yaw + 360 // Wrap around position
                };
                equatorImages.push(duplicateFirst);
                console.log(`Now have ${equatorImages.length} images (12 + 1 duplicate)`);
            }
            
            for (let i = 0; i < equatorImages.length; i++) {
                const imgData = equatorImages[i];
                
                // Load image
                const img = new Image();
                await new Promise((resolve, reject) => {
                    img.onload = resolve;
                    img.onerror = reject;
                    img.src = imgData.imageData;
                });
                
                // Create canvas to get image data
                const tempCanvas = document.createElement('canvas');
                const tempCtx = tempCanvas.getContext('2d');
                tempCanvas.width = img.width;
                tempCanvas.height = img.height;
                tempCtx.drawImage(img, 0, 0);
                
                // Convert to OpenCV Mat
                const mat = cv.imread(tempCanvas);
                imageVector.push_back(mat);
                
                // Add FOV - use 80 degrees for portrait images as in equator-stitch-test
                fovVector.push_back(EQUATOR_PORTRAIT_FOV_DEG);
                
                console.log(`Added image ${i+1}/${equatorImages.length}: yaw=${imgData.yaw || imgData.actualYaw || 0}°`);
            }
            
            console.log(`Total images for OpenCV: ${imageVector.size()} (should be 13 with duplicate)`);
            
            // Create ImgStitch instance
            console.log('Creating cv.ImgStitch instance...');
            mImgStitch = new cv.ImgStitch(imageVector);
            
            // Set parameters - EXACT same as working equator-stitch-test.html
            if (typeof mImgStitch.set === 'function') {
                console.log('Setting OpenCV parameters...');
                try {
                    const paramTypes = new cv.IntVector();
                    const paramValues = new cv.FloatVector();
                    
                    // Projection - spherical
                    paramTypes.push_back(305);  // projection_type  
                    paramValues.push_back(553);  // 553 = spherical
                    
                    // Enable camera estimation
                    paramTypes.push_back(582);  // camera_estimation
                    paramValues.push_back(1);   // 1 = enabled
                    
                    // Ray blacklist bundle adjustment
                    paramTypes.push_back(583);  // bundle_adjustment
                    paramValues.push_back(703);  // 703 = ray blacklist
                    
                    // Horizontal wave correction
                    paramTypes.push_back(610);  // wave_correction
                    paramValues.push_back(611);  // 611 = WAVE_CORRECT_H
                    
                    // Voronoi seam finder
                    paramTypes.push_back(577);  // seam_finder_type
                    paramValues.push_back(578);  // 578 = Voronoi
                    
                    // Enable seam blend
                    paramTypes.push_back(585);  // seam_blend
                    paramValues.push_back(1);    // 1 = enabled
                    
                    // Increase blend strength for better wrap-around
                    paramTypes.push_back(592);  // blend_strength
                    paramValues.push_back(20);   // Higher than default 10
                    
                    // Disable exposure compensation (causes memory overflow)
                    paramTypes.push_back(590);  // exposure_compensator
                    paramValues.push_back(0);    // 0 = disabled
                    
                    // Enable calculate center image
                    paramTypes.push_back(588);  // calc_center_image
                    paramValues.push_back(1);    // 1 = enabled
                    
                    // Disable warp first
                    paramTypes.push_back(580);  // warp_first
                    paramValues.push_back(0);    // 0 = disabled
                    
                    // Lower confidence threshold for better matching
                    paramTypes.push_back(589);  // pano_confidence_thresh
                    paramValues.push_back(0.2);  // Lower for better matching
                    
                    // Set image match reach to 0 (infinite - check all images)
                    paramTypes.push_back(720);  // input_images_match_reach
                    paramValues.push_back(0);    // 0 = infinite
                    
                    // Try to force matching for wrap-around
                    paramTypes.push_back(602);  // force_matches
                    paramValues.push_back(1);    // 1 = force matching
                    
                    // Scale down output
                    paramTypes.push_back(596);  // compositing_resol
                    paramValues.push_back(0.2);  // Scale down to avoid huge outputs
                    
                    mImgStitch.set(paramTypes, paramValues);
                    console.log('Parameters set - all from working equator-stitch-test');
                    
                    paramTypes.delete();
                    paramValues.delete();
                } catch(e) {
                    console.log('Warning: Could not set all parameters: ' + e);
                    // Continue anyway - might still work
                }
            } else {
                console.log('Warning: mImgStitch.set not available');
            }
            
            // Use stitchStart/stitchNext approach
            console.log('Starting OpenCV stitching...');
            let stitchedImage = new cv.Mat();
            const stitchIndices = new cv.IntVector();
            
            const startResult = mImgStitch.stitchStart(fovVector, stitchedImage, stitchIndices);
            console.log('stitchStart result: ' + startResult);
            
            if (startResult !== -1) {
                const numMatched = stitchIndices.size();
                console.log(`Initial stitching matched ${numMatched} images`);
                
                // Track which images were matched
                for (let i = 0; i < numMatched; i++) {
                    const idx = stitchIndices.get(i);
                    // Don't include the duplicate last image in matched indices
                    if (idx < equatorImages.length) {
                        matchedIndices.push(idx);
                    }
                }
                
                // Delete initial warped image
                stitchedImage.delete();
                
                // Complete stitching with stitchNext
                let iterations = 0;
                const maxIterations = 20;
                
                while (iterations < maxIterations) {
                    const stitchedImageNext = new cv.Mat();
                    const stitchedImageSmall = new cv.Mat();
                    const nextResult = mImgStitch.stitchNext(stitchedImageNext, stitchedImageSmall);
                    console.log(`stitchNext returned: ${nextResult}`);
                    
                    if (nextResult === -1) {
                        console.log('ERROR: stitchNext failed');
                        stitchedImageNext.delete();
                        stitchedImageSmall.delete();
                        break;
                    } else if (nextResult === 0 || nextResult === numMatched) {
                        console.log('Stitching complete!');
                        cv.imshow(outputCanvas, stitchedImageNext);
                        
                        // Log detailed results
                        console.log(`✅ OpenCV stitching successful`);
                        console.log(`   Output: ${outputCanvas.width}x${outputCanvas.height}`);
                        console.log(`   Matched: ${matchedIndices.length} of ${equatorImages.length - 1} images (excluding duplicate)`);
                        console.log(`   Pixels/degree: ${(outputCanvas.width / 360).toFixed(1)}`);
                        
                        stitchedImageNext.delete();
                        stitchedImageSmall.delete();
                        break;
                    }
                    
                    stitchedImageNext.delete();
                    stitchedImageSmall.delete();
                    iterations++;
                }
            } else {
                console.log('ERROR: stitchStart failed - not enough matches');
            }
            
            stitchIndices.delete();
            
        } catch (error) {
            console.error('OpenCV stitching error:', error);
        } finally {
            // Cleanup
            if (imageVector) {
                for (let i = 0; i < imageVector.size(); i++) {
                    imageVector.get(i).delete();
                }
                imageVector.delete();
            }
            if (fovVector) fovVector.delete();
            if (mImgStitch) mImgStitch.delete();
        }
        
        return { canvas: outputCanvas, matchedIndices };
    }
    
    async projectSphericalToERP(gl, erpCanvas, sphericalCanvas, equatorImages, matchedIndices) {
        // Project the spherical panorama from OpenCV to equirectangular
        // The equator images have FOV of 67° vertically for portrait orientation
        
        console.log(`Projecting spherical equator (${sphericalCanvas.width}x${sphericalCanvas.height}) to ERP...`);
        
        // Check if we actually have a canvas with content
        if (!sphericalCanvas || sphericalCanvas.width === 0 || sphericalCanvas.height === 0) {
            console.error('Spherical canvas is empty or invalid');
            return;
        }
        
        // Create a texture from the spherical canvas
        const texture = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, sphericalCanvas);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        
        // Shader that maps spherical to equirectangular
        // The spherical panorama from OpenCV covers the full 360° horizontally
        // The vertical FOV is based on the camera FOV (67° for equator images)
        const vs = `
        attribute vec2 a_position;
        varying vec2 v_texCoord;
        void main() {
            gl_Position = vec4(a_position, 0.0, 1.0);
            v_texCoord = a_position * 0.5 + 0.5;
            v_texCoord.y = 1.0 - v_texCoord.y; // Flip Y
        }`;
        
        const fs = `
        precision mediump float;
        varying vec2 v_texCoord;
        uniform sampler2D u_spherical;
        uniform vec2 u_sphericalSize;
        uniform vec2 u_erpSize;
        
        void main() {
            // ERP coordinates to spherical angles
            float theta = v_texCoord.x * 2.0 * 3.14159265359; // 0 to 2π
            float phi = (0.5 - v_texCoord.y) * 3.14159265359; // -π/2 to π/2
            
            // The equator band should cover more vertical space
            float maxPhi = 0.785; // 45 degrees in radians
            if (abs(phi) > maxPhi) {
                gl_FragColor = vec4(0.0, 0.0, 0.0, 0.0); // Transparent/black outside equator
                return;
            }
            
            // Map to spherical panorama texture coordinates
            // The OpenCV output has already been cropped to 360° by cropEquatorWrap
            float u = theta / (2.0 * 3.14159265359); // 0 to 1 in ERP space (360°)
            
            // Apply horizontal offset to align with WebGL panorama
            // This shifts the OpenCV result to start at the correct position
            u = mod(u + 0.85, 1.0); // Shift for alignment
            
            // Map the ERP phi directly to texture v
            float v = 0.5 - (phi / (2.0 * maxPhi)); // Map [-45°, +45°] to [0, 1]
            
            // Sample the spherical panorama
            vec4 color = texture2D(u_spherical, vec2(u, v));
            
            // Add feathering at the edges for smooth blending
            float edgeFade = 1.0;
            float fadeZone = 0.1; // Fade over 10% of the range
            float fadeStart = maxPhi * (1.0 - fadeZone);
            
            if (abs(phi) > fadeStart) {
                // Fade out near the edges
                edgeFade = 1.0 - ((abs(phi) - fadeStart) / (maxPhi - fadeStart));
                edgeFade = smoothstep(0.0, 1.0, edgeFade);
            }
            
            color.a *= edgeFade;
            gl_FragColor = color;
        }`;
        
        // Compile shaders
        const vertShader = gl.createShader(gl.VERTEX_SHADER);
        gl.shaderSource(vertShader, vs);
        gl.compileShader(vertShader);
        if (!gl.getShaderParameter(vertShader, gl.COMPILE_STATUS)) {
            console.error('Vertex shader error:', gl.getShaderInfoLog(vertShader));
            return;
        }
        
        const fragShader = gl.createShader(gl.FRAGMENT_SHADER);
        gl.shaderSource(fragShader, fs);
        gl.compileShader(fragShader);
        if (!gl.getShaderParameter(fragShader, gl.COMPILE_STATUS)) {
            console.error('Fragment shader error:', gl.getShaderInfoLog(fragShader));
            return;
        }
        
        const program = gl.createProgram();
        gl.attachShader(program, vertShader);
        gl.attachShader(program, fragShader);
        gl.linkProgram(program);
        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            console.error('Program link error:', gl.getProgramInfoLog(program));
            return;
        }
        
        // Set up geometry (full screen quad)
        const buffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([
            -1, -1,
             1, -1,
            -1,  1,
             1,  1
        ]), gl.STATIC_DRAW);
        
        // Use program and set uniforms
        gl.useProgram(program);
        
        const posLoc = gl.getAttribLocation(program, 'a_position');
        gl.enableVertexAttribArray(posLoc);
        gl.vertexAttribPointer(posLoc, 2, gl.FLOAT, false, 0, 0);
        
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.uniform1i(gl.getUniformLocation(program, 'u_spherical'), 0);
        gl.uniform2f(gl.getUniformLocation(program, 'u_sphericalSize'), sphericalCanvas.width, sphericalCanvas.height);
        gl.uniform2f(gl.getUniformLocation(program, 'u_erpSize'), erpCanvas.width, erpCanvas.height);
        
        // Clear and draw
        gl.viewport(0, 0, erpCanvas.width, erpCanvas.height);
        gl.clearColor(0, 0, 0, 0);
        gl.clear(gl.COLOR_BUFFER_BIT);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        
        console.log('Spherical to ERP projection complete');
        
        // Cleanup
        gl.deleteTexture(texture);
        gl.deleteProgram(program);
        gl.deleteShader(vertShader);
        gl.deleteShader(fragShader);
        gl.deleteBuffer(buffer);
    }
    
    async prepareLayersForRendering(images) {
        // Similar to the existing preparation in startBestPixelStitching
        const layers = [];
        
        for (const capture of images) {
            const img = new Image();
            await new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = reject;
                img.src = capture.imageData;
            });
            
            const targetW = 720;
            const targetH = 1280;
            const canvas = this.drawToPortraitCanvas(img, targetW, targetH);
            
            // Calculate yaw value - normalize compass headings relative to first capture
            let yawValue;
            if (capture.hasCompass && capture.compassYaw !== undefined && this.firstCaptureCompassHeading !== null) {
                // Normalize compass heading relative to first capture
                // This makes the panorama relative to the first capture direction
                // while maintaining real-world alignment metadata
                const relativeYaw = (capture.compassYaw - this.firstCaptureCompassHeading + 360) % 360;
                yawValue = relativeYaw;
                console.log(`Image ${capture.hotspotId}: compass ${capture.compassYaw.toFixed(1)}° → relative ${relativeYaw.toFixed(1)}°`);
            } else {
                // Fall back to device-relative yaw
                yawValue = capture.actualYaw || capture.yaw || 0;
            }
            
            layers.push({
                canvas: canvas,
                hotspotId: capture.hotspotId, // Include hotspot ID for adjacency mapping
                pitch: capture.actualPitch || capture.pitch || 0,
                yaw: yawValue,
                roll: capture.roll || 0,
                fov: capture.fov || 67,
                hasCompass: capture.hasCompass || false
            });
        }
        
        return layers;
    }
    
    async renderBestPixelWithMask(gl, erpCanvas, layers, hasEquator) {
        // If we don't have an equator from OpenCV, just render everything normally
        if (!hasEquator) {
            await this.renderBestPixelPanorama(gl, erpCanvas, layers);
            return;
        }
        
        // Save the current framebuffer content (which has the equator)
        const pixels = new Uint8Array(erpCanvas.width * erpCanvas.height * 4);
        gl.readPixels(0, 0, erpCanvas.width, erpCanvas.height, gl.RGBA, gl.UNSIGNED_BYTE, pixels);
        
        // Create a texture from the current content
        const equatorTexture = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, equatorTexture);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, erpCanvas.width, erpCanvas.height, 0, gl.RGBA, gl.UNSIGNED_BYTE, pixels);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
        
        // Now render the upper/lower bands with best-pixel
        await this.renderBestPixelPanorama(gl, erpCanvas, layers);
        
        // Blend the equator back on top
        // Simple shader to composite the equator band
        const vs = `
        attribute vec2 a_position;
        varying vec2 v_texCoord;
        void main() {
            gl_Position = vec4(a_position, 0.0, 1.0);
            v_texCoord = a_position * 0.5 + 0.5;
        }`;
        
        const fs = `
        precision mediump float;
        varying vec2 v_texCoord;
        uniform sampler2D u_equator;
        uniform sampler2D u_current;
        
        void main() {
            vec4 equatorColor = texture2D(u_equator, v_texCoord);
            vec4 currentColor = texture2D(u_current, v_texCoord);
            
            // Calculate latitude from texture coordinate
            float phi = (0.5 - v_texCoord.y) * 3.14159265359; // -π/2 to π/2
            float maxPhi = 0.785; // 45 degrees - equator band extent
            
            // Blend based on the alpha channel of the equator (which has feathering)
            if (abs(phi) <= maxPhi && equatorColor.a > 0.0) {
                // Alpha blend the equator with the current content
                float alpha = equatorColor.a;
                gl_FragColor = vec4(
                    mix(currentColor.rgb, equatorColor.rgb, alpha),
                    1.0
                );
            } else {
                gl_FragColor = currentColor;
            }
        }`;
        
        // Get current framebuffer content
        const currentPixels = new Uint8Array(erpCanvas.width * erpCanvas.height * 4);
        gl.readPixels(0, 0, erpCanvas.width, erpCanvas.height, gl.RGBA, gl.UNSIGNED_BYTE, currentPixels);
        
        const currentTexture = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, currentTexture);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, erpCanvas.width, erpCanvas.height, 0, gl.RGBA, gl.UNSIGNED_BYTE, currentPixels);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
        
        // Compile shaders
        const vertShader = gl.createShader(gl.VERTEX_SHADER);
        gl.shaderSource(vertShader, vs);
        gl.compileShader(vertShader);
        
        const fragShader = gl.createShader(gl.FRAGMENT_SHADER);
        gl.shaderSource(fragShader, fs);
        gl.compileShader(fragShader);
        
        const program = gl.createProgram();
        gl.attachShader(program, vertShader);
        gl.attachShader(program, fragShader);
        gl.linkProgram(program);
        
        // Set up and draw
        gl.useProgram(program);
        
        const posLoc = gl.getAttribLocation(program, 'a_position');
        const buffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);
        gl.enableVertexAttribArray(posLoc);
        gl.vertexAttribPointer(posLoc, 2, gl.FLOAT, false, 0, 0);
        
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, equatorTexture);
        gl.uniform1i(gl.getUniformLocation(program, 'u_equator'), 0);
        
        gl.activeTexture(gl.TEXTURE1);
        gl.bindTexture(gl.TEXTURE_2D, currentTexture);
        gl.uniform1i(gl.getUniformLocation(program, 'u_current'), 1);
        
        gl.viewport(0, 0, erpCanvas.width, erpCanvas.height);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        
        // Cleanup
        gl.deleteTexture(equatorTexture);
        gl.deleteTexture(currentTexture);
        gl.deleteProgram(program);
        gl.deleteShader(vertShader);
        gl.deleteShader(fragShader);
        gl.deleteBuffer(buffer);
    }
    
    async displayStitchResult(canvas) {
        // Similar to existing display logic
        this.elements.statusText.textContent = 'Photosphere Complete!';
        this.elements.statusDetails.textContent = 'Enhanced stitching finished';
        
        // Show preview
        const ctx = this.elements.previewCanvas.getContext('2d');
        const scale = Math.min(
            this.elements.previewCanvas.width / canvas.width,
            this.elements.previewCanvas.height / canvas.height
        );
        const width = canvas.width * scale;
        const height = canvas.height * scale;
        const x = (this.elements.previewCanvas.width - width) / 2;
        const y = (this.elements.previewCanvas.height - height) / 2;
        
        ctx.clearRect(0, 0, this.elements.previewCanvas.width, this.elements.previewCanvas.height);
        ctx.drawImage(canvas, x, y, width, height);
        
        // Save to database
        const panoramaBlob = await new Promise(resolve => 
            canvas.toBlob(resolve, 'image/jpeg', 0.9)
        );
        
        const panoramaData = {
            imageBlob: panoramaBlob,
            timestamp: new Date().toISOString(),
            type: 'enhanced-equirectangular',
            width: canvas.width,
            height: canvas.height
        };
        
        await this.database.savePanorama(panoramaData);
        console.log('Enhanced panorama saved to database');
    }

    async renderEquatorBandLinear(gl, canvas, equatorCanvas) {
        // Create texture from equator band
        const equatorTexture = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, equatorTexture);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, equatorCanvas);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        
        // Read current canvas content to preserve upper/lower bands
        const currentTexture = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, currentTexture);
        gl.copyTexImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 0, 0, canvas.width, canvas.height, 0);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
        
        // Shader that does linear longitude mapping
        const vs = `
        attribute vec2 a_position;
        varying vec2 v_texCoord;
        void main() {
            gl_Position = vec4(a_position, 0.0, 1.0);
            v_texCoord = a_position * 0.5 + 0.5;
            v_texCoord.y = 1.0 - v_texCoord.y;
        }`;
        
        const fs = `
        precision highp float;
        varying vec2 v_texCoord;
        uniform sampler2D u_current;
        uniform sampler2D u_equator;
        uniform vec2 u_equatorSize;
        uniform vec2 u_canvasSize;
        uniform float u_horizontalOffset;
        
        void main() {
            vec4 currentColor = texture2D(u_current, v_texCoord);
            
            // Check if we're in the equator band region
            float equatorHeight = u_equatorSize.y / u_canvasSize.y;
            float equatorTop = 0.5 - equatorHeight * 0.5;
            float equatorBottom = 0.5 + equatorHeight * 0.5;
            
            if (v_texCoord.y >= equatorTop && v_texCoord.y <= equatorBottom) {
                // Direct linear mapping - NO Y-flip (everything was upside down!)
                float u = mod(v_texCoord.x + u_horizontalOffset, 1.0);  // Apply offset for alignment
                float v = (v_texCoord.y - equatorTop) / equatorHeight;  // Direct mapping
                
                vec4 equatorColor = texture2D(u_equator, vec2(u, v));
                
                // Use equator if it has content (alpha > 0)
                if (equatorColor.a > 0.01) {
                    gl_FragColor = equatorColor;
                } else {
                    gl_FragColor = currentColor;
                }
            } else {
                gl_FragColor = currentColor;
            }
        }`;
        
        // Compile shaders
        const vertShader = gl.createShader(gl.VERTEX_SHADER);
        gl.shaderSource(vertShader, vs);
        gl.compileShader(vertShader);
        if (!gl.getShaderParameter(vertShader, gl.COMPILE_STATUS)) {
            console.error('Vertex shader error:', gl.getShaderInfoLog(vertShader));
            return;
        }
        
        const fragShader = gl.createShader(gl.FRAGMENT_SHADER);
        gl.shaderSource(fragShader, fs);
        gl.compileShader(fragShader);
        if (!gl.getShaderParameter(fragShader, gl.COMPILE_STATUS)) {
            console.error('Fragment shader error:', gl.getShaderInfoLog(fragShader));
            return;
        }
        
        const program = gl.createProgram();
        gl.attachShader(program, vertShader);
        gl.attachShader(program, fragShader);
        gl.linkProgram(program);
        
        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            console.error('Program link error:', gl.getProgramInfoLog(program));
            return;
        }
        
        gl.useProgram(program);
        
        // Set uniforms
        gl.uniform1i(gl.getUniformLocation(program, 'u_current'), 0);
        gl.uniform1i(gl.getUniformLocation(program, 'u_equator'), 1);
        gl.uniform2f(gl.getUniformLocation(program, 'u_equatorSize'), equatorCanvas.width, equatorCanvas.height);
        gl.uniform2f(gl.getUniformLocation(program, 'u_canvasSize'), canvas.width, canvas.height);
        // Adjust offset if needed for alignment  
        gl.uniform1f(gl.getUniformLocation(program, 'u_horizontalOffset'), 0.0);  // Start with 0, adjust if needed
        
        // Bind textures
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, currentTexture);
        gl.activeTexture(gl.TEXTURE1);
        gl.bindTexture(gl.TEXTURE_2D, equatorTexture);
        
        // Set up geometry
        const positionBuffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
        
        const positionLocation = gl.getAttribLocation(program, 'a_position');
        gl.enableVertexAttribArray(positionLocation);
        gl.vertexAttribPointer(positionLocation, 2, gl.FLOAT, false, 0, 0);
        
        // Draw
        gl.viewport(0, 0, canvas.width, canvas.height);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        
        // Clean up
        gl.deleteTexture(equatorTexture);
        gl.deleteTexture(currentTexture);
        gl.deleteProgram(program);
        gl.deleteShader(vertShader);
        gl.deleteShader(fragShader);
        gl.deleteBuffer(positionBuffer);
    }

    // Map a stitched equator band (already panoramic) into the ERP by linear longitude.
    // Assumes the band width spans exactly 360° (crop overlap first).
    mapEquatorBandToERPLinearLongitude(equatorCanvas, erpCanvas, bandCenterY = null, horizontalOffset = 0.85) {
        const srcW = equatorCanvas.width;
        const srcH = equatorCanvas.height;
        const dstW = erpCanvas.width;
        const dstH = erpCanvas.height;

        // target vertical placement: centered on ERP equator
        const bandY = (bandCenterY == null) ? Math.floor(dstH / 2 - srcH / 2) : Math.max(0, Math.min(dstH - srcH, bandCenterY));

        const srcCtx = equatorCanvas.getContext('2d', { willReadFrequently: true });
        const srcData = srcCtx.getImageData(0, 0, srcW, srcH).data;

        const dstCtx = erpCanvas.getContext('2d');
        const dstImg = dstCtx.getImageData(0, bandY, dstW, srcH);
        const dstData = dstImg.data;

        // For each ERP x, compute longitude, then sample source x linearly.
        for (let x = 0; x < dstW; x++) {
            const lon = (x / dstW) * 2 * Math.PI - Math.PI;       // [-π, π)
            // Apply horizontal offset to align with WebGL panorama
            let sx = ((lon + Math.PI) / (2 * Math.PI) + horizontalOffset) % 1.0 * srcW;      // [0, srcW) with offset
            // bilinear in X (simple: frac blend between floor/ceil)
            const sx0 = Math.floor(sx) % srcW;
            const sx1 = (sx0 + 1) % srcW;
            const t = sx - Math.floor(sx);

            for (let y = 0; y < srcH; y++) {
                const si0 = (y * srcW + sx0) * 4;
                const si1 = (y * srcW + sx1) * 4;
                const di = (y * dstW + x) * 4;

                dstData[di + 0] = (1 - t) * srcData[si0 + 0] + t * srcData[si1 + 0];
                dstData[di + 1] = (1 - t) * srcData[si0 + 1] + t * srcData[si1 + 1];
                dstData[di + 2] = (1 - t) * srcData[si0 + 2] + t * srcData[si1 + 2];
                dstData[di + 3] = (1 - t) * srcData[si0 + 3] + t * srcData[si1 + 3];
            }
        }

        dstCtx.putImageData(dstImg, 0, bandY);
    }

    // --- overlap detection for stitched equator band ---
    estimateEquatorWrapOverlapPx(canvas, bandPx = 64, maxSearchPx = 512) {
    const W = canvas.width, H = canvas.height;
    if (!W || !H) return 0;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
  
    const stripW = Math.min(bandPx, Math.floor(W / 6) || 1);
    const left = ctx.getImageData(0, 0, stripW, H).data;
    const rightImg = ctx.getImageData(W - stripW, 0, stripW, H).data;
  
    // luminance helper
    const lumAt = (data, i) => 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2];
  
    const stepY = Math.max(1, Math.floor(H / 512)); // subsample rows for speed
    const stepX = 2;                                 // subsample cols
    const search = Math.min(maxSearchPx, Math.floor(W / 6)); // reasonable search window
  
    let bestShift = 0, bestScore = Infinity;
    for (let s = 0; s <= search; s++) {
      let ssd = 0, n = 0;
      for (let y = 0; y < H; y += stepY) {
        const rowBase = y * stripW * 4;
        for (let x = 0; x < stripW; x += stepX) {
          const li = rowBase + x * 4;
          const rx = Math.max(0, Math.min(stripW - 1, x - s));
          const ri = rowBase + rx * 4;
  
          const l = lumAt(left, li);
          const r = lumAt(rightImg, ri);
          const d = l - r;
          ssd += d * d; n++;
        }
      }
      ssd /= Math.max(1, n);
      if (ssd < bestScore) { bestScore = ssd; bestShift = s; }
    }
    return bestShift; // pixels of duplicated wrap to remove
  }
  
  // --- circularly shift so seam is at x=0 and crop the overlap ---
  cropEquatorWrap(canvas, overlapPx) {
    if (!overlapPx || overlapPx <= 0) return canvas;
    const W = canvas.width, H = canvas.height;
  
    const out = document.createElement('canvas');
    out.width = Math.max(1, W - overlapPx);
    out.height = H;
    const octx = out.getContext('2d');
  
    // move [overlap..W) to the front, then append [0..overlap)
    octx.drawImage(canvas, overlapPx, 0, W - overlapPx, H, 0, 0, W - overlapPx, H);
    octx.drawImage(canvas, 0, 0, overlapPx, H, W - overlapPx, 0, overlapPx, H);
  
    return out;
  }
  
    
    drawToPortraitCanvas(img, targetW, targetH) {
        // Handle both Image and ImageBitmap objects
        const srcW = img.naturalWidth || img.width;
        const srcH = img.naturalHeight || img.height;
        
        // Validate dimensions
        if (!srcW || !srcH) {
            console.error('Invalid image dimensions:', { srcW, srcH, img });
            // Return empty canvas as fallback
            const canvas = document.createElement('canvas');
            canvas.width = targetW;
            canvas.height = targetH;
            return canvas;
        }
        
        const landscape = srcW > srcH;
        const canvas = document.createElement('canvas');
        canvas.width = targetW;
        canvas.height = targetH;
        const ctx = canvas.getContext('2d');
        
        const dw = landscape ? srcH : srcW;
        const dh = landscape ? srcW : srcH;
        const s = Math.min(targetW/dw, targetH/dh);
        const drawW = dw * s, drawH = dh * s;
        const dx = (targetW - drawW)/2, dy = (targetH - drawH)/2;
        
        if (landscape) {
            ctx.save();
            ctx.translate(dx, dy + drawH);
            ctx.rotate(-Math.PI/2);
            ctx.drawImage(img, 0, 0, srcW, srcH, 0, 0, drawH, drawW);
            ctx.restore();
        } else {
            ctx.drawImage(img, 0, 0, srcW, srcH, dx, dy, drawW, drawH);
        }
        return canvas;
    }
    
    /**
     * Normalize yaw angle to [0, 360] range
     * Handles wraparound for compass headings
     * 
     * @param {number} y - Yaw angle in degrees
     * @returns {number} Normalized angle in [0, 360]
     */
    normYaw(y) {
        let d = y % 360;
        return d < 0 ? d + 360 : d;
    }
    
    /**
     * Compute gain compensation values to normalize exposure differences
     * Simplified approach using known adjacency
     */
    async computeGainCompensation(layers, canvases) {
        const gains = new Float32Array(layers.length);
        gains.fill(1.0);
        
        // Skip gain compensation if disabled
        const disableGainComp = sessionStorage.getItem('disableGainCompensation') === 'true';
        if (disableGainComp) {
            console.log('Gain compensation disabled');
            return gains;
        }
        
        try {
        
        console.log(`Computing gain compensation for ${layers.length} images`);
        
        // First, compute average brightness for each image
        const brightness = new Float32Array(layers.length);
        for (let i = 0; i < canvases.length; i++) {
            const canvas = canvases[i];
            const ctx = canvas.getContext('2d');
            
            // Sample the image to get average brightness
            const step = 20; // Sample every 20th pixel for speed
            let sum = 0;
            let count = 0;
            
            try {
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;
                
                for (let y = 0; y < canvas.height; y += step) {
                    for (let x = 0; x < canvas.width; x += step) {
                        const idx = (y * canvas.width + x) * 4;
                        // Convert to luminance
                        const r = data[idx] / 255;
                        const g = data[idx + 1] / 255;
                        const b = data[idx + 2] / 255;
                        const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
                        if (lum > 0.01) { // Skip very dark pixels
                            sum += lum;
                            count++;
                        }
                    }
                }
                
                brightness[i] = count > 0 ? sum / count : 0.5;
                console.log(`Image ${i} (hotspot ${layers[i].hotspotId}): brightness=${brightness[i].toFixed(3)}`);
            } catch (e) {
                console.warn(`Failed to compute brightness for image ${i}:`, e);
                brightness[i] = 0.5;
            }
        }
        
        // Use known adjacency from hotspot pattern
        const { Hotspots } = await import('./hotspots.js');
        const adjacencyGraph = Hotspots.getMatchingGraph();
        
        // Build list of adjacent pairs
        const adjacentPairs = [];
        const hotspotToIndex = new Map();
        
        // Map hotspot IDs to layer indices
        for (let i = 0; i < layers.length; i++) {
            const hotspotId = layers[i].hotspotId;
            if (hotspotId) {
                hotspotToIndex.set(hotspotId, i);
            }
        }
        
        // Find all adjacent pairs
        for (let i = 0; i < layers.length; i++) {
            const hotspotId = layers[i].hotspotId;
            if (!hotspotId) continue;
            
            const neighbors = adjacencyGraph[hotspotId.toString()] || [];
            for (const neighborId of neighbors) {
                const j = hotspotToIndex.get(parseInt(neighborId));
                if (j !== undefined && j > i) { // Only process each pair once
                    const ratio = brightness[i] / brightness[j];
                    adjacentPairs.push({ i, j, ratio });
                    console.log(`Adjacent ${hotspotId}-${neighborId}: brightness ratio=${ratio.toFixed(2)}`);
                }
            }
        }
        
        console.log(`Found ${adjacentPairs.length} adjacent image pairs`);
        
        if (adjacentPairs.length === 0) {
            console.warn('No adjacent pairs found for gain compensation');
            return gains;
        }
        
        // Simple gain optimization: make all images have similar brightness
        // Target brightness is the median brightness
        const sortedBrightness = [...brightness].sort((a, b) => a - b);
        const targetBrightness = sortedBrightness[Math.floor(sortedBrightness.length / 2)];
        
        console.log(`Target brightness: ${targetBrightness.toFixed(3)}`);
        
        // Set gains to normalize each image to target brightness
        for (let i = 0; i < layers.length; i++) {
            if (brightness[i] > 0) {
                gains[i] = targetBrightness / brightness[i];
                // Clamp gains to reasonable range
                gains[i] = Math.max(0.5, Math.min(2.0, gains[i]));
                console.log(`Image ${i}: gain=${gains[i].toFixed(2)}`);
            }
        }
        
        // Optional: Iterative refinement based on adjacent pairs
        const maxIterations = 5;
        const convergenceThreshold = 0.01;
        const dampingFactor = 0.3; // Slower updates for stability
        
        for (let iter = 0; iter < maxIterations; iter++) {
            const updates = new Float32Array(layers.length);
            const counts = new Float32Array(layers.length);
            
            // Accumulate gain adjustments from all adjacent pairs
            let totalError = 0;
            for (const pair of adjacentPairs) {
                const { i, j, ratio } = pair;
                // Current intensity ratio with gains applied
                const currentRatio = (gains[i] / gains[j]) * ratio;
                // Error from ideal ratio of 1.0
                const error = Math.log(currentRatio);
                totalError += Math.abs(error);
                
                // Distribute error proportionally
                updates[i] -= error * 0.5;
                updates[j] += error * 0.5;
                counts[i] += 1;
                counts[j] += 1;
            }
            
            // Apply updates with damping
            let maxUpdate = 0;
            for (let i = 0; i < layers.length; i++) {
                if (counts[i] > 0) {
                    const update = updates[i] / counts[i];
                    const dampedUpdate = update * dampingFactor;
                    gains[i] *= Math.exp(dampedUpdate);
                    maxUpdate = Math.max(maxUpdate, Math.abs(dampedUpdate));
                }
            }
            
            // Normalize to geometric mean of 1.0 (better than arithmetic mean)
            const logSum = gains.reduce((sum, g) => sum + Math.log(g), 0);
            const geoMean = Math.exp(logSum / gains.length);
            for (let i = 0; i < gains.length; i++) {
                gains[i] /= geoMean;
            }
            
            // Check convergence
            const avgError = adjacentPairs.length > 0 ? totalError / adjacentPairs.length : 0;
            if (maxUpdate < convergenceThreshold || avgError < 0.01) {
                console.log(`Gain compensation converged in ${iter + 1} iterations (avg error: ${avgError.toFixed(4)})`);
                break;
            }
        }
        
        // Apply gentle smoothing to neighboring gains to avoid sharp transitions
        const smoothedGains = new Float32Array(gains);
        for (let i = 0; i < layers.length; i++) {
            let sum = gains[i] * 2; // Weight current gain more
            let count = 2;
            
            // Add adjacent neighbors' gains for smoothing
            const hotspotId = layers[i].hotspotId;
            if (hotspotId) {
                const neighbors = adjacencyGraph[hotspotId.toString()] || [];
                for (const neighborId of neighbors) {
                    const j = hotspotToIndex.get(parseInt(neighborId));
                    if (j !== undefined) {
                        sum += gains[j];
                        count++;
                    }
                }
            }
            
            smoothedGains[i] = sum / count;
        }
        
        // Clamp gains to reasonable range (allow wider range for testing)
        for (let i = 0; i < smoothedGains.length; i++) {
            smoothedGains[i] = Math.max(0.5, Math.min(2.0, smoothedGains[i]));
        }
        
        console.log('Computed exposure gains:', smoothedGains);
        return smoothedGains;
        
        } catch (error) {
            console.error('Error computing gain compensation:', error);
            // Return default gains rather than crashing
            return gains;
        }
    }
    
    /**
     * Compute intensity ratio between overlapping regions of two images
     */
    async computeIntensityRatio(canvas1, layer1, canvas2, layer2) {
        // More focused sampling in actual overlap region
        const ctx1 = canvas1.getContext('2d');
        const ctx2 = canvas2.getContext('2d');
        
        const HFOVdeg = 44, VFOVdeg = 73;
        const tanHalfHF = Math.tan(Math.PI * HFOVdeg / 360);
        const tanHalfVF = Math.tan(Math.PI * VFOVdeg / 360);
        
        // Calculate overlap region more precisely
        const yaw1 = layer1.yaw * Math.PI / 180;
        const yaw2 = layer2.yaw * Math.PI / 180;
        const pitch1 = layer1.pitch * Math.PI / 180;
        const pitch2 = layer2.pitch * Math.PI / 180;
        
        // Sample in a focused grid around the overlap center
        const samples = 30; // More samples for better accuracy
        const values1 = [];
        const values2 = [];
        
        // Sample in a grid centered between the two camera positions
        const centerYaw = (yaw1 + yaw2) / 2;
        const centerPitch = (pitch1 + pitch2) / 2;
        const sampleRange = Math.PI / 6; // 30 degree sampling range
        
        for (let si = 0; si < samples; si++) {
            for (let sj = 0; sj < samples; sj++) {
                // Generate world direction in overlap region
                const lon = centerYaw + (si / (samples - 1) - 0.5) * sampleRange;
                const lat = centerPitch + (sj / (samples - 1) - 0.5) * sampleRange;
                
                // Clamp latitude to valid range
                const clampedLat = Math.max(-Math.PI/2, Math.min(Math.PI/2, lat));
                
                const worldDir = {
                    x: Math.cos(clampedLat) * Math.sin(lon),
                    y: Math.sin(clampedLat),
                    z: Math.cos(clampedLat) * Math.cos(lon)
                };
                
                // Project to both cameras
                const uv1 = this.projectToCamera(worldDir, layer1, tanHalfHF, tanHalfVF);
                const uv2 = this.projectToCamera(worldDir, layer2, tanHalfHF, tanHalfVF);
                
                // If visible in both cameras and not at edges, sample pixels
                if (uv1 && uv2 && 
                    uv1.u > 0.1 && uv1.u < 0.9 && uv1.v > 0.1 && uv1.v < 0.9 &&
                    uv2.u > 0.1 && uv2.u < 0.9 && uv2.v > 0.1 && uv2.v < 0.9) {
                    
                    const x1 = Math.floor(uv1.u * canvas1.width);
                    const y1 = Math.floor(uv1.v * canvas1.height);
                    const x2 = Math.floor(uv2.u * canvas2.width);
                    const y2 = Math.floor(uv2.v * canvas2.height);
                    
                    try {
                        const pixel1 = ctx1.getImageData(x1, y1, 1, 1).data;
                        const pixel2 = ctx2.getImageData(x2, y2, 1, 1).data;
                        
                        // Convert to luminance (use linear space for accuracy)
                        const r1 = Math.pow(pixel1[0] / 255, 2.2);
                        const g1 = Math.pow(pixel1[1] / 255, 2.2);
                        const b1 = Math.pow(pixel1[2] / 255, 2.2);
                        const lum1 = 0.2126 * r1 + 0.7152 * g1 + 0.0722 * b1;
                        
                        const r2 = Math.pow(pixel2[0] / 255, 2.2);
                        const g2 = Math.pow(pixel2[1] / 255, 2.2);
                        const b2 = Math.pow(pixel2[2] / 255, 2.2);
                        const lum2 = 0.2126 * r2 + 0.7152 * g2 + 0.0722 * b2;
                        
                        // Only use pixels with reasonable brightness
                        if (lum1 > 0.01 && lum2 > 0.01) {
                            values1.push(lum1);
                            values2.push(lum2);
                        }
                    } catch (e) {
                        // Ignore out of bounds
                    }
                }
            }
        }
        
        // Need sufficient samples for reliable ratio
        if (values1.length < 20) {
            console.log(`Not enough overlap samples: ${values1.length} < 20`);
            // Try to return a ratio if we have at least some samples
            if (values1.length >= 5) {
                const avgLum1 = values1.reduce((a, b) => a + b, 0) / values1.length;
                const avgLum2 = values2.reduce((a, b) => a + b, 0) / values2.length;
                return avgLum1 / avgLum2;
            }
            return 0; // Not enough overlap
        }
        
        // Use median ratio for robustness against outliers
        const ratios = [];
        for (let i = 0; i < values1.length; i++) {
            ratios.push(values1[i] / values2[i]);
        }
        ratios.sort((a, b) => a - b);
        
        // Return median ratio
        const medianRatio = ratios[Math.floor(ratios.length / 2)];
        
        // Sanity check
        if (medianRatio < 0.1 || medianRatio > 10) {
            return 0; // Unrealistic ratio
        }
        
        return medianRatio;
    }
    
    /**
     * Apply vignetting correction to a single canvas
     * This corrects the natural lens darkening at edges BEFORE stitching
     * Optimized version with pre-computed lookup table
     */
    applyVignettingCorrection(canvas, strength) {
        try {
            const ctx = canvas.getContext('2d');
            const w = canvas.width;
            const h = canvas.height;
            const imageData = ctx.getImageData(0, 0, w, h);
            const data = imageData.data;
            
            const centerX = w / 2;
            const centerY = h / 2;
            const maxDist = Math.sqrt(centerX * centerX + centerY * centerY);
            
            // Pre-compute correction values for performance
            const correctionLUT = new Float32Array(256);
            for (let i = 0; i < 256; i++) {
                const normDist = i / 255;
                correctionLUT[i] = 1.0 + strength * (0.3 * normDist * normDist + 0.4 * Math.pow(normDist, 4));
            }
            
            // Process in chunks to avoid blocking
            for (let y = 0; y < h; y++) {
                for (let x = 0; x < w; x++) {
                    // Calculate distance from center
                    const dx = x - centerX;
                    const dy = y - centerY;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    const normDist = Math.min(dist / maxDist, 1.0);
                    const lutIndex = Math.floor(normDist * 255);
                    const correction = correctionLUT[lutIndex];
                    
                    // Apply correction to each color channel
                    const idx = (y * w + x) * 4;
                    data[idx] = Math.min(255, data[idx] * correction);     // R
                    data[idx + 1] = Math.min(255, data[idx + 1] * correction); // G
                    data[idx + 2] = Math.min(255, data[idx + 2] * correction); // B
                    // Alpha unchanged
                }
            }
            
            ctx.putImageData(imageData, 0, 0);
        } catch (error) {
            console.error('Error applying vignetting correction:', error);
            // Continue without correction rather than crashing
        }
    }
    
    /**
     * Project world direction to camera UV coordinates
     */
    projectToCamera(worldDir, layer, tanHalfHF, tanHalfVF) {
        const yaw = layer.yaw * Math.PI / 180;
        const pitch = layer.pitch * Math.PI / 180;
        const roll = (layer.roll || 0) * Math.PI / 180;
        
        const cy = Math.cos(yaw), sy = Math.sin(yaw);
        const cp = Math.cos(pitch), sp = Math.sin(pitch);
        const cr = Math.cos(roll), sr = Math.sin(roll);
        
        // Apply inverse rotations (yaw, pitch, roll)
        let temp = {
            x: cy * worldDir.x + sy * worldDir.z,
            y: worldDir.y,
            z: -sy * worldDir.x + cy * worldDir.z
        };
        
        let temp2 = {
            x: temp.x,
            y: cp * temp.y + sp * temp.z,
            z: -sp * temp.y + cp * temp.z
        };
        
        let camDir = {
            x: cr * temp2.x - sr * temp2.y,
            y: sr * temp2.x + cr * temp2.y,
            z: temp2.z
        };
        
        if (camDir.z <= 0) return null; // Behind camera
        
        const xn = camDir.x / camDir.z;
        const yn = camDir.y / camDir.z;
        
        if (Math.abs(xn) > tanHalfHF || Math.abs(yn) > tanHalfVF) {
            return null; // Outside FOV
        }
        
        const u = (xn / (2 * tanHalfHF)) + 0.5;
        const v = (yn / (2 * tanHalfVF)) + 0.5;
        
        return { u, v };
    }

    async renderBestPixelPanorama(gl, erpCanvas, layers) {
        // WebGL2 shader setup
        const vs = `#version 300 es
        precision highp float;
        layout(location=0) in vec2 pos;
        out vec2 v_uv;
        void main(){ v_uv = 0.5*pos + 0.5; gl_Position = vec4(pos,0,1); }`;
        
        const fs = `#version 300 es
        precision highp float;
        precision highp sampler2DArray;
        in vec2 v_uv;
        out vec4 frag;
        uniform sampler2DArray tiles;
        uniform int layerCount;
        uniform float camYaw[36];
        uniform float camPitch[36];
        uniform float camRoll[36];
        uniform float tanHalfHF;
        uniform float tanHalfVF;
        uniform float pi;
        uniform float gain[36];
        uniform float threshold;
        // NEW: Voronoi bias inputs
        uniform vec2  layerCenterUV[36];
        uniform float voronoiBias; // ~1.0–2.0 is a good start
      
        vec3 toLin(vec3 c) { return pow(c, vec3(2.2)); }
        vec3 toSRGB(vec3 c) { return pow(max(c, vec3(0.0)), vec3(1.0/2.2)); }
      
        float quality(float xn, float yn, float tanH, float tanV) {
          float dx = abs(xn) / tanH;
          float dy = abs(yn) / tanV;
          float dist = sqrt(dx*dx + dy*dy);
          return 1.0 - dist;
        }
      
        // NEW: toroidal ERP distance so seams near u=0/1 don't blow up
        float erpDistance(vec2 a, vec2 b){
          float du = abs(a.x - b.x);
          du = min(du, 1.0 - du); // wrap horizontally
          float dv = a.y - b.y;   // no wrap vertical
          return sqrt(du*du + dv*dv);
        }
      
        void main(){
          float lon = 2.0*pi*v_uv.x - pi;
          float lat = pi*(0.5 - v_uv.y);
      
          vec3 worldDir = vec3(
            cos(lat) * sin(lon),
            sin(lat),
            cos(lat) * cos(lon)
          );
      
          vec3 bestColor = vec3(0.0);
          float bestScore = -1e9;   // NEW: use score (not just q)
          int bestLayer = -1;
      
          vec3 accumColor = vec3(0.0);
          float accumWeight = 0.0;
      
          for (int l = 0; l < 36; ++l) {
            if (l >= layerCount) break;
      
            float yaw = camYaw[l];
            float pitch = camPitch[l];
            float roll = camRoll[l];
      
            float cy = cos(yaw), sy = sin(yaw);
            float cp = cos(pitch), sp = sin(pitch);
            float cr = cos(roll), sr = sin(roll);
      
            // yaw
            vec3 temp = vec3(
              cy * worldDir.x + sy * worldDir.z,
              worldDir.y,
              -sy * worldDir.x + cy * worldDir.z
            );
            // pitch
            vec3 temp2 = vec3(
              temp.x,
              cp * temp.y + sp * temp.z,
              -sp * temp.y + cp * temp.z
            );
            // roll
            vec3 camDir = vec3(
              cr * temp2.x - sr * temp2.y,
              sr * temp2.x + cr * temp2.y,
              temp2.z
            );
      
            if (camDir.z <= 0.0) continue;
      
            float xn = camDir.x / camDir.z;
            float yn = camDir.y / camDir.z;
      
            if (abs(xn) > tanHalfHF || abs(yn) > tanHalfVF) continue;
      
            float u = (xn / (2.0*tanHalfHF)) + 0.5;
            float v = (yn / (2.0*tanHalfVF)) + 0.5;
      
            vec3 s = toLin(texture(tiles, vec3(u, v, float(l))).rgb);
            s *= gain[l];
      
            float q = quality(xn, yn, tanHalfHF, tanHalfVF);
      
            // NEW: Voronoi-biased score (stabilizes seams)
            float d = erpDistance(v_uv, layerCenterUV[l]);
            float score = q - voronoiBias * d;
      
            if (score > bestScore) {
              bestScore = score;
              bestColor = s;
              bestLayer = l;
            }
      
            // keep your soft fallback as-is (uses pure q for feather)
            if (q > 0.0) {
              float w = pow(q, 4.0);
              accumColor += s * w;
              accumWeight += w;
            }
          }
      
          vec3 finalColor;
          // Use the original threshold behavior, but based on the *quality*
          // (keeps your intended crisp vs. feather logic)
          if (bestScore > (threshold - voronoiBias * 0.0)) {
            finalColor = bestColor;
          } else if (accumWeight > 0.0) {
            finalColor = accumColor / accumWeight;
          } else {
            finalColor = vec3(0.0);
          }
      
          frag = (bestLayer >= 0) ? vec4(toSRGB(finalColor), 1.0)
                                  : vec4(0.0, 0.0, 0.0, 1.0);
        }`;
      
        // Compile shaders
        function compileShader(type, src) {
          const shader = gl.createShader(type);
          gl.shaderSource(shader, src);
          gl.compileShader(shader);
          if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            throw new Error(gl.getShaderInfoLog(shader));
          }
          return shader;
        }
      
        const vsShader = compileShader(gl.VERTEX_SHADER, vs);
        const fsShader = compileShader(gl.FRAGMENT_SHADER, fs);
      
        const prog = gl.createProgram();
        gl.attachShader(prog, vsShader);
        gl.attachShader(prog, fsShader);
        gl.bindAttribLocation(prog, 0, 'pos');
        gl.linkProgram(prog);
      
        if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
          throw new Error(gl.getProgramInfoLog(prog));
        }
      
        gl.useProgram(prog);
      
        // Get uniform locations
        const uLoc = {
          tiles: gl.getUniformLocation(prog, 'tiles'),
          layerCount: gl.getUniformLocation(prog, 'layerCount'),
          camYaw: gl.getUniformLocation(prog, 'camYaw'),
          camPitch: gl.getUniformLocation(prog, 'camPitch'),
          camRoll: gl.getUniformLocation(prog, 'camRoll'),
          tanHalfHF: gl.getUniformLocation(prog, 'tanHalfHF'),
          tanHalfVF: gl.getUniformLocation(prog, 'tanHalfVF'),
          pi: gl.getUniformLocation(prog, 'pi'),
          gain: gl.getUniformLocation(prog, 'gain'),
          threshold: gl.getUniformLocation(prog, 'threshold'),
          // NEW:
          layerCenterUV: gl.getUniformLocation(prog, 'layerCenterUV'),
          voronoiBias:   gl.getUniformLocation(prog, 'voronoiBias'),
        };
      
        // Create vertex buffer
        const vao = gl.createVertexArray();
        gl.bindVertexArray(vao);
        const vbo = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, vbo);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 3,-1, -1,3]), gl.STATIC_DRAW);
        gl.enableVertexAttribArray(0);
        gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
      
        // Set uniforms
        const HFOVdeg = 44, VFOVdeg = 73;
        const tanHalfHF = Math.tan(Math.PI * HFOVdeg / 360);
        const tanHalfVF = Math.tan(Math.PI * VFOVdeg / 360);
      
        gl.uniform1f(uLoc.pi, Math.PI);
        gl.uniform1f(uLoc.tanHalfHF, tanHalfHF);
        gl.uniform1f(uLoc.tanHalfVF, tanHalfVF);
        gl.uniform1f(uLoc.threshold, 0.7);
      
        // NEW: set voronoi bias (tune 0.8–1.8). Start at 1.2
        gl.uniform1f(uLoc.voronoiBias, 1.2);
      
        // Apply vignetting correction to each canvas BEFORE uploading to GPU
        const vignetteStrength = parseFloat(sessionStorage.getItem('vignetteCorrection') || '0.8');
        const canvases = layers.map(l => l.canvas);
        
        if (vignetteStrength > 0) {
            console.log(`Applying vignetting correction (strength: ${vignetteStrength})`);
            // Process in batches to avoid blocking
            for (let i = 0; i < canvases.length; i++) {
                this.applyVignettingCorrection(canvases[i], vignetteStrength);
                // Yield to browser every 4 images to prevent blocking
                if (i % 4 === 3) {
                    await new Promise(resolve => setTimeout(resolve, 0));
                }
            }
        }
        
        const w = canvases[0].width, h = canvases[0].height;
        const tex = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D_ARRAY, tex);
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
        gl.texParameteri(gl.TEXTURE_2D_ARRAY, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D_ARRAY, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D_ARRAY, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D_ARRAY, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.texStorage3D(gl.TEXTURE_2D_ARRAY, 1, gl.RGBA8, w, h, canvases.length);
      
        // Upload each canvas to texture array
        // Note: We could optimize this further by creating ImageBitmaps from canvases
        // but the canvas data is already in memory so benefit is minimal
        for (let i = 0; i < canvases.length; i++) {
          const data = canvases[i].getContext('2d').getImageData(0, 0, w, h).data;
          gl.texSubImage3D(gl.TEXTURE_2D_ARRAY, 0, 0, 0, i, w, h, 1, gl.RGBA, gl.UNSIGNED_BYTE, data);
        }
      
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D_ARRAY, tex);
        gl.uniform1i(uLoc.tiles, 0);
        gl.uniform1i(uLoc.layerCount, canvases.length);
      
        // Set camera orientations
        const yawArray = new Float32Array(layers.map(l => l.yaw * Math.PI / 180));
        const pitchArray = new Float32Array(layers.map(l => l.pitch * Math.PI / 180));
        const rollArray = new Float32Array(layers.map(l => (l.roll || 0) * Math.PI / 180));
        gl.uniform1fv(uLoc.camYaw, yawArray);
        gl.uniform1fv(uLoc.camPitch, pitchArray);
        gl.uniform1fv(uLoc.camRoll, rollArray);
      
        // Compute exposure gains using iterative optimization
        const computedGains = await this.computeGainCompensation(layers, canvases);
        
        // Shader expects exactly 36 gain values, pad if needed
        const gains = new Float32Array(36);
        gains.fill(1.0); // Default gain
        
        if (computedGains && computedGains.length > 0) {
            for (let i = 0; i < Math.min(computedGains.length, 36); i++) {
                // Ensure valid gain values
                const gain = computedGains[i];
                if (gain > 0 && gain < 10 && !isNaN(gain)) {
                    gains[i] = gain;
                }
            }
            console.log(`Applied ${computedGains.length} computed gains`);
        } else {
            console.warn('No computed gains, using default values');
        }
        
        gl.uniform1fv(uLoc.gain, gains);
      
        // NEW: compute and upload layerCenterUV (ERP centers)
        const centerUV = new Float32Array(36*2);
        for (let i=0;i<layers.length && i<36;i++){
          const [u,v] = this._erpCenterFromYawPitch(layers[i].yaw, layers[i].pitch);
          centerUV[i*2+0] = u;
          centerUV[i*2+1] = v;
        }
        gl.uniform2fv(uLoc.layerCenterUV, centerUV);
      
        // Render
        gl.viewport(0, 0, erpCanvas.width, erpCanvas.height);
        gl.clearColor(0, 0, 0, 1); // Clear to black
        gl.clear(gl.COLOR_BUFFER_BIT);
        gl.drawArrays(gl.TRIANGLES, 0, 3);
      
        // Clean up
        gl.deleteTexture(tex);
        gl.deleteProgram(prog);
        gl.deleteShader(vsShader);
        gl.deleteShader(fsShader);
        gl.deleteBuffer(vbo);
        gl.deleteVertexArray(vao);
      }
      

    // Post-stitch exposure equalization (homomorphic, GPU, 1 pass after mipmap)
    async postExposureEqualizeERP(erpCanvas, {
      strength = 0.85,    // 0..1 how strongly to flatten exposure
      kernelPx = 512,     // target blur scale (bigger = smoother)
      minGain = 0.6,
      maxGain = 1.6
    } = {}) {
      // Output canvas
      const out = document.createElement('canvas');
      out.width = erpCanvas.width;
      out.height = erpCanvas.height;

      const gl = out.getContext('webgl2', { premultipliedAlpha: false, preserveDrawingBuffer: true });
      if (!gl) {
        console.warn('WebGL2 not available; skipping exposure equalization');
        // Fallback: just draw original
        out.getContext('2d').drawImage(erpCanvas, 0, 0);
        return out;
      }

      // --- shaders ---
      const vs = `#version 300 es
      layout(location=0) in vec2 pos;
      out vec2 v_uv;
      void main(){ v_uv = 0.5*pos + 0.5; gl_Position = vec4(pos,0,1); }`;

      const fs = `#version 300 es
      precision highp float;
      in vec2 v_uv;
      out vec4 frag;

      uniform sampler2D src;
      uniform float strength;
      uniform float lod;       // blur level
      uniform float lodMax;    // 1x1 mip level
      uniform float minGain;
      uniform float maxGain;

      vec3 toLin(vec3 c){ return pow(c, vec3(2.2)); }
      vec3 toSRGB(vec3 c){ return pow(max(c, vec3(0.0)), vec3(1.0/2.2)); }

      float luma(vec3 lin){ return max(1e-6, dot(lin, vec3(0.2126,0.7152,0.0722))); }

      void main(){
        // original (sRGB→linear)
        vec3 srgb = texture(src, v_uv).rgb;
        vec3 lin  = toLin(srgb);
        float logL = log(luma(lin));

        // low-frequency log-luminance via mipmaps
        // local smooth
        vec3 srgbLow = textureLod(src, v_uv, lod).rgb;
        float logSlow = log(luma(toLin(srgbLow)));

        // global mean (approx) from 1x1 mip
        vec3 srgbGlob = textureLod(src, v_uv, lodMax).rgb;
        float logSglob = log(luma(toLin(srgbGlob)));

        // correction in log-domain, clamped as gain
        float corr = -(logSlow - logSglob) * strength;
        float gain = clamp(exp(corr), minGain, maxGain);

        vec3 outLin = lin * gain;
        frag = vec4(toSRGB(outLin), 1.0);
      }`;

      // --- compile/link ---
      const compile = (type, src) => {
        const sh = gl.createShader(type);
        gl.shaderSource(sh, src);
        gl.compileShader(sh);
        if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
          throw new Error(gl.getShaderInfoLog(sh));
        }
        return sh;
      };
      const link = (vsSrc, fsSrc) => {
        const p = gl.createProgram();
        const v = compile(gl.VERTEX_SHADER, vsSrc);
        const f = compile(gl.FRAGMENT_SHADER, fsSrc);
        gl.attachShader(p, v); gl.attachShader(p, f);
        gl.bindAttribLocation(p, 0, 'pos');
        gl.linkProgram(p);
        if (!gl.getProgramParameter(p, gl.LINK_STATUS)) {
          throw new Error(gl.getProgramInfoLog(p));
        }
        gl.deleteShader(v); gl.deleteShader(f);
        return p;
      };

      // --- fullscreen tri ---
      const vao = gl.createVertexArray(); gl.bindVertexArray(vao);
      const vbo = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, vbo);
      gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 3,-1, -1,3]), gl.STATIC_DRAW);
      gl.enableVertexAttribArray(0);
      gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);

      // --- source texture from erpCanvas ---
      const tex = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, tex);
      // sized internal format path (WebGL2). If some browsers complain, fallback below
      try {
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA8, gl.RGBA, gl.UNSIGNED_BYTE, erpCanvas);
      } catch(e) {
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, erpCanvas);
      }
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.generateMipmap(gl.TEXTURE_2D);

      const maxDim = Math.max(out.width, out.height);
      const lod = Math.max(0, Math.log2(maxDim / Math.max(1, kernelPx)));
      const lodMax = Math.floor(Math.log2(maxDim));

      // --- program & uniforms ---
      const prog = link(vs, fs);
      gl.useProgram(prog);
      const u = {
        src:        gl.getUniformLocation(prog, 'src'),
        strength:   gl.getUniformLocation(prog, 'strength'),
        lod:        gl.getUniformLocation(prog, 'lod'),
        lodMax:     gl.getUniformLocation(prog, 'lodMax'),
        minGain:    gl.getUniformLocation(prog, 'minGain'),
        maxGain:    gl.getUniformLocation(prog, 'maxGain'),
      };

      gl.activeTexture(gl.TEXTURE0);
      gl.bindTexture(gl.TEXTURE_2D, tex);
      gl.uniform1i(u.src, 0);
      gl.uniform1f(u.strength, strength);
      gl.uniform1f(u.lod, lod);
      gl.uniform1f(u.lodMax, lodMax);
      gl.uniform1f(u.minGain, minGain);
      gl.uniform1f(u.maxGain, maxGain);

      // --- draw to 'out' canvas framebuffer (default) ---
      gl.viewport(0, 0, out.width, out.height);
      gl.drawArrays(gl.TRIANGLES, 0, 3);

      // cleanup
      gl.deleteTexture(tex);
      gl.deleteBuffer(vbo);
      gl.deleteVertexArray(vao);
      gl.deleteProgram(prog);

      return out;
    }

    /**
     * Apply multi-band blending to smooth seams
     * Simpler implementation: combine high frequencies from best-pixel with low frequencies from smooth blend
     */
    async applyMultiBandBlending(gl, erpCanvas, layers) {
        // Skip multi-band blending if disabled
        const disableMultiband = sessionStorage.getItem('disableMultibandBlending') === 'true';
        if (disableMultiband) {
            console.log('Multi-band blending disabled');
            return erpCanvas;
        }
        
        // Check if we have WebGL2 context
        if (!gl) {
            console.warn('No WebGL2 context for multi-band blending');
            return erpCanvas;
        }
        
        console.log('Applying simplified multi-band blending...');
        
        const width = erpCanvas.width;
        const height = erpCanvas.height;
        
        // Simple frequency-based blending shader
        const vs = `#version 300 es
        layout(location=0) in vec2 pos;
        out vec2 v_uv;
        void main(){ 
            v_uv = 0.5 * pos + 0.5; 
            gl_Position = vec4(pos, 0, 1); 
        }`;
        
        const fs = `#version 300 es
        precision highp float;
        in vec2 v_uv;
        out vec4 frag;
        
        uniform sampler2D original;  // Best-pixel result (sharp)
        uniform float blendStrength; // How much to blend (0-1)
        
        void main() {
            // Sample the original at different mipmap levels for frequency decomposition
            vec3 sharp = texture(original, v_uv).rgb;                    // Level 0: Full detail
            vec3 medium = textureLod(original, v_uv, 2.0).rgb;          // Level 2: Medium frequencies
            vec3 blurred = textureLod(original, v_uv, 4.0).rgb;         // Level 4: Low frequencies
            vec3 veryBlurred = textureLod(original, v_uv, 6.0).rgb;     // Level 6: Very low frequencies
            vec3 superBlurred = textureLod(original, v_uv, 8.0).rgb;    // Level 8: Super smooth
            
            // Detect sky regions (bright areas at top of image)
            float brightness = dot(sharp, vec3(0.299, 0.587, 0.114));
            float isSky = smoothstep(0.25, 0.45, v_uv.y) * smoothstep(0.4, 0.7, brightness);
            
            // Adaptive blending strength - much stronger for sky
            float adaptiveStrength = mix(blendStrength, min(1.0, blendStrength * 1.5), isSky);
            
            // Extract frequency bands
            vec3 highFreq = sharp - medium;              // Highest detail
            vec3 midFreq = medium - blurred;             // Medium detail
            vec3 lowFreq = blurred - veryBlurred;        // Low detail
            vec3 baseFreq = mix(veryBlurred, superBlurred, isSky);  // Use super smooth for sky
            
            // Reconstruct with progressive blending
            vec3 result = baseFreq;
            result += lowFreq * (1.0 - adaptiveStrength * 0.5);    // Blend low frequencies more
            result += midFreq * (1.0 - adaptiveStrength * 0.3);    // Blend mid frequencies
            result += highFreq * (1.0 - adaptiveStrength * 0.1);   // Even blend high freq slightly in sky
            
            // Ensure we don't lose too much contrast (less contrast boost for sky)
            float contrast = mix(1.05, 1.0, isSky);
            result = mix(vec3(0.5), result, contrast);
            
            frag = vec4(result, 1.0);
        }`;
        
        // Compile and link shader program
        const prog = this.compileProgram(gl, vs, fs);
        if (!prog) {
            console.warn('Failed to compile multi-band blending shader');
            return erpCanvas;
        }
        
        // Create texture from original with mipmaps for frequency decomposition
        const originalTex = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, originalTex);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, erpCanvas);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.generateMipmap(gl.TEXTURE_2D);
        
        // Setup rendering
        gl.useProgram(prog);
        
        // Create vertex buffer (full-screen triangle)
        const vao = gl.createVertexArray();
        gl.bindVertexArray(vao);
        const vbo = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, vbo);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 3,-1, -1,3]), gl.STATIC_DRAW);
        gl.enableVertexAttribArray(0);
        gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
        
        // Set uniforms
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, originalTex);
        gl.uniform1i(gl.getUniformLocation(prog, 'original'), 0);
        gl.uniform1f(gl.getUniformLocation(prog, 'blendStrength'), 0.85); // Increased for smoother sky blending
        
        // Create output canvas and render
        const outCanvas = document.createElement('canvas');
        outCanvas.width = width;
        outCanvas.height = height;
        const outCtx = outCanvas.getContext('2d');
        
        // Render to screen (we'll read it back)
        gl.viewport(0, 0, width, height);
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        gl.clearColor(0, 0, 0, 1);
        gl.clear(gl.COLOR_BUFFER_BIT);
        gl.drawArrays(gl.TRIANGLES, 0, 3);
        
        // Read back result (no flip needed - the copy shader will handle it)
        const pixels = new Uint8Array(width * height * 4);
        gl.readPixels(0, 0, width, height, gl.RGBA, gl.UNSIGNED_BYTE, pixels);
        
        const imageData = new ImageData(new Uint8ClampedArray(pixels), width, height);
        outCtx.putImageData(imageData, 0, 0);
        
        // Cleanup
        gl.deleteTexture(originalTex);
        gl.deleteProgram(prog);
        gl.deleteBuffer(vbo);
        gl.deleteVertexArray(vao);
        
        console.log('Simplified multi-band blending applied');
        return outCanvas;
    }
    
    /**
     * Helper to compile shader program
     */
    compileProgram(gl, vsSrc, fsSrc) {
        const vs = gl.createShader(gl.VERTEX_SHADER);
        gl.shaderSource(vs, vsSrc);
        gl.compileShader(vs);
        if (!gl.getShaderParameter(vs, gl.COMPILE_STATUS)) {
            console.error('VS error:', gl.getShaderInfoLog(vs));
            return null;
        }
        
        const fs = gl.createShader(gl.FRAGMENT_SHADER);
        gl.shaderSource(fs, fsSrc);
        gl.compileShader(fs);
        if (!gl.getShaderParameter(fs, gl.COMPILE_STATUS)) {
            console.error('FS error:', gl.getShaderInfoLog(fs));
            return null;
        }
        
        const prog = gl.createProgram();
        gl.attachShader(prog, vs);
        gl.attachShader(prog, fs);
        gl.linkProgram(prog);
        
        if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
            console.error('Link error:', gl.getProgramInfoLog(prog));
            return null;
        }
        
        gl.deleteShader(vs);
        gl.deleteShader(fs);
        return prog;
    }

    _erpCenterFromYawPitch(yawDeg, pitchDeg){
        const yaw = yawDeg * Math.PI/180;
        const pitch = pitchDeg * Math.PI/180;
        // camera forward direction in world:
        // (sin yaw * cos pitch, sin pitch, cos yaw * cos pitch)
        const x = Math.sin(yaw) * Math.cos(pitch);
        const y = Math.sin(pitch);
        const z = Math.cos(yaw) * Math.cos(pitch);
        const lon = Math.atan2(x, z);      // -pi..pi
        const lat = Math.asin(y);          // -pi/2..pi/2
        const u = (lon + Math.PI) / (2*Math.PI); // 0..1
        const v = 0.5 - (lat / Math.PI);         // 0..1
        return [u, v];
    }
    
    /**
     * Clean up WebGL resources and stitching memory
     * Called when opening camera roll from processor card
     */
    cleanupStitchingMemory() {
        console.log('Cleaning up stitching memory');
        
        // Clean up stitched canvas
        if (this.stitchedCanvas) {
            const ctx = this.stitchedCanvas.getContext('2d');
            if (ctx) {
                ctx.clearRect(0, 0, this.stitchedCanvas.width, this.stitchedCanvas.height);
            }
            this.stitchedCanvas.width = 1;
            this.stitchedCanvas.height = 1;
            this.stitchedCanvas = null;
        }
        
        // Clean up WebGL context
        if (this.gl) {
            // Lose the context to free GPU resources
            const loseContext = this.gl.getExtension('WEBGL_lose_context');
            if (loseContext) {
                loseContext.loseContext();
            }
            this.gl = null;
        }
        
        // Clear any temporary canvases
        if (this.tempCanvas) {
            this.tempCanvas.width = 1;
            this.tempCanvas.height = 1;
            this.tempCanvas = null;
        }
        
        // Run garbage collection hint (may not actually trigger GC)
        // Note: window.gc only exists in Chrome with --js-flags="--expose-gc"
        if (typeof window !== 'undefined' && window.gc) {
            window.gc();
        }
    }
      
    animate() {
        requestAnimationFrame(() => this.animate());
        
        if (this.scene) {
            this.scene.render();
        }
    }
    
    /**
     * Lock the processing card UI to prevent user from leaving
     */
    lockProcessingCard() {
        // Find and hide the close button on overlay
        const overlayCloseBtn = this.elements.stitchingOverlay.querySelector('.close-btn');
        if (overlayCloseBtn) {
            overlayCloseBtn.style.display = 'none';
        }
        
        // Disable all buttons in processor card
        const processorCard = document.getElementById('processor-card');
        if (processorCard) {
            // Disable close button
            const cardCloseBtn = processorCard.querySelector('.card-close-btn');
            if (cardCloseBtn) {
                cardCloseBtn.disabled = true;
                cardCloseBtn.style.opacity = '0.3';
                cardCloseBtn.style.cursor = 'not-allowed';
            }
            
            // Disable action buttons (view, share, camera roll, start new)
            const actionButtons = processorCard.querySelectorAll('#processor-actions button');
            actionButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.3';
                btn.style.cursor = 'not-allowed';
            });
        }
        
        // Cancel button removed - stitching is fast enough that canceling isn't needed
        // Prevent page navigation
        this.beforeUnloadHandler = (e) => {
            e.preventDefault();
            e.returnValue = 'Processing in progress. Are you sure you want to leave?';
            return 'Processing in progress. Are you sure you want to leave?';
        };
        window.addEventListener('beforeunload', this.beforeUnloadHandler);
    }
    
    /**
     * Unlock the processing card UI after processing
     */
    unlockProcessingCard() {
        // Restore close button on overlay
        const overlayCloseBtn = this.elements.stitchingOverlay.querySelector('.close-btn');
        if (overlayCloseBtn) {
            overlayCloseBtn.style.display = '';
        }
        
        // Re-enable all buttons in processor card
        const processorCard = document.getElementById('processor-card');
        if (processorCard) {
            // Re-enable close button
            const cardCloseBtn = processorCard.querySelector('.card-close-btn');
            if (cardCloseBtn) {
                cardCloseBtn.disabled = false;
                cardCloseBtn.style.opacity = '';
                cardCloseBtn.style.cursor = '';
            }
            
            // Re-enable action buttons
            const actionButtons = processorCard.querySelectorAll('#processor-actions button');
            actionButtons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '';
                btn.style.cursor = '';
            });
        }
        
        // Cancel button has been removed - no longer needed
        
        // Remove navigation prevention
        if (this.beforeUnloadHandler) {
            window.removeEventListener('beforeunload', this.beforeUnloadHandler);
            this.beforeUnloadHandler = null;
        }
    }
    
    // Cancel processing method removed - stitching is fast enough that canceling isn't needed
}