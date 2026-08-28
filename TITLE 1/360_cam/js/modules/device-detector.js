// Device and Orientation Detection Module
export class DeviceDetector {
    constructor(app) {
        this.app = app;
        this.isMobile = this.checkIfMobile();
        this.isPortrait = this.checkOrientation();
        this.orientationLocked = false;
        this.desktopWarningShown = false;
        this.orientationWarningVisible = false;
    }

    // Check if device is mobile
    checkIfMobile() {
        // Check multiple indicators for mobile device
        const userAgent = navigator.userAgent || navigator.vendor || window.opera;
        
        // Check for mobile user agents
        const mobileRegex = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i;
        const isMobileUA = mobileRegex.test(userAgent.toLowerCase());
        
        // Check for touch support
        const hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        // Check screen size (mobile devices typically < 768px)
        const isMobileSize = window.innerWidth <= 768;
        
        // Check for mobile-specific features
        const hasMobileFeatures = 'orientation' in window || 'ondeviceorientation' in window;
        
        // Consider it mobile if it has mobile UA AND (touch OR mobile size OR mobile features)
        return isMobileUA && (hasTouch || isMobileSize || hasMobileFeatures);
    }

    // Check current orientation
    checkOrientation() {
        // Use screen.orientation if available
        if (screen.orientation) {
            return screen.orientation.type.includes('portrait');
        }
        // Fallback to window dimensions
        return window.innerHeight > window.innerWidth;
    }

    // Initialize device detection
    async init() {
        // Check if desktop and show warning
        if (!this.isMobile && !this.desktopWarningShown) {
            await this.showDesktopWarning();
        }

        // Set up orientation monitoring
        this.setupOrientationMonitoring();

        // Try to lock orientation to portrait if supported
        await this.lockOrientation();
    }

    // Show desktop warning dialog
    async showDesktopWarning() {
        this.desktopWarningShown = true;
        
        // Hide start screen first
        const startScreen = document.getElementById('start-screen');
        if (startScreen) {
            startScreen.style.display = 'none';
        }

        // Create custom dialog with warning style button (will show grid)
        const result = await this.app.cardUI.showDialog({
            title: '<img src="img/logo_150x150.png" alt="Photosphere Camera Logo" class="logo logo-glow" width="60">',
            message: 'VFT Photosphere Camera is designed for smartphones.\n\nFor the best experience, please access this app from a mobile device.',
            type: 'custom',
            showGrid: true,  // Explicitly show grid for desktop warning
            buttons: [
                {
                    text: 'Learn More',
                    style: 'link',
                    action: () => {
                        window.open('https://vftcam.stanford.edu', '_blank');
                        return false; // Don't close dialog
                    }
                },
                {
                    text: 'Continue Anyway',
                    style: 'warning',
                    action: () => true // Close dialog
                }
            ]
        });

        // If user continues anyway, hide grid and show start screen
        if (result) {
            if (this.app.cardUI) {
                this.app.cardUI.showGridAndToolbar(false);
            }
            // Show start screen again
            const startScreen = document.getElementById('start-screen');
            if (startScreen) {
                startScreen.style.display = 'block';
            }
        }
    }

    // Set up orientation change monitoring
    setupOrientationMonitoring() {
        // Listen for orientation changes
        const handleOrientationChange = () => {
            const wasPortrait = this.isPortrait;
            this.isPortrait = this.checkOrientation();
            
            // Only check orientation on mobile devices
            if (this.isMobile) {
                if (!this.isPortrait && !this.orientationWarningVisible) {
                    this.showOrientationWarning();
                } else if (this.isPortrait && this.orientationWarningVisible) {
                    this.hideOrientationWarning();
                }
            }
        };

        // Use screen.orientation API if available
        if (screen.orientation) {
            screen.orientation.addEventListener('change', handleOrientationChange);
        }
        
        // Also listen to window resize as fallback
        window.addEventListener('resize', handleOrientationChange);
        
        // Also listen to orientationchange event (older API)
        window.addEventListener('orientationchange', handleOrientationChange);

        // Check initial orientation
        handleOrientationChange();
    }

    // Show orientation warning
    showOrientationWarning() {
        this.orientationWarningVisible = true;
        
        // Store what was visible before
        this.previouslyVisibleElements = {
            startScreen: false,
            sceneContainer: false,
            cameraViewport: false,
            stitchingOverlay: false,
            visibleCards: []
        };
        
        // Hide start screen if visible
        const startScreen = document.getElementById('start-screen');
        if (startScreen && startScreen.style.display !== 'none') {
            this.previouslyVisibleElements.startScreen = true;
            startScreen.style.display = 'none';
        }
        
        // Hide capture UI elements
        const sceneContainer = document.getElementById('scene-container');
        if (sceneContainer && sceneContainer.style.display !== 'none') {
            this.previouslyVisibleElements.sceneContainer = true;
            sceneContainer.style.display = 'none';
        }
        
        const cameraViewport = document.getElementById('camera-viewport');
        if (cameraViewport && cameraViewport.style.display !== 'none') {
            this.previouslyVisibleElements.cameraViewport = true;
            cameraViewport.style.display = 'none';
        }
        
        // Hide stitching overlay if visible
        const stitchingOverlay = document.getElementById('stitching-overlay');
        if (stitchingOverlay && stitchingOverlay.classList.contains('visible')) {
            this.previouslyVisibleElements.stitchingOverlay = true;
            stitchingOverlay.classList.remove('visible', 'lifting-in');
            stitchingOverlay.classList.add('pressing-down');
            setTimeout(() => {
                stitchingOverlay.classList.remove('pressing-down');
            }, 300);
        }
        
        // Hide all visible cards (except orientation warning)
        if (this.app.cardUI && this.app.cardUI.cards) {
            Object.entries(this.app.cardUI.cards).forEach(([name, card]) => {
                if (name !== 'orientation-warning' && card && card.classList.contains('visible')) {
                    this.previouslyVisibleElements.visibleCards.push(name);
                    card.classList.remove('visible');
                }
            });
        }
        
        // Hide other UI elements
        const alignmentIndicator = document.getElementById('alignment-indicator');
        if (alignmentIndicator) alignmentIndicator.style.display = 'none';
        
        const instructions = document.getElementById('instructions');
        if (instructions) instructions.style.display = 'none';
        
        const bottomControls = document.querySelector('.bottom-controls');
        if (bottomControls) bottomControls.style.display = 'none';
        
        // Stop capturing
        if (this.app) {
            this.app.setCapturingEnabled(false);
            // Pause camera if it's running
            if (this.app.camera && this.app.camera.stream) {
                this.app.camera.stream.getTracks().forEach(track => {
                    if (track.enabled) {
                        track.enabled = false;
                    }
                });
            }
        }

        // Show grid background
        const gridBackground = document.getElementById('grid-background');
        if (gridBackground) {
            gridBackground.classList.add('visible');
        }

        // Show orientation warning card
        const orientationCard = document.getElementById('orientation-warning-card');
        if (orientationCard) {
            orientationCard.classList.add('visible');
            orientationCard.removeAttribute('aria-hidden');  // Make accessible to screen readers
            orientationCard.style.zIndex = '2000';
        }
    }

    // Hide orientation warning
    hideOrientationWarning() {
        this.orientationWarningVisible = false;
        
        // Restore previously visible elements
        if (this.previouslyVisibleElements) {
            if (this.previouslyVisibleElements.startScreen) {
                const startScreen = document.getElementById('start-screen');
                if (startScreen) startScreen.style.display = 'block';
            }
            
            if (this.previouslyVisibleElements.sceneContainer) {
                const sceneContainer = document.getElementById('scene-container');
                if (sceneContainer) sceneContainer.style.display = 'block';
                
                const alignmentIndicator = document.getElementById('alignment-indicator');
                if (alignmentIndicator) alignmentIndicator.style.display = 'block';
                
                const instructions = document.getElementById('instructions');
                if (instructions) instructions.style.display = 'block';
                
                const bottomControls = document.querySelector('.bottom-controls');
                if (bottomControls) bottomControls.style.display = 'flex';
            }
            
            if (this.previouslyVisibleElements.cameraViewport) {
                const cameraViewport = document.getElementById('camera-viewport');
                if (cameraViewport) cameraViewport.style.display = 'block';
            }
            
            // Restore stitching overlay if it was visible
            if (this.previouslyVisibleElements.stitchingOverlay) {
                const stitchingOverlay = document.getElementById('stitching-overlay');
                if (stitchingOverlay) {
                    stitchingOverlay.classList.remove('pressing-down');
                    stitchingOverlay.classList.add('lifting-in', 'visible');
                    setTimeout(() => {
                        stitchingOverlay.classList.remove('lifting-in');
                    }, 400);
                }
            }
            
            // Restore previously visible cards
            if (this.previouslyVisibleElements.visibleCards && this.app.cardUI && this.app.cardUI.cards) {
                this.previouslyVisibleElements.visibleCards.forEach(cardName => {
                    const card = this.app.cardUI.cards[cardName];
                    if (card) {
                        card.classList.add('visible');
                    }
                });
            }
        }
        
        // Resume capturing
        if (this.app) {
            this.app.setCapturingEnabled(true);
            // Resume camera if it was paused
            if (this.app.camera && this.app.camera.stream) {
                this.app.camera.stream.getTracks().forEach(track => {
                    track.enabled = true;
                });
            }
        }

        // Hide orientation warning card first
        const orientationCard = document.getElementById('orientation-warning-card');
        if (orientationCard) {
            orientationCard.classList.remove('visible');
            orientationCard.setAttribute('aria-hidden', 'true');  // Hide from screen readers
        }

        // Hide grid background (check after hiding orientation card)
        const gridBackground = document.getElementById('grid-background');
        if (gridBackground) {
            // Check if any OTHER cards are visible (not including orientation card)
            const hasOtherCards = this.app.cardUI ? 
                Object.entries(this.app.cardUI.cards)
                    .filter(([name, card]) => name !== 'orientation-warning')
                    .some(([name, card]) => card && card.classList.contains('visible')) 
                : false;
            
            if (!hasOtherCards) {
                gridBackground.classList.remove('visible');
            }
        }
    }

    // Try to lock orientation to portrait
    async lockOrientation() {
        // Only try to lock on mobile devices
        if (!this.isMobile) return;

        try {
            // Modern API
            if (screen.orientation && screen.orientation.lock) {
                await screen.orientation.lock('portrait');
                this.orientationLocked = true;
                console.log('Orientation locked to portrait');
            }
            // Older API (deprecated but still might work)
            else if (screen.lockOrientation) {
                screen.lockOrientation('portrait');
                this.orientationLocked = true;
                console.log('Orientation locked to portrait (legacy API)');
            }
            else if (screen.mozLockOrientation) {
                screen.mozLockOrientation('portrait');
                this.orientationLocked = true;
                console.log('Orientation locked to portrait (Mozilla API)');
            }
            else if (screen.msLockOrientation) {
                screen.msLockOrientation('portrait');
                this.orientationLocked = true;
                console.log('Orientation locked to portrait (MS API)');
            }
        } catch (error) {
            console.log('Could not lock orientation:', error);
            // Orientation lock may require fullscreen or other conditions
            // It's okay if it fails - we still have the warning system
        }
    }

    // Unlock orientation (for cleanup)
    unlockOrientation() {
        try {
            if (screen.orientation && screen.orientation.unlock) {
                screen.orientation.unlock();
            } else if (screen.unlockOrientation) {
                screen.unlockOrientation();
            } else if (screen.mozUnlockOrientation) {
                screen.mozUnlockOrientation();
            } else if (screen.msUnlockOrientation) {
                screen.msUnlockOrientation();
            }
            this.orientationLocked = false;
        } catch (error) {
            console.log('Could not unlock orientation:', error);
        }
    }
}