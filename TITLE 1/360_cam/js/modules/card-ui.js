/**
 * Card UI Management Module - Glass-Morphic Interface System
 * 
 * This module manages the entire card-based user interface for VFTCam, implementing a layered
 * glass-morphic design system with backdrop blur effects. It handles all dialog interactions,
 * navigation between screens, and user confirmations.
 * 
 * ARCHITECTURE:
 * The UI is built on a card metaphor where each "screen" is a semi-transparent card that
 * can stack on top of others. Cards animate in/out with lifting and pressing effects.
 * 
 * KEY CONCEPTS:
 * - CARDS: Semi-transparent panels with backdrop blur that overlay the main interface
 * - Z-INDEX STACKING: Cards increment z-index to ensure proper layering (base: 1002)
 * - GLASS-MORPHISM: Uses backdrop-filter blur for modern glass effect (fallback to opacity)
 * - ANIMATION STATES: lifting-in (appearing), pressing-down (disappearing), visible (shown)
 * - GRID BACKGROUND: Dotted grid pattern shown behind certain dialogs for emphasis
 * - TOOLBAR: Bottom navigation bar with home/gallery/processor buttons
 * 
 * CARD TYPES:
 * 1. HOME CARD: Main navigation hub with capture/stitch/gallery options
 * 2. INFO CARD: Flip-card for settings (about, privacy, FOV, clear data)
 * 3. PROCESSOR CARD: Stitching interface with progress and preview
 * 4. CAMERA ROLL: Gallery of saved photospheres with actions
 * 5. SETTINGS MENU: Quick access to app settings and info
 * 6. DIALOG: Generic alert/confirm/custom dialog system
 * 7. ORIENTATION WARNING: Special dialog for landscape rotation
 * 
 * NAVIGATION FLOW:
 * Start Screen → Home Card → [Capture Mode OR Gallery OR Processor]
 *                    ↓
 *             Settings Menu → Info Cards (various)
 * 
 * DIALOG SYSTEM:
 * The showDialog method provides three types:
 * - alert: Simple OK button
 * - confirm: OK/Cancel buttons  
 * - custom: Arbitrary buttons with styles (link, warning)
 * 
 * ACCESSIBILITY:
 * - All cards have proper ARIA attributes (role="dialog", aria-modal, aria-labelledby)
 * - Cards toggle aria-hidden based on visibility
 * - Screen reader announcements for state changes
 * - Minimum touch targets of 44×44px
 * 
 * MEMORY MANAGEMENT:
 * - Panorama thumbnails loaded on-demand in camera roll
 * - Canvas previews cleaned up after use
 * - Event listeners properly removed when cards close
 * 
 * @module CardUI
 * @requires Database - For loading/saving photospheres
 */

import { Database } from './database.js';

export class CardUI {
    /**
     * Initialize the Card UI system
     * @param {Object} app - Main app instance for accessing camera, database, etc.
     */
    constructor(app) {
        this.app = app;
        this.database = app.database; // Use shared database instance to avoid conflicts
        this.currentCard = null; // Currently visible card name (null means no card)
        this.cameFromCapture = false; // Flag to track navigation from capture mode
        this.cardZIndex = 1002; // Base z-index, incremented for each new card to ensure stacking
        // Cache DOM references for all card elements
        this.cards = {
            'home': document.getElementById('home-card'),
            'info': document.getElementById('info-card'), 
            'processor': document.getElementById('processor-card'),
            'camera-roll': document.getElementById('camera-roll-card'),
            'settings-menu': document.getElementById('settings-menu-card'),
            'dialog': document.getElementById('dialog-card'),
            'orientation-warning': document.getElementById('orientation-warning-card')
        };
        
        // UI chrome elements
        this.gridBackground = document.getElementById('grid-background'); // Dotted grid overlay
        this.toolbar = document.getElementById('custom-toolbar'); // Bottom navigation bar
        this.settingsGearBtn = document.getElementById('settings-gear-btn'); // Floating settings button
        
        // Content definitions for info card sections - each can be shown independently
        this.infoContent = {
            about: {
                icon: '/360_cam/img/about.svg',
                title: 'Innovatech PH 360 Camera',
                content: `
                    <h3 style="color: white; margin-bottom: 10px;">360° Campus Panorama Capture</h3>
                    <p style="margin-bottom: 15px;">Innovatech PH 360 Camera is part of the AI-Assisted AR 360° Virtual Campus Navigation and Information System for Educational Institutions.</p>

                    <h3 style="color: white; margin-bottom: 10px;">How It Works</h3>
                    <p style="margin-bottom: 15px;">The app uses your device's gyroscope and compass to guide you through capturing 36 precisely positioned images. These images are then stitched together to create a seamless equirectangular panorama.</p>

                    <h3 style="color: white; margin-bottom: 10px;">Features</h3>
                    <ul style="margin-left: 15px; margin-bottom: 15px;">
                        <li>Real-time 3D preview with hotspot guidance</li>
                        <li>Automatic image capture when aligned</li>
                        <li>High-resolution spherical projection</li>
                        <li>Built-in panorama viewer</li>
                        <li>Integrated with Innovatech PH campus navigation system</li>
                    </ul>

                    <h3 style="color: white; margin-bottom: 10px;">Version</h3>
                    <p style="margin-bottom: 15px;">Innovatech 360 Camera v1.0.0</p>

                    <h3 style="color: white; margin-bottom: 10px;">Innovatech PH</h3>
                    <p style="margin-bottom: 15px;">
                        Innovatech PH 360 Camera is part of the AI-Assisted AR 360° Virtual Campus Navigation and Information System.
                        Designed for educational institutions to create immersive campus tours and navigation experiences.
                    </p>
                    <p style="margin-bottom: 15px;text-align: center;">Innovatech PH 360 Camera<br/>&copy; 2025 Innovatech PH<br/>All Rights Reserved</p>
                `
            },
            privacy: {
                icon: '/360_cam/img/privacy.svg',
                title: 'Privacy Policy',
                content: `
                    <p style="margin-bottom: 20px; color: rgba(255,255,255,0.9);">Your privacy is our priority. Innovatech PH 360 Camera is designed to keep your data secure and private.</p>

                    <h3 style="color: white; margin-bottom: 10px;">Your Data Stays Local</h3>
                    <p style="margin-bottom: 20px;">Innovatech PH 360 Camera operates entirely within your web browser. All captured images and generated panoramas remain on your device. We have no access to your photos or any data you create using the app.</p>

                    <h3 style="color: white; margin-bottom: 10px;">No Cloud Uploads</h3>
                    <ul style="margin-left: 15px; margin-bottom: 10px; list-style-type: disc;">
                        <li>All image processing happens locally using your device's GPU through WebGL2</li>
                        <li>No photos are ever transmitted to external servers</li>
                        <li>All data is stored in your browser's local storage (IndexedDB)</li>
                        <li>The app works completely offline once loaded</li>
                    </ul>
                    
                    <h3 style="color: white; margin-bottom: 10px; margin-top: 20px;"><img src="/360_cam/img/compass.svg" style="width: 16px; height: 16px; filter: brightness(0) invert(1);" alt=""/> Sensor Data Usage</h3>
                    <p style="margin-bottom: 10px;">The app uses your device's orientation sensors solely for real-time capture guidance. This sensor data is:</p>
                    <ul style="margin-left: 15px; margin-bottom: 10px; list-style-type: disc;">
                        <li>Used only to help you align with capture points</li>
                        <li>Processed in real-time and not stored after stitching</li>
                        <li>Never transmitted to any external service</li>
                        <li>Required only during active capture sessions</li>
                    </ul>
                    
                    <h3 style="color: white; margin-bottom: 10px; margin-top: 20px;"><img src="/360_cam/img/camera.svg" style="width: 16px; height: 16px; filter: brightness(0) invert(1);" alt=""/> Camera Permissions</h3>
                    <p style="margin-bottom: 10px;">Camera access is required to capture photos. The permission:</p>
                    <ul style="margin-left: 15px; margin-bottom: 10px; list-style-type: disc;">
                        <li>Is requested only when you start capturing</li>
                        <li>Can be revoked at any time through browser settings</li>
                        <li>Is used solely for capturing images within the app</li>
                        <li>Does not allow background access to your camera</li>
                    </ul>
                    
                    <h3 style="color: white; margin-bottom: 10px; margin-top: 20px;"><img src="/360_cam/img/trash.svg" style="width: 16px; height: 16px; filter: brightness(0) invert(1);" alt=""/> Data Control</h3>
                    <p style="margin-bottom: 10px;">You have complete control over your data:</p>
                    <ul style="margin-left: 15px; margin-bottom: 10px; list-style-type: disc;">
                        <li>Delete individual photospheres through the camera roll</li>
                        <li>Clear all data using "Clear Camera Roll" option</li>
                        <li>Browser data clearing will remove all app data</li>
                        <li>Uninstalling the PWA removes all associated data</li>
                    </ul>
                    
                    <h3 style="color: white; margin-bottom: 10px; margin-top: 20px;"><img src="/360_cam/img/box-arrow-up.svg" style="width: 16px; height: 16px; filter: brightness(0) invert(1);" alt=""/> Sharing Features</h3>
                    <p style="margin-bottom: 10px;">When you choose to share a photosphere:</p>
                    <ul style="margin-left: 15px; margin-bottom: 10px; list-style-type: disc;">
                        <li>The app uses your device's native share functionality</li>
                        <li>You control which apps or services receive the image</li>
                        <li>Images include metadata for VR/photosphere viewers</li>
                        <li>No analytics or tracking is performed on shared content</li>
                    </ul>
                    
                    <h3 style="color: white; margin-bottom: 10px; margin-top: 20px;"><img src="/360_cam/img/geo-alt.svg" style="width: 16px; height: 16px; filter: brightness(0) invert(1);" alt=""/> Location Data</h3>
                    <p style="margin-bottom: 10px;">The app can optionally add GPS metadata if you grant permission:</p>
                    <ul style="margin-left: 15px; margin-bottom: 10px; list-style-type: disc;">
                        <li>Location access is completely optional</li>
                        <li>GPS data is only added if you explicitly allow location access</li>
                        <li>Location information stays within the image file</li>
                        <li>No location data is transmitted to external servers</li>
                    </ul>

                    <h3 style="color: white; margin-bottom: 10px; margin-top: 20px;">Contact</h3>
                    <p style="margin-bottom: 10px;">Questions about this app?<br/>Contact:</p>
                    <p style="margin-bottom: 10px;">Innovatech PH<br>
                    AI-Assisted AR 360° Virtual Campus Navigation</p>

                    <p style="margin-top: 25px; font-size: 12px; color: rgba(255,255,255,0.6);">Last updated: August 2025<br>
                    This policy applies to Innovatech PH 360 Camera</p>
                `
            },
            fov: {
                icon: './img/fov-calibration.svg',
                title: 'FOV Fine-Tuning',
                content: `
                    <h3 style="color: white; margin-bottom: 10px;">Field of View Adjustment</h3>
                    <p style="margin-bottom: 15px;">If your panoramas aren't stitching perfectly, you can fine-tune the FOV setting. Small adjustments can improve alignment where images overlap.</p>
                    
                    <div style="background: rgba(255,255,255,0.1); border-radius: 10px; padding: 15px; margin: 15px 0; border: 1px solid rgba(255,255,255,0.15);">
                        <label for="fovSlider" style="color: rgba(255,255,255,0.8); font-size: 14px; display: block; margin-bottom: 8px;">
                            FOV Adjustment: <span id="fovValue">0</span>°
                        </label>
                        <input type="range" 
                               id="fovSlider" 
                               style="width: 100%;"
                               min="-5" 
                               max="5" 
                               value="0"
                               step="0.5"
                               oninput="window.sphereCapture && window.sphereCapture.cardUI && window.sphereCapture.cardUI.updateFOV(this.value)">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 5px;">
                            <span>-5°</span>
                            <span style="position: absolute; left: 50%; transform: translateX(-50%);">0°</span>
                            <span>+5°</span>
                        </div>
                    </div>
                    
                    <h3 style="color: white; margin-bottom: 10px;">Tips</h3>
                    <p style="margin-bottom: 15px;">• Start with 0° (default setting)<br>
                    • If images overlap too much: try positive values (+1 to +3)<br>
                    • If gaps appear between images: try negative values (-1 to -3)<br>
                    • Test with a new capture after adjusting</p>
                    
                    <button style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; color: white; padding: 10px 20px; margin-top: 15px; cursor: pointer; width: 100%;" onclick="window.sphereCapture && window.sphereCapture.cardUI && window.sphereCapture.cardUI.saveFOV()">
                        Save FOV Adjustment
                    </button>
                `
            },
            clear: {
                icon: './img/clear-camera-roll.svg',
                title: 'Clear Camera Roll',
                content: `
                    <h3 style="color: white; margin-bottom: 10px;">Delete All Photospheres</h3>
                    <p style="margin-bottom: 15px;">This will permanently delete all captured photospheres from your device's storage.</p>
                    
                    <div style="background: rgba(255,100,100,1); border: 1px solid rgba(255,100,100,1); border-radius: 10px; padding: 15px; margin: 20px 0;">
                        <p style="color: #ffffff; font-size: 14px;">
                            ⚠️ <strong>Warning:</strong> This action cannot be undone. All your photospheres will be permanently deleted.
                        </p>
                    </div>
                    
                    <h3 style="color: white; margin-bottom: 10px;">Current Storage</h3>
                    <p id="storage-info" style="margin-bottom: 15px;">Loading storage information...</p>
                    
                    <button class="danger" style="background: linear-gradient(135deg, rgba(255, 100, 100, 0.3), rgba(255, 100, 100, 0.2)); border: 1px solid rgba(255, 100, 100, 0.4); border-radius: 10px; color: white; padding: 12px 20px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; margin-top: 15px;" onclick="window.sphereCapture && window.sphereCapture.cardUI && window.sphereCapture.cardUI.confirmClearRoll()">
                        Delete All Photospheres
                    </button>
                `
            }
        };
    }

    /**
     * Initialize the UI - load camera roll data and show home card
     * Called after DOM is ready and database is initialized
     */
    async init() {
        await this.loadCameraRoll();
        this.showCard('home');
    }

    /**
     * Show a card with lifting animation
     * Cards stack on top of each other with increasing z-index
     * @param {string} cardName - Name of card to show (home, info, processor, etc.)
     */
    showCard(cardName) {
        const newCard = this.cards[cardName];
        if (!newCard) return;
        
        // IMPORTANT: Don't hide start screen here - cards overlay on top
        // Start screen is only hidden when entering capture mode
        
        // Reset info card flip state when showing
        if (cardName === 'info') {
            const infoCardInner = document.querySelector('.info-card-inner');
            if (infoCardInner) {
                infoCardInner.classList.remove('flipped');
            }
        }
        
        // Show navigation UI (grid and toolbar)
        this.showGridAndToolbar(true);
        
        // Apply animation classes for smooth entry
        newCard.classList.remove('background', 'pressing-down');
        newCard.classList.add('lifting-in', 'visible');
        
        // Make card accessible to screen readers
        newCard.removeAttribute('aria-hidden');
        
        // Stack new card on top by incrementing z-index
        this.cardZIndex++;
        newCard.style.zIndex = this.cardZIndex;
        
        // Remove lifting animation class after animation completes
        setTimeout(() => {
            newCard.classList.remove('lifting-in');
        }, 250);
        
        // Load camera roll data when showing gallery
        if (cardName === 'camera-roll') {
            this.loadCameraRoll();
        }
    }

    /**
     * Close a card and handle navigation
     * Special handling for returning from cards to capture mode
     * @param {string} cardName - Name of card to close
     */
    async closeCard(cardName) {
        // Special case: returning to capture mode from a card
        if (this.cameFromCapture) {
            this.cameFromCapture = false;
            this.closeAllCards();
            document.getElementById('scene-container').style.display = 'block';
            document.getElementById('camera-viewport').style.display = 'block';
            document.getElementById('alignment-indicator').style.display = 'block';
            document.getElementById('instructions').style.display = 'block';
            document.querySelector('.bottom-controls').style.display = 'flex';
            this.settingsGearBtn.style.display = 'flex';
            if (this.app && this.app.camera) {
                // Only start camera if not already running
                if (!this.app.camera.stream) {
                    await this.app.camera.startCamera();
                }
                if (this.app.camera.videoElement) {
                   this.app.camera.videoElement.play().catch(e => console.error("Video play failed after closing card", e));
                }
            }
            return;
        }
        
        if (cardName === 'home') {
            this.closeAllCards();
            const startScreen = document.getElementById('start-screen');
            if (startScreen) {
                startScreen.style.display = 'block';
            }
        } else {
            this.showCard('home');
        }
    }
    
    /**
     * Close all visible cards with press-down animation
     * Used when transitioning to capture mode or clearing the UI
     */
    closeAllCards() {
        Object.keys(this.cards).forEach(cardName => {
            const card = this.cards[cardName];
            if (card.classList.contains('visible') || card.classList.contains('background')) {
                card.classList.add('pressing-down');
                card.classList.remove('visible', 'lifting-in', 'background');
                
                // Hide from screen readers when not visible
                card.setAttribute('aria-hidden', 'true');
                
                setTimeout(() => {
                    card.classList.remove('pressing-down');
                }, 200);
            }
        });
        this.currentCard = null;
        this.showGridAndToolbar(false);
    }

    /**
     * Toggle visibility of grid background and bottom toolbar
     * Grid is shown behind cards for navigation context
     * @param {boolean} show - Whether to show or hide the UI chrome
     */
    showGridAndToolbar(show) {
        if (this.gridBackground) {
            if (show) {
                this.gridBackground.classList.add('visible');
            } else {
                this.gridBackground.classList.remove('visible');
            }
        }
        if (this.toolbar) {
            if (show) {
                this.toolbar.classList.add('visible');
            } else {
                this.toolbar.classList.remove('visible');
            }
        }
    }

    /**
     * Transition from card UI to capture mode
     * Hides all cards, shows 3D scene and camera viewport
     * Starts camera stream and orientation tracking
     */
    async startCapture() {
        console.log('startCapture called');
        
        this.closeAllCards();
        
        const startScreen = document.getElementById('start-screen');
        if (startScreen) {
            startScreen.style.display = 'none';
        }
        
        if (this.settingsGearBtn) {
            this.settingsGearBtn.style.display = 'flex';
        }
        
        const sceneContainer = document.getElementById('scene-container');
        const cameraViewport = document.getElementById('camera-viewport');
        const alignmentIndicator = document.getElementById('alignment-indicator');
        const instructions = document.getElementById('instructions');
        
        // Show capture UI elements and make them accessible
        if (sceneContainer) {
            sceneContainer.style.display = 'block';
            sceneContainer.removeAttribute('aria-hidden');
        }
        if (cameraViewport) {
            cameraViewport.style.display = 'block';
        }
        if (alignmentIndicator) {
            alignmentIndicator.style.display = 'block';
        }
        if (instructions) {
            instructions.style.display = 'block';
            instructions.removeAttribute('aria-hidden');
        }
        const bottomControls = document.querySelector('.bottom-controls');
        if (bottomControls) {
            bottomControls.style.display = 'flex';
        }
        
        if (this.app && this.app.camera) {
            console.log('Starting camera...');
            await this.app.camera.startCamera();
            if (this.app.camera.videoElement) {
                this.app.camera.videoElement.play().catch(e => console.error("Video play failed", e));
            }
        } else {
            console.error('app or camera not initialized');
        }
    }

    /**
     * Show the processor/stitching card
     * Displays either empty state, ready state, or completed panorama
     * Handles the entire stitching workflow UI
     */
    async showProcessorCard() {
        this.showCard('processor');
        
        const capturedData = await this.database.loadCapturedImages();
        
        const emptyState = document.getElementById('processor-empty');
        const statusDiv = document.querySelector('#processor-status > :not(#processor-empty)');
        const actionsDiv = document.getElementById('processor-actions');
        const previewCanvas = document.getElementById('preview-canvas');
        
        if (capturedData.length === 0) {
            emptyState.style.display = 'block';
            const stitchingProgress = document.querySelector('#processor-status .stitching-progress');
            const processorH2 = document.querySelector('#processor-status h2');
            if (stitchingProgress) stitchingProgress.style.display = 'none';
            if (processorH2) processorH2.style.display = 'none';
            if (previewCanvas) previewCanvas.style.display = 'none';
            if (actionsDiv) actionsDiv.style.display = 'none';
        } else {
            emptyState.style.display = 'none';
            if (statusDiv) {
                document.querySelector('.stitching-progress').style.display = 'block';
                document.querySelector('#processor-status h2').style.display = 'block';
            }
            
            if (this.app?.lastStitchedPanorama || (previewCanvas && previewCanvas.width > 0 && previewCanvas.height > 0)) {
                document.getElementById('processor-status-text').textContent = 'Panorama Complete!';
                document.getElementById('processor-details').textContent = `Created from ${capturedData.length} images`;
                document.getElementById('processor-progress-bar').style.width = '100%';
                previewCanvas.style.display = 'block';
                actionsDiv.style.display = 'flex';
                this.setupProcessorButtons();
            } else {
                document.getElementById('processor-status-text').textContent = 'Ready to process';
                document.getElementById('processor-details').textContent = `${capturedData.length} images captured`;
                document.getElementById('processor-progress-bar').style.width = '0%';
                
                const startStitchBtn = document.createElement('button');
                startStitchBtn.textContent = 'Create Photosphere';
                startStitchBtn.style.cssText = 'width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 12px; color: white; font-weight: 600; cursor: pointer; margin-top: 20px;';
                startStitchBtn.onclick = () => {
                    startStitchBtn.remove();
                    this.startProcessingInCard();
                };
                document.querySelector('#processor-status').appendChild(startStitchBtn);
            }
        }
    }
    
    /**
     * Set up view and download buttons for completed panorama
     * Called after successful stitching to enable user actions
     */
    setupProcessorButtons() {
        const previewCanvas = document.getElementById('preview-canvas');
        const viewBtn = document.getElementById('view-btn');
        const downloadBtn = document.getElementById('download-btn');
        
        // Make the preview canvas clickable to view the panorama
        previewCanvas.style.cursor = 'pointer';
        previewCanvas.title = 'Click to view panorama';
        previewCanvas.onclick = () => {
            if (this.app && this.app.lastStitchedPanorama) {
                this.app.viewPanorama(this.app.lastStitchedPanorama);
            } else if (previewCanvas.width > 0) {
                this.app.viewPanorama(previewCanvas);
            }
        };
        
        viewBtn.onclick = () => {
            if (this.app && this.app.lastStitchedPanorama) {
                this.app.viewPanorama(this.app.lastStitchedPanorama);
            } else if (previewCanvas.width > 0) {
                this.app.viewPanorama(previewCanvas);
            }
        };
        
        downloadBtn.onclick = () => {
            const canvas = this.app?.lastStitchedPanorama || previewCanvas;
            if (canvas && canvas.width > 0) {
                canvas.toBlob((blob) => {
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
                    link.download = `photosphere_${timestamp}.jpg`;
                    link.href = url;
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 100);
                }, 'image/jpeg', 0.95);
            }
        };
    }
    
    /**
     * Start stitching process within the processor card
     * Hijacks the main stitching overlay to show progress in card instead
     * Updates progress bar and status text in real-time
     */
    async startProcessingInCard() {
        const progressBar = document.getElementById('processor-progress-bar');
        const statusText = document.getElementById('processor-status-text');
        const detailsText = document.getElementById('processor-details');
        const previewCanvas = document.getElementById('preview-canvas');
        const actionsDiv = document.getElementById('processor-actions');
        
        // Disable all buttons during processing
        const processorCard = document.getElementById('processor-card');
        if (processorCard) {
            // Disable close button
            const cardCloseBtn = processorCard.querySelector('.card-close-btn');
            if (cardCloseBtn) {
                cardCloseBtn.disabled = true;
                cardCloseBtn.style.opacity = '0.3';
                cardCloseBtn.style.cursor = 'not-allowed';
            }
            
            // Disable action buttons if they exist
            const actionButtons = processorCard.querySelectorAll('#processor-actions button');
            actionButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.3';
                btn.style.cursor = 'not-allowed';
            });
        }
        
        try {
            previewCanvas.style.display = 'block';
            previewCanvas.style.cursor = 'default';  // Reset cursor during processing
            previewCanvas.onclick = null;  // Remove click handler during processing
            statusText.textContent = 'Starting processing...';
            progressBar.style.width = '10%';
            
            const originalOverlay = document.getElementById('stitching-overlay');
            if (originalOverlay) {
                const originalDisplay = originalOverlay.style.display;
                Object.defineProperty(originalOverlay.style, 'display', {
                    configurable: true,
                    enumerable: true,
                    set: function(value) { return; },
                    get: function() { return 'none'; }
                });
                
                const updateInterval = setInterval(() => {
                    const origProgressBar = document.getElementById('stitch-progress-bar');
                    const origStatusText = document.getElementById('stitch-status');
                    const origStatusDetails = document.getElementById('stitch-details');
                    
                    if (origProgressBar?.style.width) progressBar.style.width = origProgressBar.style.width;
                    if (origStatusText?.textContent) statusText.textContent = origStatusText.textContent;
                    if (origStatusDetails?.textContent) detailsText.textContent = origStatusDetails.textContent;
                }, 100);
                
                await this.app.startBestPixelStitching();
                
                clearInterval(updateInterval);
                
                Object.defineProperty(originalOverlay.style, 'display', {
                    configurable: true,
                    enumerable: true,
                    writable: true,
                    value: originalDisplay
                });
            } else {
                await this.app.startBestPixelStitching();
            }
            
            if (this.app.lastStitchedPanorama) {
                const ctx = previewCanvas.getContext('2d');
                const source = this.app.lastStitchedPanorama;
                const scale = Math.min(previewCanvas.width / source.width, previewCanvas.height / source.height);
                const width = source.width * scale;
                const height = source.height * scale;
                const x = (previewCanvas.width - width) / 2;
                const y = (previewCanvas.height - height) / 2;
                ctx.clearRect(0, 0, previewCanvas.width, previewCanvas.height);
                ctx.drawImage(source, x, y, width, height);
            }
            
            statusText.textContent = 'Panorama Complete!';
            progressBar.style.width = '100%';
            this.setupProcessorButtons();
            actionsDiv.style.display = 'flex';
        } catch (error) {
            console.error('Stitching failed:', error);
            statusText.textContent = 'Processing failed';
            detailsText.textContent = error.message || 'Unknown error occurred';
            progressBar.style.width = '0%';
        } finally {
            // Re-enable all buttons after processing
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
        }
    }

    /**
     * Get service worker version
     */
    async getServiceWorkerVersion() {
        try {
            // Try to fetch the service worker file
            const response = await fetch('/service-worker.js');
            const text = await response.text();
            
            // Extract version from the header comment
            const versionMatch = text.match(/Version:\s*([\d.]+)/);
            if (versionMatch) {
                return versionMatch[1];
            }
        } catch (error) {
            console.error('Could not fetch service worker version:', error);
        }
        return '1.0.0'; // Default fallback
    }

    /**
     * Show specific content in the info card
     * Loads content from infoContent definitions
     * @param {string} section - Section to show (about, privacy, fov, clear)
     */
    async showInfoDetails(section) {
        // Get the info card elements
        const titleElement = document.getElementById('info-card-title');
        const contentElement = document.getElementById('info-card-content');
        
        if (!titleElement || !contentElement) return;
        
        // Get the content data
        const data = this.infoContent[section];
        if (!data) return;
        
        // Update the card title with icon
        if (data.icon) {
            titleElement.innerHTML = `
                <span style="display: inline-flex; align-items: center;">
                    <img src="${data.icon}" style="width: 24px; height: 24px; filter: brightness(0) invert(1); margin-right: 8px;" alt="">
                    ${data.title}
                </span>
            `;
        } else {
            titleElement.textContent = data.title;
        }
        
        // Update the card content
        let content = data.content;
        
        // If this is the about section, replace the version placeholder
        if (section === 'about') {
            const version = await this.getServiceWorkerVersion();
            content = content.replace(/Innovatech 360 v[\d.]+/, `Innovatech 360 v${version}`);
        }
        
        contentElement.innerHTML = content;
        
        // If this is the clear section, update storage info after content is loaded
        if (section === 'clear') {
            // Use setTimeout to ensure DOM is updated
            setTimeout(() => {
                this.updateStorageInfo();
            }, 0);
        }
        
        // Show the info card
        this.showCard('info');
    }

    /**
     * Flip the info card to show back side (not currently used)
     * Info card has flip capability for future two-sided content
     * @param {boolean} show - Whether to show the back side
     */
    flipInfoCard(show) {
        const cardInner = document.getElementById('info-card-inner');
        cardInner.classList.add('flipping');
        if (show) {
            cardInner.classList.add('flipped');
        } else {
            cardInner.classList.remove('flipped');
        }
        setTimeout(() => {
            cardInner.classList.remove('flipping');
        }, 600);
    }

    /**
     * Update storage information display in clear data section
     * Shows count and estimated size of stored photospheres
     * Called when showing the clear data info card
     */
    async updateStorageInfo() {
        const panoramas = await this.database.loadAllPanoramas();
        const count = panoramas.length;
        let totalSize = 0;
        panoramas.forEach(pano => {
            if (pano.imageData) {
                totalSize += pano.imageData.length * 0.75;
            }
        });
        const sizeMB = (totalSize / 1048576).toFixed(1);
        const storageInfo = document.getElementById('storage-info');
        if (storageInfo) {
            if (count > 0) {
                const lastCapture = panoramas[panoramas.length - 1];
                const lastDate = new Date(lastCapture.timestamp).toLocaleString();
                storageInfo.innerHTML = `• <strong>${count} photospheres</strong> stored<br>
                • <strong>${sizeMB} MB</strong> total size<br>
                • Last capture: ${lastDate}`;
            } else {
                storageInfo.innerHTML = '• No photospheres stored';
            }
        }
    }

    /**
     * Load and display all saved photospheres in camera roll
     * Creates thumbnail cards with view/download/delete actions
     * Shows empty state if no photospheres saved
     */
    async loadCameraRoll() {
        const listContainer = document.getElementById('camera-roll-list');
        const countElement = document.getElementById('camera-roll-count');
        
        try {
            const panoramas = await this.database.loadAllPanoramas();
            
            if (panoramas.length === 0) {
                countElement.textContent = 'No photospheres';
                listContainer.innerHTML = `
                    <div style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.2); padding: 40px 30px; text-align: center;">
                        <h2 style="color: white; font-size: 20px; margin-bottom: 10px;">No Photospheres Yet</h2>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 14px;">Start capturing your first 360° photosphere!</p>
                    </div>
                `;
                return;
            }
            
            const totalSize = panoramas.reduce((sum, pano) => sum + (pano.imageData ? pano.imageData.length * 0.75 : 0), 0);
            const sizeMB = (totalSize / 1048576).toFixed(1);
            countElement.textContent = `${panoramas.length} captured • ${sizeMB} MB total`;
            
            let html = '';
            panoramas.slice().reverse().forEach((pano, index) => {
                const panoIndex = panoramas.length - 1 - index;
                const dateStr = this.formatDate(new Date(pano.timestamp));
                html += `
                    <div class="photosphere-item">
                        <div class="equirectangular-container">
                            <div class="equirectangular-preview" style="background-image: url('${pano.imageData}'); background-size: cover; background-position: center;"></div>
                            <div class="action-buttons">
                                <button class="action-btn" onclick="window.sphereCapture.cardUI.viewPanorama(${panoIndex})" title="View"><img src="/360_cam/img/view.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt="View"></button>
                                <button class="action-btn" onclick="window.sphereCapture.cardUI.downloadPanorama(${panoIndex})" title="Download"><img src="/360_cam/img/download.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt="Download"></button>
                                <button class="action-btn" onclick="window.sphereCapture.cardUI.deletePanorama(${panoIndex})" title="Delete"><img src="/360_cam/img/trash.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt="Delete"></button>
                            </div>
                        </div>
                        <div class="capture-date">${dateStr}</div>
                    </div>
                `;
            });
            listContainer.innerHTML = html;
        } catch (error) {
            console.error('Error loading camera roll:', error);
            countElement.textContent = 'Error loading';
            listContainer.innerHTML = '<p style="color: rgba(255,255,255,0.7); text-align: center;">Error loading photospheres</p>';
        }
    }

    /**
     * Format date for display in camera roll
     * Shows relative time (just now, X minutes ago, today, yesterday)
     * @param {Date} date - Date to format
     * @returns {string} Formatted date string
     */
    formatDate(date) {
        const now = new Date();
        const diff = now - date;
        const days = Math.floor(diff / 86400000);
        if (days === 0) {
            const hours = Math.floor(diff / 3600000);
            if (hours === 0) {
                const minutes = Math.floor(diff / 60000);
                if (minutes < 1) return 'Just now';
                return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
            }
            return `Today, ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
        } else if (days === 1) {
            return `Yesterday, ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
        } else {
            return date.toLocaleDateString();
        }
    }

    /**
     * View a photosphere in full-screen Pannellum viewer
     * Creates temporary overlay with panorama viewer
     * @param {number} index - Index of panorama in database
     */
    async viewPanorama(index) {
        const panoramas = await this.database.loadAllPanoramas();
        const pano = panoramas[index];
        if (pano && pano.imageData) {
            const viewerContainer = document.createElement('div');
            viewerContainer.id = 'panorama-viewer';
            viewerContainer.style.cssText = `position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 2000; background: black;`;
            const closeBtn = document.createElement('button');
            closeBtn.className = 'card-close-btn';
            closeBtn.innerHTML = '<img src="/360_cam/img/x.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt="Close">';
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
                font-size: 24px;
                z-index: 2001;
            `;
            closeBtn.onclick = () => viewerContainer.remove();
            viewerContainer.appendChild(closeBtn);
            document.body.appendChild(viewerContainer);
            pannellum.viewer('panorama-viewer', { type: 'equirectangular', panorama: pano.imageData, autoLoad: true, autoRotate: -2, showFullscreenCtrl: true, mouseZoom: true });
        }
    }

    /**
     * Download a photosphere as JPEG file
     * @param {number} index - Index of panorama in database
     */
    async downloadPanorama(index) {
        const panoramas = await this.database.loadAllPanoramas();
        const pano = panoramas[index];
        if (pano && pano.imageData) {
            const link = document.createElement('a');
            link.download = `photosphere_${pano.timestamp}.jpg`;
            link.href = pano.imageData;
            link.click();
        }
    }

    /**
     * Delete a photosphere after confirmation
     * @param {number} index - Index of panorama in database
     */
    async deletePanorama(index) {
        const shouldDelete = await this.confirm(
            'Are you sure you want to delete this photosphere?\n\nThis action cannot be undone.',
            'Delete Photosphere?'
        );
        if (!shouldDelete) return;
        
        const panoramas = await this.database.loadAllPanoramas();
        const pano = panoramas[index];
        if (pano) {
            await this.database.deletePanorama(pano.id);
            await this.loadCameraRoll();
        }
    }

    /**
     * Update FOV adjustment slider value
     * Used for fine-tuning camera field of view if stitching has gaps/overlaps
     * @param {number} value - FOV adjustment in degrees (-5 to +5)
     */
    updateFOV(value) {
        const displayValue = value > 0 ? `+${value}` : value;
        document.getElementById('fovValue').textContent = displayValue;
        localStorage.setItem('fov-adjustment', value);
    }

    /**
     * Save FOV adjustment to localStorage
     * Persists user's camera calibration preference
     */
    async saveFOV() {
        const adjustment = document.getElementById('fovSlider').value;
        localStorage.setItem('fov-adjustment', adjustment);
        const displayValue = adjustment > 0 ? `+${adjustment}` : adjustment;
        await this.alert(`FOV adjustment saved: ${displayValue}°`, 'Settings Saved');
    }

    async updateStorageInfo() {
        const storageInfoElement = document.getElementById('storage-info');
        if (!storageInfoElement) return;
        
        try {
            const panoramas = await this.database.loadAllPanoramas();
            const count = panoramas.length;
            
            if (count === 0) {
                storageInfoElement.innerHTML = 'No photospheres stored';
            } else {
                // Estimate storage size (rough estimate based on image data URLs)
                let totalSize = 0;
                panoramas.forEach(pano => {
                    if (pano.imageData) {
                        // Base64 data URL size estimation
                        totalSize += pano.imageData.length * 0.75; // Base64 is ~33% larger
                    }
                });
                
                const sizeMB = (totalSize / (1024 * 1024)).toFixed(1);
                storageInfoElement.innerHTML = `
                    <strong>${count}</strong> photosphere${count !== 1 ? 's' : ''} stored<br>
                    Approximately <strong>${sizeMB} MB</strong> of storage used
                `;
            }
        } catch (error) {
            console.error('Error loading storage info:', error);
            storageInfoElement.innerHTML = 'Unable to load storage information';
        }
    }

    /**
     * Clear all photospheres after double confirmation
     * Requires two confirmation dialogs to prevent accidental deletion
     */
    async confirmClearRoll() {
        const panoramas = await this.database.loadAllPanoramas();
        if (panoramas.length === 0) {
            await this.alert('No photospheres to delete', 'Nothing to Clear');
            return;
        }
        const firstConfirm = await this.confirm(
            `Are you sure you want to delete ALL ${panoramas.length} photospheres?\n\nThis action cannot be undone.`,
            'Delete All Photospheres?'
        );
        if (firstConfirm) {
            const secondConfirm = await this.confirm(
                `This will permanently delete ${panoramas.length} photospheres.\n\nAre you absolutely sure?`,
                'Final Confirmation'
            );
            if (secondConfirm) {
                try {
                    // Delete each panorama individually since there's no clearAllPanoramas method
                    for (const pano of panoramas) {
                        await this.database.deletePanorama(pano.id);
                    }
                    
                    await this.alert('Camera roll cleared successfully', 'Deleted');
                    
                    // Close the info card and return to settings menu
                    this.closeInfoCard();
                    
                    // Update camera roll if it's visible
                    if (window.sphereCapture && window.sphereCapture.cameraRoll && window.sphereCapture.cameraRoll.isVisible) {
                        await window.sphereCapture.cameraRoll.loadCameraRoll();
                    }
                } catch (error) {
                    console.error('Error deleting panoramas:', error);
                    await this.alert('Failed to delete some photospheres', 'Error');
                }
            }
        }
    }

    /**
     * Show the settings menu card
     * Provides quick access to about, privacy, FOV, and clear data
     */
    showSettingsMenu() {
        // Check if we're currently in capture mode
        const sceneContainer = document.getElementById('scene-container');
        if (sceneContainer && sceneContainer.style.display !== 'none') {
            this.cameFromCapture = true;
        }
        this.showCard('settings-menu');
    }

    /**
     * Hide the settings menu card
     * Returns to whatever was visible before (capture mode or other card)
     */
    hideSettingsMenu() {
        // Close the settings menu card
        const settingsCard = this.cards['settings-menu'];
        if (settingsCard) {
            settingsCard.classList.add('pressing-down');
            settingsCard.classList.remove('visible', 'lifting-in', 'background');
            
            // Hide from screen readers
            settingsCard.setAttribute('aria-hidden', 'true');
            
            setTimeout(() => {
                settingsCard.classList.remove('pressing-down');
            }, 200);
        }
        
        // If we came from capture mode, restore it
        if (this.cameFromCapture) {
            this.cameFromCapture = false;
            
            // Hide grid and toolbar
            this.showGridAndToolbar(false);
            
            // Show capture UI elements
            document.getElementById('scene-container').style.display = 'block';
            document.getElementById('camera-viewport').style.display = 'block';
            document.getElementById('alignment-indicator').style.display = 'block';
            document.getElementById('instructions').style.display = 'block';
            const bottomControls = document.querySelector('.bottom-controls');
            if (bottomControls) bottomControls.style.display = 'flex';
            
            // Show the hotspots
            if (this.app && this.app.scene) {
                this.app.scene.showCaptureState();
            }
            
            // Resume camera if needed (but don't restart if already running)
            if (this.app && this.app.camera) {
                // Only start camera if not already running
                if (!this.app.camera.stream) {
                    this.app.camera.startCamera();
                } else if (this.app.camera.videoElement) {
                    // Just resume video playback if camera is already initialized
                    this.app.camera.videoElement.play().catch(e => console.error("Video play failed", e));
                }
            }
        } else {
            // Otherwise just check if we need to hide grid/toolbar
            let anyCardVisible = false;
            Object.values(this.cards).forEach(card => {
                if (card && card !== settingsCard && card.classList.contains('visible')) {
                    anyCardVisible = true;
                }
            });
            
            // Only hide grid/toolbar if no cards are visible
            if (!anyCardVisible) {
                this.showGridAndToolbar(false);
            }
        }
    }

    /**
     * Open info card with specific section
     * Called from settings menu items
     * @param {string} section - Section to show (about, privacy, fov, clear)
     */
    openInfoCard(section) {
        // Use the showInfoDetails method which handles the content
        this.showInfoDetails(section);
    }
    
    /**
     * Close the info card and return to settings menu
     * Info card always opens from settings, so settings is underneath
     */
    closeInfoCard() {
        // Just close the info card - settings menu is already underneath
        const infoCard = this.cards['info'];
        if (infoCard) {
            infoCard.classList.add('pressing-down');
            infoCard.classList.remove('visible', 'lifting-in', 'background');
            
            // Hide from screen readers
            infoCard.setAttribute('aria-hidden', 'true');
            
            setTimeout(() => {
                infoCard.classList.remove('pressing-down');
            }, 200);
        }
    }
    
    /**
     * Show a modal dialog with customizable buttons
     * Supports alert, confirm, and custom button configurations
     * 
     * @param {Object} options - Dialog configuration
     * @param {string} options.title - Dialog title (can include HTML)
     * @param {string} options.message - Dialog message text
     * @param {string} options.type - Dialog type: 'alert', 'confirm', or 'custom'
     * @param {string} options.confirmText - Text for confirm button (default: 'OK')
     * @param {string} options.cancelText - Text for cancel button (default: 'Cancel')
     * @param {Array} options.buttons - Custom button configs for type='custom'
     * @param {boolean} options.showGrid - Whether to show grid background (default: false)
     * @returns {Promise} Resolves with user's choice (true/false or custom value)
     */
    showDialog(options) {
        return new Promise((resolve) => {
            const { title = 'Alert', message = '', type = 'alert', confirmText = 'OK', cancelText = 'Cancel', buttons, showGrid = false } = options;
            
            // Create modal backdrop if it doesn't exist
            let backdrop = document.getElementById('dialog-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.id = 'dialog-backdrop';
                backdrop.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    z-index: 9998;
                    display: none;
                    opacity: 0;
                    transition: opacity 0.25s ease;
                `;
                document.body.appendChild(backdrop);
            }
            
            // Set dialog content
            const titleElement = document.getElementById('dialog-title');
            const messageElement = document.getElementById('dialog-message');
            const buttonsContainer = document.getElementById('dialog-buttons');
            
            if (titleElement) titleElement.innerHTML = title; // Use textContent for safety
            if (messageElement) messageElement.textContent = message;
            
            // Clear existing buttons and remove all event listeners
            if (buttonsContainer) {
                // Clone the container to remove all event listeners
                const newContainer = buttonsContainer.cloneNode(false);
                buttonsContainer.parentNode.replaceChild(newContainer, buttonsContainer);
                // Get fresh reference to the new container
                const freshButtonsContainer = document.getElementById('dialog-buttons');
                
                // Custom buttons
                if (type === 'custom' && buttons) {
                    buttons.forEach(buttonConfig => {
                        const btn = document.createElement(buttonConfig.style === 'link' ? 'a' : 'button');
                        btn.textContent = buttonConfig.text;
                        
                        if (buttonConfig.style === 'link') {
                            btn.href = '#';
                            btn.style.cssText = `
                                color: rgba(255, 255, 255, 0.9);
                                text-decoration: underline;
                                padding: 10px;
                                font-size: 14px;
                                cursor: pointer;
                            `;
                            btn.onclick = async (e) => {
                                e.preventDefault();
                                const result = buttonConfig.action();
                                if (result !== false) {
                                    await this.hideDialog();
                                    resolve(result);
                                }
                            };
                        } else if (buttonConfig.style === 'warning') {
                            btn.style.cssText = `
                                background: rgba(255, 0, 0, 0.2);
                                border: 1px solid rgba(255, 0, 0, 0.4);
                                color: #ff6b6b;
                                padding: 10px 20px;
                                border-radius: 10px;
                                font-size: 14px;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            `;
                            btn.onmouseover = () => {
                                btn.style.background = 'rgba(255, 0, 0, 0.3)';
                                btn.style.borderColor = 'rgba(255, 0, 0, 0.5)';
                            };
                            btn.onmouseout = () => {
                                btn.style.background = 'rgba(255, 0, 0, 0.2)';
                                btn.style.borderColor = 'rgba(255, 0, 0, 0.4)';
                            };
                            btn.onclick = async () => {
                                const result = buttonConfig.action();
                                if (result !== false) {
                                    await this.hideDialog();
                                    resolve(result);
                                }
                            };
                        } else if (buttonConfig.style === 'primary') {
                            btn.style.cssText = `
                                background: linear-gradient(135deg, #820000 0%, #8C1515 100%);
                                border: 1px solid rgba(140, 21, 21, 0.3);
                                color: white;
                                padding: 10px 20px;
                                border-radius: 10px;
                                font-size: 14px;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            `;
                            btn.onmouseover = () => {
                                btn.style.transform = 'scale(1.05)';
                                btn.style.boxShadow = '0 4px 12px rgba(140, 21, 21, 0.3)';
                            };
                            btn.onmouseout = () => {
                                btn.style.transform = 'scale(1)';
                                btn.style.boxShadow = 'none';
                            };
                            btn.onclick = async () => {
                                const result = await buttonConfig.action();
                                if (result !== false) {
                                    await this.hideDialog();
                                    resolve(result);
                                }
                            };
                        } else {
                            // Default button style
                            btn.style.cssText = `
                                background: rgba(255, 255, 255, 0.1);
                                border: 1px solid rgba(255, 255, 255, 0.2);
                                color: rgba(255, 255, 255, 0.9);
                                padding: 10px 20px;
                                border-radius: 10px;
                                font-size: 14px;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            `;
                            btn.onmouseover = () => {
                                btn.style.background = 'rgba(255, 255, 255, 0.15)';
                                btn.style.borderColor = 'rgba(255, 255, 255, 0.3)';
                            };
                            btn.onmouseout = () => {
                                btn.style.background = 'rgba(255, 255, 255, 0.1)';
                                btn.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                            };
                            btn.onclick = async () => {
                                const result = await buttonConfig.action();
                                if (result !== false) {
                                    await this.hideDialog();
                                    resolve(result);
                                }
                            };
                        }
                        
                        freshButtonsContainer.appendChild(btn);
                    });
                } else if (type === 'confirm') {
                    // Add Cancel button
                    const cancelBtn = document.createElement('button');
                    cancelBtn.textContent = cancelText;
                    cancelBtn.style.cssText = `
                        background: rgba(255, 255, 255, 0.1);
                        border: 1px solid rgba(255, 255, 255, 0.2);
                        color: rgba(255, 255, 255, 0.8);
                        padding: 10px 20px;
                        border-radius: 10px;
                        font-size: 14px;
                        font-weight: 500;
                        cursor: pointer;
                        transition: all 0.2s ease;
                    `;
                    cancelBtn.onmouseover = () => {
                        cancelBtn.style.background = 'rgba(255, 255, 255, 0.15)';
                        cancelBtn.style.borderColor = 'rgba(255, 255, 255, 0.3)';
                    };
                    cancelBtn.onmouseout = () => {
                        cancelBtn.style.background = 'rgba(255, 255, 255, 0.1)';
                        cancelBtn.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                    };
                    cancelBtn.onclick = async () => {
                        await this.hideDialog();
                        resolve(false);
                    };
                    freshButtonsContainer.appendChild(cancelBtn);
                    
                    // Add OK/Confirm button
                    const okBtn = document.createElement('button');
                    okBtn.textContent = confirmText;
                    okBtn.style.cssText = `
                        background: linear-gradient(135deg, #820000 0%, #8C1515 100%);
                        border: 1px solid rgba(140, 21, 21, 0.3);
                        color: white;
                        padding: 10px 20px;
                        border-radius: 10px;
                        font-size: 14px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.2s ease;
                    `;
                    okBtn.onmouseover = () => {
                        okBtn.style.transform = 'scale(1.05)';
                        okBtn.style.boxShadow = '0 4px 12px rgba(140, 21, 21, 0.3)';
                    };
                    okBtn.onmouseout = () => {
                        okBtn.style.transform = 'scale(1)';
                        okBtn.style.boxShadow = 'none';
                    };
                    okBtn.onclick = async () => {
                        await this.hideDialog();
                        resolve(true);
                    };
                    freshButtonsContainer.appendChild(okBtn);
                } else if (type === 'alert') {
                    // Add OK button for alert
                    const okBtn = document.createElement('button');
                    okBtn.textContent = confirmText;
                    okBtn.style.cssText = `
                        background: linear-gradient(135deg, #820000 0%, #8C1515 100%);
                        border: 1px solid rgba(140, 21, 21, 0.3);
                        color: white;
                        padding: 10px 20px;
                        border-radius: 10px;
                        font-size: 14px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.2s ease;
                    `;
                    okBtn.onmouseover = () => {
                        okBtn.style.transform = 'scale(1.05)';
                        okBtn.style.boxShadow = '0 4px 12px rgba(140, 21, 21, 0.3)';
                    };
                    okBtn.onmouseout = () => {
                        okBtn.style.transform = 'scale(1)';
                        okBtn.style.boxShadow = 'none';
                    };
                    okBtn.onclick = async () => {
                        await this.hideDialog();
                        resolve(true);
                    };
                    freshButtonsContainer.appendChild(okBtn);
                }
            }
            
            // Show the dialog with proper z-index and animation
            const dialogCard = this.cards['dialog'];
            if (dialogCard) {
                // Show backdrop first
                backdrop.style.display = 'block';
                requestAnimationFrame(() => {
                    backdrop.style.opacity = '1';
                });
                
                // Increment z-index to ensure dialog appears on top of backdrop
                this.cardZIndex = 9999; // Ensure dialog is above backdrop (9998)
                dialogCard.style.zIndex = this.cardZIndex;
                
                // Reset transform to prepare for animation
                dialogCard.style.transform = 'translate(-50%, -50%) scale(0.95)';
                dialogCard.style.opacity = '0';
                
                // Only show grid if explicitly requested
                if (showGrid) {
                    this.showGridAndToolbar(true);
                }
                dialogCard.classList.remove('background', 'pressing-down');
                dialogCard.classList.add('visible');
                
                // Make accessible to screen readers
                dialogCard.removeAttribute('aria-hidden');
                
                // Trigger the lift-in animation after a frame
                requestAnimationFrame(() => {
                    dialogCard.style.transition = 'transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.25s ease';
                    dialogCard.style.transform = 'translate(-50%, -50%) scale(1)';
                    dialogCard.style.opacity = '1';
                });
            }
        });
    }
    
    /**
     * Hide the dialog with press-down animation
     * Manages grid/toolbar visibility based on remaining cards
     * @returns {Promise} Resolves when dialog is fully hidden
     */
    hideDialog() {
        return new Promise((resolve) => {
            const dialogCard = this.cards['dialog'];
            const backdrop = document.getElementById('dialog-backdrop');
            
            // Hide backdrop
            if (backdrop) {
                backdrop.style.opacity = '0';
                setTimeout(() => {
                    backdrop.style.display = 'none';
                }, 250);
            }
            
            if (dialogCard) {
                // Animate press-down
                dialogCard.style.transition = 'transform 0.2s cubic-bezier(0.34, 1, 0.64, 1), opacity 0.2s ease';
                dialogCard.style.transform = 'translate(-50%, -50%) scale(0.95)';
                dialogCard.style.opacity = '0';
                
                setTimeout(() => {
                    dialogCard.classList.remove('visible');
                    // Hide from screen readers
                    dialogCard.setAttribute('aria-hidden', 'true');
                    // Reset z-index for next use
                    this.cardZIndex = Math.max(1002, this.cardZIndex - 10);
                    // Reset inline styles
                    dialogCard.style.transform = '';
                    dialogCard.style.opacity = '';
                    dialogCard.style.transition = '';
                    resolve();
                }, 250); // Slightly longer to ensure animation completes
            } else {
                resolve();
            }
            
            // Check if any other cards are still visible
            let anyCardVisible = false;
            Object.entries(this.cards).forEach(([name, card]) => {
                if (name !== 'dialog' && card && card.classList.contains('visible')) {
                    anyCardVisible = true;
                }
            });
            
            // Only hide grid/toolbar if no cards are visible
            if (!anyCardVisible) {
                this.showGridAndToolbar(false);
            }
        });
    }
    
    /**
     * Show a simple alert dialog with OK button
     * @param {string} message - Alert message
     * @param {string} title - Dialog title (default: 'Alert')
     * @returns {Promise<true>} Always resolves to true when dismissed
     */
    alert(message, title = 'Alert') {
        return this.showDialog({ title, message, type: 'alert' });
    }
    
    /**
     * Show a confirmation dialog with OK/Cancel buttons
     * @param {string} message - Confirmation message
     * @param {string} title - Dialog title (default: 'Confirm')
     * @returns {Promise<boolean>} Resolves to true (OK) or false (Cancel)
     */
    confirm(message, title = 'Confirm') {
        return this.showDialog({ title, message, type: 'confirm' });
    }
    
    /**
     * Check if any cards are currently visible
     * Used to determine if grid/toolbar should be shown
     * @returns {boolean} True if any card is visible
     */
    hasVisibleCards() {
        return Object.values(this.cards).some(card => 
            card && card.classList.contains('visible')
        );
    }
    
    /**
     * Show install button for PWA
     * Displays in settings menu when app is installable
     */
    showInstallButton() {
        // Add install button to settings if not already present
        const settingsContent = document.querySelector('#settings-menu .card-content');
        if (settingsContent && !document.getElementById('pwa-install-btn')) {
            const installSection = document.createElement('div');
            installSection.innerHTML = `
                <button id="pwa-install-btn" class="settings-item" style="
                    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
                    border: 1px solid rgba(76, 175, 80, 0.3);
                    margin-top: 15px;
                ">
                    <span class="settings-item-content">
                        <span class="settings-item-icon">📲</span>
                        <div>
                            <span class="settings-item-title">Install App</span>
                            <span class="settings-item-description">Add to home screen for offline access</span>
                        </div>
                    </span>
                    <span class="settings-item-arrow">›</span>
                </button>
            `;
            settingsContent.appendChild(installSection);
            
            // Handle install click
            document.getElementById('pwa-install-btn').addEventListener('click', () => {
                this.triggerInstall();
            });
        }
    }
    
    /**
     * Hide install button after installation
     */
    hideInstallButton() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.remove();
        }
    }
    
    /**
     * Trigger PWA installation
     */
    async triggerInstall() {
        if (window.deferredPrompt) {
            // Show the install prompt
            window.deferredPrompt.prompt();
            
            // Wait for user response
            const result = await window.deferredPrompt.userChoice;
            console.log('Install prompt result:', result);
            
            if (result.outcome === 'accepted') {
                console.log('User accepted install');
            } else {
                console.log('User dismissed install');
            }
            
            // Clear the deferred prompt
            window.deferredPrompt = null;
        } else {
            // Show instructions for manual installation
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            const instructions = isIOS
                ? 'To install: Tap the Share button below, then tap "Add to Home Screen"'
                : 'To install: Open browser menu and select "Install App" or "Add to Home Screen"';
            
            await this.alert(instructions, 'Install VFTCam');
        }
    }
    
    /**
     * Show update prompt when new service worker is available
     */
    showUpdatePrompt() {
        this.showDialog({
            title: 'Update Available',
            message: 'A new version of VFTCam is available. Update now for the latest features and improvements.',
            type: 'custom',
            buttons: [
                {
                    text: 'Update Now',
                    style: 'primary',
                    action: () => {
                        this.applyUpdate();
                        return true;
                    }
                },
                {
                    text: 'Later',
                    style: 'default',
                    action: () => true
                }
            ]
        });
    }
    
    /**
     * Apply service worker update
     */
    async applyUpdate() {
        // Tell service worker to skip waiting
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
            
            // Show updating message
            await this.alert('Updating app... The page will reload automatically.', 'Updating');
            
            // Reload after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    }
    
    /**
     * Check for app updates
     */
    async checkForUpdates() {
        // Check if service worker is supported and registered
        if (!('serviceWorker' in navigator)) {
            await this.alert('Updates are not supported in this browser', 'Not Available');
            return;
        }
        
        // Try to get the registration with the correct scope
        let registration = await navigator.serviceWorker.getRegistration('/');
        
        // If not found, try without scope
        if (!registration) {
            registration = await navigator.serviceWorker.getRegistration();
        }
        
        // If still not found, check if there's any registration at all
        if (!registration) {
            const registrations = await navigator.serviceWorker.getRegistrations();
            if (registrations.length > 0) {
                registration = registrations[0];
            }
        }
        
        if (!registration) {
            await this.alert('Service worker not found. Try refreshing the page first.', 'Not Ready');
            return;
        }
        
        // Make sure service worker is ready
        await navigator.serviceWorker.ready;
        
        // Show checking message
        const checkingDialog = this.showDialog({
            title: 'Checking for Updates',
            message: 'Looking for new version...',
            type: 'custom',
            buttons: []
        });
        
        try {
            // Check if we're offline first
            if (!navigator.onLine) {
                await this.hideDialog();
                await this.alert('Cannot check for updates while offline. Please connect to the internet and try again.', 'Offline');
                return;
            }
            
            // Force check for updates
            await registration.update();
            
            // Check if an update was found
            if (registration.waiting) {
                // Update is available and waiting
                await this.hideDialog();
                const shouldUpdate = await this.confirm(
                    'A new version is available. Would you like to update now?\n\nThis will refresh the app.',
                    'Update Available'
                );
                
                if (shouldUpdate) {
                    // Tell service worker to skip waiting and activate
                    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                    // Reload the page
                    window.location.reload();
                }
            } else if (registration.installing) {
                // Update is being installed
                await this.hideDialog();
                await this.alert('An update is being installed. The app will refresh automatically.', 'Update Installing');
                
                // Wait for installation to complete
                registration.installing.addEventListener('statechange', () => {
                    if (registration.installing.state === 'installed') {
                        window.location.reload();
                    }
                });
            } else {
                // No update available
                await this.hideDialog();
                
                // Show options dialog
                this.showDialog({
                    title: 'App is Up to Date',
                    message: 'You have the latest version. If you\'re experiencing issues, you can force a refresh to clear the cache.',
                    type: 'custom',
                    buttons: [
                        {
                            text: 'Force Refresh',
                            style: 'warning',
                            action: async () => {
                                const confirmForce = await this.confirm(
                                    'This will clear all cached data and reload the app. Your saved photospheres will be preserved.\n\nContinue?',
                                    'Force Refresh?'
                                );
                                if (confirmForce) {
                                    await this.forceRefresh();
                                }
                                return false; // Don't close dialog yet
                            }
                        },
                        {
                            text: 'OK',
                            style: 'primary',
                            action: () => true
                        }
                    ]
                });
            }
        } catch (error) {
            console.error('Error checking for updates:', error);
            await this.hideDialog();
            await this.alert('Failed to check for updates. Please try again later.', 'Error');
        }
    }
    
    /**
     * Force refresh the app by clearing cache and reloading
     */
    async forceRefresh() {
        try {
            // Show progress
            this.showDialog({
                title: 'Refreshing App',
                message: 'Clearing cache and reloading...',
                type: 'custom',
                buttons: []
            });
            
            // Clear all caches
            if ('caches' in window) {
                const cacheNames = await caches.keys();
                await Promise.all(
                    cacheNames.map(cacheName => {
                        console.log('Deleting cache:', cacheName);
                        return caches.delete(cacheName);
                    })
                );
            }
            
            // Unregister service worker
            if ('serviceWorker' in navigator) {
                const registration = await navigator.serviceWorker.getRegistration();
                if (registration) {
                    await registration.unregister();
                    console.log('Service worker unregistered');
                }
            }
            
            // Clear session storage (but not local storage to preserve settings)
            sessionStorage.clear();
            
            // Hard reload the page (bypass cache)
            window.location.reload(true);
        } catch (error) {
            console.error('Error during force refresh:', error);
            await this.hideDialog();
            await this.alert('Failed to refresh app. Please try reloading manually.', 'Error');
        }
    }
    
    /**
     * Show iOS install instructions
     */
    showIOSInstallInstructions() {
        const card = document.createElement('div');
        card.className = 'ui-card';
        card.innerHTML = `
            <div class="card-header">
                <h2 class="card-title">Install VFTCam</h2>
            </div>
            <div class="card-content" style="text-align: center;">
                <div style="font-size: 48px; margin: 20px 0;">📲</div>
                <p style="margin-bottom: 20px;">To install VFTCam on your iPhone:</p>
                <ol style="text-align: left; max-width: 300px; margin: 0 auto;">
                    <li style="margin: 10px 0;">Tap the Share button 
                        <span style="display: inline-block; width: 20px; height: 20px; 
                               background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 9h8"/><path d="M12 3v13"/><path d="M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>') center/contain no-repeat;
                               vertical-align: middle;"></span>
                    </li>
                    <li style="margin: 10px 0;">Scroll down and tap "Add to Home Screen"</li>
                    <li style="margin: 10px 0;">Tap "Add" in the top right</li>
                </ol>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="margin-top: 30px; padding: 10px 30px; 
                               background: #8C1515; color: white; 
                               border: none; border-radius: 10px; 
                               font-size: 16px; cursor: pointer;">
                    Got it!
                </button>
            </div>
        `;
        document.body.appendChild(card);
        card.classList.add('visible');
    }
    
    /**
     * Create a custom card with provided content
     */
    async createCard(options) {
        const { id, title, content, className = '', zIndex = 1002 } = options;
        
        // Remove existing card with same ID if present
        const existingCard = document.getElementById(id);
        if (existingCard) {
            existingCard.remove();
        }
        
        // Create the card element
        const card = document.createElement('div');
        card.id = id;
        card.className = `ui-card ${className}`;
        card.setAttribute('role', 'dialog');
        card.setAttribute('aria-modal', 'true');
        
        card.innerHTML = `
            <button class="card-close-btn" aria-label="Close ${title}">
                <img src="/360_cam/img/x.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt="Close icon">
            </button>
            <div class="card-header">
                <h1 class="card-title">${title}</h1>
            </div>
            <div class="card-content">
                ${content}
            </div>
        `;
        
        // Add to body
        document.body.appendChild(card);
        
        // Set z-index if specified
        card.style.zIndex = zIndex;
        
        // Apply lifting-in animation
        requestAnimationFrame(() => {
            card.classList.add('lifting-in');
        });
        
        // Make visible after animation starts
        setTimeout(() => {
            card.classList.add('visible');
        }, 50);
        
        return card;
    }
    
    /**
     * Hide a card by ID
     */
    async hideCard(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;
        
        // Apply pressing-down animation
        card.classList.add('pressing-down');
        card.classList.remove('lifting-in');
        
        // Remove after animation
        setTimeout(() => {
            card.remove();
        }, 300);
    }
}
