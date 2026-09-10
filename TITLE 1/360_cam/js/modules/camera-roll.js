/**
 * Camera Roll Module - Photosphere Gallery Management
 * 
 * This module provides a dedicated gallery interface for viewing, sharing, and managing
 * saved photospheres. It operates as an overlay that temporarily replaces the capture
 * interface, allowing users to interact with their completed panoramas.
 * 
 * ARCHITECTURE:
 * The camera roll is a full-screen card overlay that shows when the user taps the
 * gallery button during capture mode. It pauses all capture operations and displays
 * a grid of photosphere thumbnails with action buttons.
 * 
 * KEY FEATURES:
 * - Grid display of photosphere thumbnails with equirectangular preview
 * - View in Pannellum 360° viewer with auto-rotation
 * - Share via Web Share API (mobile) or download (desktop)
 * - Delete with confirmation dialog
 * - Storage size calculation and display
 * - Relative date formatting ("Just now", "2 days ago", etc.)
 * 
 * UI STRUCTURE:
 * ┌─────────────────────────────────┐
 * │  Camera Roll (X photospheres)  │ <- Header with count/size
 * ├─────────────────────────────────┤
 * │ ┌───────────┐ ┌───────────┐     │
 * │ │ Thumbnail │ │ Thumbnail │    │ <- Equirectangular previews
 * │ │ [👁][↗][🗑] │ │ [👁][↗][🗑] │    │ <- View/Share/Delete buttons
 * │ │ 2 hrs ago │ │ Yesterday  │    │ <- Capture timestamp
 * │ └───────────┘ └───────────┘     │
 * └─────────────────────────────────┘
 * 
 * STATE MANAGEMENT:
 * - Tracks capture state to restore after closing
 * - Pauses camera stream to save battery
 * - Hides all capture UI elements
 * - Prevents multiple delete operations
 * 
 * INTERACTION FLOW:
 * 1. User taps gallery button during capture
 * 2. Camera roll slides up, capture UI hidden
 * 3. User can view/share/delete panoramas
 * 4. Close button returns to capture mode
 * 5. Camera and UI state fully restored
 * 
 * PANNELLUM INTEGRATION:
 * The view function creates a temporary full-screen Pannellum viewer
 * that allows interactive exploration of the 360° panorama with:
 * - Auto-rotation at -2°/second
 * - Mouse/touch drag navigation
 * - Zoom controls
 * - Fullscreen mode
 * 
 * WEB SHARE API:
 * On mobile devices, uses native share sheet for sharing to:
 * - Social media apps
 * - Messaging apps  
 * - Cloud storage
 * - Email
 * Falls back to download on desktop or if sharing fails
 * 
 * METADATA PRESERVATION:
 * When sharing, checks if panorama has XMP metadata and:
 * - Recreates blob with metadata if present
 * - Preserves photosphere markers for Google Photos
 * - Maintains original capture timestamp
 * 
 * MEMORY CONSIDERATIONS:
 * - Thumbnails use base64 data URLs (stored in database)
 * - Full panoramas loaded only when viewing/sharing
 * - Pannellum viewer cleaned up on close
 * - Each thumbnail ~50-100KB in DOM
 * 
 * ERROR HANDLING:
 * - Graceful fallback if Web Share API unavailable
 * - Confirmation dialogs prevent accidental deletion
 * - Try-catch blocks for all async operations
 * - User feedback via alerts for errors
 * 
 * @module CameraRoll
 * @requires metadataUtils - For XMP metadata handling
 * @requires photoSphereSharer - For Web Share API integration
 */

import { metadataUtils } from './metadata-utils.js';
import { photoSphereSharer } from './share-utils.js';
import { VRViewer } from './vr-viewer.js';

export class CameraRoll {
    /**
     * Initialize the camera roll gallery
     * @param {Object} app - Main app instance for accessing database and UI
     */
    constructor(app) {
        this.app = app;                      // Reference to main app controller
        this.database = app.database;         // Shared database instance
        this.isVisible = false;               // Track visibility state
        this.card = document.getElementById('camera-roll-card'); // Gallery card element
        this.captureWasEnabled = true;        // Remember capture state for restoration
        this.isDeleting = false;              // Mutex to prevent concurrent deletes
        this.vrViewer = new VRViewer(app);   // VR viewer instance
    }

    /**
     * Show the camera roll gallery
     * Pauses capture mode and displays photosphere gallery
     * Saves current state for restoration when closing
     */
    async show() {
        if (this.isVisible) return;

        // Check if stitching overlay is visible (processor card)
        const stitchingOverlay = document.getElementById('stitching-overlay');
        const isProcessing = stitchingOverlay && stitchingOverlay.classList.contains('visible');

        // If coming from processor card, close it first and clean up memory
        if (isProcessing) {
            console.log('Closing processor card and cleaning up memory before opening camera roll');

            // Hide the processor card with animation
            stitchingOverlay.classList.remove('lifting-in');
            stitchingOverlay.classList.add('pressing-down');

            // Clean up memory from stitching process
            if (this.app.cleanupStitchingMemory) {
                this.app.cleanupStitchingMemory();
            }

            // Wait for animation to complete
            await new Promise(resolve => setTimeout(resolve, 300));

            // Fully hide the processor card
            stitchingOverlay.classList.remove('visible', 'pressing-down');

            // Show start screen underneath so it's not black
            const startScreen = document.getElementById('start-screen');
            if (startScreen) {
                startScreen.style.display = 'block';
                startScreen.classList.add('visible');
            }
        } else {
            // Only warn if we're in active capture mode, NOT if we're on the processor card
            // Check if we're in an active capture session
            const capturedCount = this.app.capturedHotspots ? this.app.capturedHotspots.size : 0;
            const isCapturing = capturedCount > 0;

            // If actively capturing, warn user they'll lose progress
            if (isCapturing) {
                const message = `Leave capture mode?\n\nYour current progress (${capturedCount} of 36 photos) will be lost.`;

                const proceed = await this.app.cardUI.showDialog({
                    title: 'Leave Capture Session',
                    message: message,
                    confirmText: 'Leave Capture',
                    cancelText: 'Cancel',
                    isWarning: true
                });

                if (!proceed) {
                    return; // User cancelled, stay in capture mode
                }

                // Clear the capture session
                console.log('Clearing capture session to show camera roll');
                await this.app.resetCapture();
            }
        }

        // Disable capturing
        this.app.setCapturingEnabled(false);

        // Hide capture UI elements
        document.getElementById('scene-container').style.display = 'none';
        document.getElementById('camera-viewport').style.display = 'none';
        document.getElementById('alignment-indicator').style.display = 'none';
        document.getElementById('instructions').style.display = 'none';

        const bottomControls = document.querySelector('.bottom-controls');
        if (bottomControls) {
            bottomControls.style.display = 'none';
        }

        // Pause camera
        if (this.app.camera) {
            this.app.camera.stop();
        }

        // Load and show camera roll
        await this.loadCameraRoll();

        // Show the card with lift in animation
        this.card.classList.remove('pressing-down');
        this.card.classList.add('lifting-in', 'visible');
        this.isVisible = true;

        // Remove animation class after it completes
        setTimeout(() => {
            this.card.classList.remove('lifting-in');
        }, 250);
    }

    /**
     * Hide the camera roll and return to previous state
     * Either returns to capture mode or start screen
     */
    async hide() {
        if (!this.isVisible) return;

        // Animate card sliding down
        this.card.classList.remove('lifting-in');
        this.card.classList.add('pressing-down');

        // Wait for animation to complete before fully hiding
        setTimeout(() => {
            this.card.classList.remove('visible', 'pressing-down');
            this.isVisible = false;
        }, 300);

        // ALWAYS return to start screen (simplified flow)
        const startScreen = document.getElementById('start-screen');
        const gridBackground = document.getElementById('grid-background');

        // Show start screen
        if (startScreen) {
            startScreen.style.display = 'block';
            startScreen.classList.add('visible');
            startScreen.classList.remove('pressing-down');
        }

        // Hide grid background
        if (gridBackground) {
            gridBackground.style.display = 'none';
            gridBackground.classList.remove('visible');
        }

        // Hide all capture UI elements
        document.getElementById('scene-container').style.display = 'none';
        document.getElementById('camera-viewport').style.display = 'none';
        document.getElementById('alignment-indicator').style.display = 'none';
        document.getElementById('instructions').style.display = 'none';

        const bottomControls = document.querySelector('.bottom-controls');
        if (bottomControls) {
            bottomControls.style.display = 'none';
        }
    }

    /**
     * Load and display all saved photospheres
     * Generates thumbnail grid with action buttons
     * Calculates and displays total storage usage
     */
    async loadCameraRoll() {
        const listContainer = document.getElementById('camera-roll-list');
        const countElement = document.getElementById('camera-roll-count');

        // Check PWA installation status - reuse existing instance if available
        let pwaStatus = this.pwaStatus;
        let storageWarning = null;
        try {
            if (!pwaStatus) {
                const { PWAStatus } = await import('./pwa-status.js');
                pwaStatus = new PWAStatus();
                this.pwaStatus = pwaStatus;
            }
            // Always re-check status to get latest state
            await pwaStatus.checkStatus();
            storageWarning = pwaStatus.getStorageWarning();
        } catch (error) {
            console.error('Failed to load PWA status:', error);
        }

        try {
            const panoramas = await this.database.loadAllPanoramas();

            if (panoramas.length === 0) {
                countElement.textContent = 'No 360 Captures yet';
                listContainer.innerHTML = `
                    <div style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(20px); border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.2); padding: 40px 30px; text-align: center;">
                        <h2 style="color: white; font-size: 20px; margin-bottom: 10px;">No 360 Captures Yet</h2>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 14px;">Complete a 360° capture session to create your first panorama!</p>
                    </div>
                `;
                return;
            }

            // Calculate total storage size
            const totalSize = panoramas.reduce((sum, pano) => {
                if (pano.imageBlob) {
                    // Direct blob size
                    return sum + pano.imageBlob.size;
                } else if (pano.imageData) {
                    // Legacy base64 format
                    return sum + (pano.imageData.length * 0.75);
                }
                return sum;
            }, 0);
            const sizeMB = (totalSize / 1048576).toFixed(1);
            countElement.textContent = `${panoramas.length} captured • ${sizeMB} MB total`;

            // Generate thumbnail cards for each panorama
            let html = '';
            // Clean up previous blob URLs if any
            if (this.blobUrls) {
                this.blobUrls.forEach(url => URL.revokeObjectURL(url));
                this.blobUrls = [];
            } else {
                this.blobUrls = [];
            }

            panoramas.forEach((pano, index) => {
                const dateStr = this.formatDate(new Date(pano.timestamp));

                // Create blob URL for display
                let imageUrl;
                if (pano.imageBlob) {
                    imageUrl = URL.createObjectURL(pano.imageBlob);
                    this.blobUrls.push(imageUrl);
                } else if (pano.imageData) {
                    // Legacy base64 format
                    imageUrl = pano.imageData;
                } else {
                    return; // Skip if no image
                }

                html += `
                    <div class="photosphere-item">
                        <div class="equirectangular-container">
                            <div class="equirectangular-preview" 
                                 style="background-image: url('${imageUrl}'); 
                                        background-size: cover; 
                                        background-position: center;">
                            </div>
                            <div class="action-buttons">
                                <button class="action-btn" onclick="window.sphereCapture.cameraRoll.viewPanorama(${index})" title="View">
                                    <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                </button>
                                <button class="action-btn" onclick="window.sphereCapture.cameraRoll.viewVR(${index})" title="VR View">
                                    <img src="img/vr-cardboard.svg" width="20" height="20" alt="VR" style="filter: brightness(0) invert(1);">
                                </button>
                                <button class="action-btn" onclick="window.sphereCapture.cameraRoll.sharePanorama(${index})" title="Share">
                                    <svg width="20" height="20" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                                        <polyline points="16 6 12 2 8 6"></polyline>
                                        <line x1="12" y1="2" x2="12" y2="15"></line>
                                    </svg>
                                </button>
                                <button class="action-btn" onclick="window.sphereCapture.cameraRoll.syncToSystem(${index})" title="Save to System" style="background: rgba(52, 211, 153, 0.15); border-color: rgba(52, 211, 153, 0.4);">
                                    <svg width="20" height="20" fill="none" stroke="rgb(52,211,153)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <polyline points="16 16 12 12 8 16"></polyline>
                                        <line x1="12" y1="12" x2="12" y2="21"></line>
                                        <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                                    </svg>
                                </button>
                                <button class="action-btn" onclick="window.sphereCapture.cameraRoll.deletePanorama(${index})" title="Delete">
                                    <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
                                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="capture-date">${dateStr}</div>
                    </div>
                `;
            });

            // Build final HTML with photospheres first, then warning, then download button
            let finalHTML = '';

            // Add photosphere grid FIRST
            finalHTML += html;

            // Add storage warning banner AFTER photospheres
            if (storageWarning && storageWarning.level !== 'success') {
                const bannerColor = storageWarning.level === 'danger' ?
                    'rgba(255, 100, 100, 0.2)' :
                    'rgba(255, 193, 7, 0.2)';
                const borderColor = storageWarning.level === 'danger' ?
                    'rgba(255, 100, 100, 0.4)' :
                    'rgba(255, 193, 7, 0.4)';

                finalHTML += `
                    <div id="storage-warning-banner" style="
                        background: ${bannerColor};
                        border: 1px solid ${borderColor};
                        border-radius: 12px;
                        padding: 15px;
                        margin-top: 20px;
                        margin-bottom: 20px;
                        cursor: pointer;
                    " onclick="window.sphereCapture.cameraRoll.showInstallInstructions()">
                        <h3 style="color: white; font-size: 16px; margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
                            ⚠️ ${storageWarning.title}
                        </h3>
                        <p style="color: rgba(255, 255, 255, 0.9); font-size: 14px; margin: 0 0 8px 0;">
                            ${storageWarning.message}
                        </p>
                        ${storageWarning.action ? `
                            <button style="
                                background: transparent;
                                border: 1px solid rgba(255, 255, 255, 0.3);
                                color: white;
                                padding: 10px 20px;
                                border-radius: 8px;
                                font-size: 15px;
                                font-weight: 500;
                                margin-top: 12px;
                                cursor: pointer;
                                width: 100%;
                                transition: all 0.2s ease;
                            " onmouseover="this.style.borderColor='rgba(255,255,255,0.5)'; this.style.background='rgba(255,255,255,0.05)';" 
                               onmouseout="this.style.borderColor='rgba(255,255,255,0.3)'; this.style.background='transparent';">
                                ${storageWarning.action}
                            </button>
                        ` : ''}
                    </div>
                `;
            }

            // Add action buttons at the bottom: Download All + Sync All to System
            finalHTML += `
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); display: flex; flex-direction: column; gap: 10px;">
                    <button onclick="window.sphereCapture.cameraRoll.syncAllToSystem()" id="sync-all-btn" style="
                        width: 100%;
                        padding: 14px;
                        background: rgba(52, 211, 153, 0.15);
                        border: 1px solid rgba(52, 211, 153, 0.4);
                        border-radius: 12px;
                        color: rgb(52, 211, 153);
                        font-weight: 700;
                        font-size: 16px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 10px;
                        transition: all 0.2s ease;
                        letter-spacing: 0.01em;
                    " onmouseover="this.style.background='rgba(52,211,153,0.25)'; this.style.borderColor='rgba(52,211,153,0.7)'"
                       onmouseout="this.style.background='rgba(52,211,153,0.15)'; this.style.borderColor='rgba(52,211,153,0.4)'">
                        <svg width="20" height="20" fill="none" stroke="rgb(52,211,153)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <polyline points="16 16 12 12 8 16"></polyline>
                            <line x1="12" y1="12" x2="12" y2="21"></line>
                            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                        </svg>
                        Sync All to System
                    </button>
                    <button onclick="window.sphereCapture.cameraRoll.downloadAll()" style="
                        width: 100%;
                        padding: 14px;
                        background: rgba(255, 255, 255, 0.1);
                        border: 1px solid rgba(255, 255, 255, 0.2);
                        border-radius: 12px;
                        color: white;
                        font-weight: 600;
                        font-size: 16px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 10px;
                        transition: all 0.2s ease;
                    " onmouseover="this.style.background='rgba(255,255,255,0.15)'" 
                       onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                        <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                        </svg>
                        Download All (.zip)
                    </button>
                </div>
            `;

            listContainer.innerHTML = finalHTML;

            // PWA status is already stored in this.pwaStatus
        } catch (error) {
            console.error('Error loading camera roll:', error);
            countElement.textContent = 'Error loading';
            listContainer.innerHTML = '<p style="color: rgba(255,255,255,0.7); text-align: center;">Error loading photospheres</p>';
        }
    }

    /**
     * Format date for human-readable display
     * Shows relative time for recent captures ("Just now", "2 hours ago")
     * Shows absolute date for older captures
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
            return `Today, ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
        } else if (days === 1) {
            return `Yesterday, ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
        } else if (days < 7) {
            return `${days} days ago`;
        } else {
            return date.toLocaleDateString();
        }
    }

    /**
     * View a photosphere in full-screen Pannellum viewer
     * Creates temporary overlay with interactive 360° viewer
     * @param {number} index - Index of panorama in loaded array
     */
    async viewPanorama(index) {
        const panoramas = await this.database.loadAllPanoramas();
        const pano = panoramas[index];

        if (pano && (pano.imageBlob || pano.imageData)) {
            // Create blob URL for viewer
            let panoramaUrl;
            if (pano.imageBlob) {
                panoramaUrl = URL.createObjectURL(pano.imageBlob);
            } else {
                panoramaUrl = pano.imageData; // Legacy base64
            }

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
                font-size: 24px;
                z-index: 2001;
            `;
            closeBtn.onclick = () => {
                // Clean up blob URL if using blob
                if (pano.imageBlob) {
                    URL.revokeObjectURL(panoramaUrl);
                }
                viewerContainer.remove();
            };
            viewerContainer.appendChild(closeBtn);

            // Add to body
            document.body.appendChild(viewerContainer);

            // Initialize Pannellum 360° viewer with auto-rotation
            pannellum.viewer('panorama-viewer', {
                type: 'equirectangular',      // Panorama projection type
                panorama: panoramaUrl,         // Blob URL or base64
                autoLoad: true,                // Start loading immediately
                autoRotate: -2,                // Rotate at -2°/second
                showFullscreenCtrl: true,      // Show fullscreen button
                mouseZoom: true                // Enable scroll wheel zoom
            });
        }
    }

    /**
     * View panorama in VR mode
     * Opens immersive stereoscopic VR viewer with device orientation tracking
     * @param {number} index - Index of panorama in loaded array
     */
    async viewVR(index) {
        // Request device orientation permission if needed (iOS 13+)
        // This must happen from a user gesture (the button click)
        // Only request if we haven't already gotten permission
        if (typeof DeviceOrientationEvent !== 'undefined' &&
            typeof DeviceOrientationEvent.requestPermission === 'function') {

            let permission = 'default';

            // Check if we already have permission by testing if we get orientation events
            const hasPermission = await new Promise(resolve => {
                let gotEvent = false;

                const testHandler = () => {
                    gotEvent = true;
                };

                window.addEventListener('deviceorientation', testHandler);

                // Wait a bit to see if we get an event
                setTimeout(() => {
                    window.removeEventListener('deviceorientation', testHandler);
                    resolve(gotEvent);
                }, 100);
            });

            // Only request permission if we don't already have it
            if (!hasPermission) {
                try {
                    permission = await DeviceOrientationEvent.requestPermission();
                    console.log('Device orientation permission:', permission);

                    if (permission !== 'granted') {
                        // Show error message
                        if (this.app && this.app.cardUI) {
                            await this.app.cardUI.alert(
                                'Device orientation access is required for VR mode. Please grant permission and try again.',
                                'Permission Required'
                            );
                        } else {
                            alert('Device orientation access is required for VR mode.');
                        }
                        return;
                    }
                } catch (error) {
                    console.error('Error requesting device orientation permission:', error);
                    // Continue anyway - might work on some devices
                }
            } else {
                console.log('Device orientation permission already granted');
            }

            // Also request motion permission if available and not already granted
            if (typeof DeviceMotionEvent !== 'undefined' &&
                typeof DeviceMotionEvent.requestPermission === 'function') {

                // Check if we already have motion permission
                const hasMotionPermission = await new Promise(resolve => {
                    let gotEvent = false;
                    const testHandler = () => { gotEvent = true; };
                    window.addEventListener('devicemotion', testHandler);
                    setTimeout(() => {
                        window.removeEventListener('devicemotion', testHandler);
                        resolve(gotEvent);
                    }, 100);
                });

                if (!hasMotionPermission) {
                    try {
                        await DeviceMotionEvent.requestPermission();
                    } catch (error) {
                        console.error('Error requesting device motion permission:', error);
                        // Continue anyway, motion is less critical
                    }
                }
            }
        }

        const panoramas = await this.database.loadAllPanoramas();
        const pano = panoramas[index];

        if (pano && (pano.imageBlob || pano.imageData)) {
            // Create URL for VR viewer
            let panoramaUrl;
            if (pano.imageBlob) {
                panoramaUrl = URL.createObjectURL(pano.imageBlob);
                // Clean up after VR viewer closes
                setTimeout(() => URL.revokeObjectURL(panoramaUrl), 60000); // Clean after 1 minute
            } else {
                panoramaUrl = pano.imageData; // Legacy base64
            }

            // Show VR viewer
            await this.vrViewer.show(panoramaUrl);
        }
    }

    /**
     * Share or download a photosphere
     * Uses Web Share API on mobile, falls back to download on desktop
     * Preserves XMP metadata if present for Google Photos compatibility
     * @param {number} index - Index of panorama in loaded array
     */
    async sharePanorama(index) {
        const panoramas = await this.database.loadAllPanoramas();
        const pano = panoramas[index];

        if (pano && (pano.imageBlob || pano.imageData)) {
            try {
                let panoramaBlob;

                if (pano.imageBlob) {
                    // Already have blob, use it directly
                    panoramaBlob = pano.imageBlob;
                } else if (pano.imageData) {
                    // Legacy base64 format
                    if (pano.metadata && pano.hasMetadata) {
                        // Recreate blob with XMP metadata for Google Photos
                        panoramaBlob = await metadataUtils.addMetadataToJPEG(
                            pano.imageData,
                            pano.metadata
                        );
                    } else {
                        // Simple conversion without metadata
                        const response = await fetch(pano.imageData);
                        panoramaBlob = await response.blob();
                    }
                }

                // Generate filename: photosphere_YYYYMMDD_HHMMSS.jpg
                const timestamp = new Date(pano.timestamp);
                const year = timestamp.getFullYear();
                const month = String(timestamp.getMonth() + 1).padStart(2, '0');
                const day = String(timestamp.getDate()).padStart(2, '0');
                const hours = String(timestamp.getHours()).padStart(2, '0');
                const minutes = String(timestamp.getMinutes()).padStart(2, '0');
                const seconds = String(timestamp.getSeconds()).padStart(2, '0');
                const filename = `photosphere_${year}${month}${day}_${hours}${minutes}${seconds}.jpg`;

                // Use share API
                await photoSphereSharer.sharePhotosphere(panoramaBlob, filename);

            } catch (error) {
                console.error('Error sharing panorama:', error);
                // Fallback to simple download
                const link = document.createElement('a');
                const timestamp = new Date(pano.timestamp);
                const year = timestamp.getFullYear();
                const month = String(timestamp.getMonth() + 1).padStart(2, '0');
                const day = String(timestamp.getDate()).padStart(2, '0');
                const hours = String(timestamp.getHours()).padStart(2, '0');
                const minutes = String(timestamp.getMinutes()).padStart(2, '0');
                const seconds = String(timestamp.getSeconds()).padStart(2, '0');
                link.download = `photosphere_${year}${month}${day}_${hours}${minutes}${seconds}.jpg`;
                // Create blob URL for download
                if (pano.imageBlob) {
                    link.href = URL.createObjectURL(pano.imageBlob);
                    // Clean up after download
                    setTimeout(() => URL.revokeObjectURL(link.href), 1000);
                } else {
                    link.href = pano.imageData; // Legacy base64
                }
                link.click();
            }
        }
    }

    /**
     * Show PWA installation instructions
     */
    async showInstallInstructions() {
        if (!this.pwaStatus) {
            const { PWAStatus } = await import('./pwa-status.js');
            this.pwaStatus = new PWAStatus();
        }

        const instructions = this.pwaStatus.getInstallInstructions();
        const status = this.pwaStatus.getStatus();

        if (status.isInstalled) {
            // If already installed, offer to enable persistence
            if (!status.isPersistent) {
                const enabled = await this.pwaStatus.requestPersistence();
                if (enabled) {
                    await this.app.cardUI.alert('Storage protection enabled! Your photospheres are now protected from automatic deletion.', 'Success');
                    // Re-check status and reload camera roll to update banner
                    await this.pwaStatus.checkStatus();
                    await this.loadCameraRoll();
                }
            } else {
                await this.app.cardUI.alert('Your photospheres are already protected!', 'Storage Protected');
            }
            return;
        }

        // Show installation instructions
        await this.app.permissions.handleOfflineRequest();

        // Reload camera roll to update banner if status changed
        const newStatus = this.pwaStatus.getStatus();
        if (newStatus.isInstalled || newStatus.isPersistent) {
            await this.loadCameraRoll();
        }
    }

    /**
     * Download all panoramas as ZIP
     */
    async downloadAll() {
        let isCancelled = false;

        try {
            // Check if JSZip is loaded
            if (!window.JSZip) {
                // Load JSZip dynamically from local file
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = './js/ext/jszip.min.js';
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            }

            const panoramas = await this.database.loadAllPanoramas();
            if (panoramas.length === 0) {
                await this.app.cardUI.alert('No photospheres to download', 'Nothing to Download');
                return;
            }

            // Create a promise that resolves when dialog is closed
            let dialogResolve;
            const dialogPromise = new Promise(resolve => { dialogResolve = resolve; });

            // Show dialog with placeholder message - DON'T await it or it will block!
            this.app.cardUI.showDialog({
                title: 'Downloading Photospheres',
                message: 'Initializing...',
                type: 'custom',
                buttons: [
                    {
                        text: 'OK',
                        style: 'default',
                        action: () => {
                            dialogResolve('ok');
                            return true;
                        }
                    },
                    {
                        text: 'Cancel',
                        style: 'warning',
                        action: () => {
                            isCancelled = true;
                            dialogResolve('cancel');
                            return true;
                        }
                    }
                ]
            });

            // Immediately modify the dialog message element to add HTML content with progress bar
            // Need a small delay to ensure dialog is rendered
            await new Promise(resolve => setTimeout(resolve, 50));

            const messageElement = document.getElementById('dialog-message');
            if (messageElement) {
                // Replace textContent with innerHTML to render HTML - compact version
                messageElement.innerHTML = `
                    <div style="background: rgba(255,255,255,0.1); border-radius: 8px; height: 10px; overflow: hidden; margin-bottom: 10px; position: relative;">
                        <div id="download-progress-bar" style="background: #8C1515; height: 10px; width: 0%; transition: width 0.3s ease; position: absolute; top: 0; left: 0;"></div>
                    </div>
                    <p id="download-progress-text" style="color: rgba(255,255,255,0.9); font-size: 14px; margin: 0;">
                        Initializing...
                    </p>
                `;
            }

            // Disable OK button initially - it's the first button in the dialog
            setTimeout(() => {
                const dialogButtons = document.querySelectorAll('#dialog-buttons button');
                if (dialogButtons && dialogButtons[0]) {
                    const okBtn = dialogButtons[0];
                    okBtn.disabled = true;
                    okBtn.style.opacity = '0.5';
                    okBtn.style.cursor = 'not-allowed';
                }
            }, 100);

            const zip = new JSZip();
            const photospheresFolder = zip.folder('photospheres');

            // Add each panorama to the ZIP with progress updates
            for (let i = 0; i < panoramas.length; i++) {
                if (isCancelled) {
                    console.log('Download cancelled by user');
                    return;
                }

                const pano = panoramas[i];
                const timestamp = new Date(pano.timestamp).toISOString().replace(/[:.]/g, '-').slice(0, -5);
                const filename = `photosphere_${timestamp}.jpg`;

                if (pano.imageBlob) {
                    photospheresFolder.file(filename, pano.imageBlob);
                } else if (pano.imageData) {
                    // Convert base64 to blob if needed
                    const base64 = pano.imageData.replace(/^data:image\/\w+;base64,/, '');
                    photospheresFolder.file(filename, base64, { base64: true });
                }

                // Update progress
                const progress = Math.round(((i + 1) / panoramas.length) * 100);
                const progressBar = document.getElementById('download-progress-bar');
                const progressText = document.getElementById('download-progress-text');
                if (progressBar) {
                    progressBar.style.width = `${progress}%`;
                }
                if (progressText) progressText.textContent = `Processing ${i + 1} of ${panoramas.length}...`;
            }

            if (isCancelled) return;

            // Update progress text for final steps
            const progressText = document.getElementById('download-progress-text');
            if (progressText) progressText.textContent = 'Finalizing ZIP file...';

            // Add metadata
            const metadata = {
                app: 'VFT Photosphere Camera',
                version: '1.1.4',
                exportDate: new Date().toISOString(),
                photosphereCount: panoramas.length,
                photospheres: panoramas.map(p => ({
                    timestamp: p.timestamp,
                    imageCount: p.imageCount || 36
                }))
            };
            zip.file('metadata.json', JSON.stringify(metadata, null, 2));

            // Generate ZIP with progress callback
            const content = await zip.generateAsync({
                type: 'blob',
                compression: 'DEFLATE',
                compressionOptions: { level: 6 }
            }, (metadata) => {
                if (isCancelled) return;
                const percent = Math.round(metadata.percent);
                const progressBar = document.getElementById('download-progress-bar');
                const progressText = document.getElementById('download-progress-text');
                if (progressBar) progressBar.style.width = `${percent}%`;
                if (progressText) progressText.textContent = `Compressing: ${percent}%...`;
            });

            if (isCancelled) return;

            // Update progress to complete
            const progressBar = document.getElementById('download-progress-bar');
            const progressTextFinal = document.getElementById('download-progress-text');
            if (progressBar) progressBar.style.width = '100%';
            if (progressTextFinal) progressTextFinal.textContent = 'Preparing download...';

            // Download the file automatically
            const url = URL.createObjectURL(content);
            const link = document.createElement('a');
            const exportTimestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
            link.download = `vftcam_photospheres_${exportTimestamp}.zip`;
            link.href = url;

            // Wrap in try-catch to ensure button gets re-enabled even if download fails
            try {
                link.click();
            } catch (error) {
                console.error('Download click failed:', error);
            }

            // Give a small delay to ensure download initiated, then clean up and enable OK
            setTimeout(() => {
                URL.revokeObjectURL(url);

                // Update message to show download complete
                const progressTextComplete = document.getElementById('download-progress-text');
                if (progressTextComplete) progressTextComplete.textContent = '✓ Download complete! File saved to your Downloads folder.';

                // Enable OK button now that download is complete
                const dialogButtons = document.querySelectorAll('#dialog-buttons button');
                if (dialogButtons && dialogButtons[0]) {
                    const okBtn = dialogButtons[0];
                    okBtn.disabled = false;
                    okBtn.style.opacity = '1';
                    okBtn.style.cursor = 'pointer';
                }
            }, 500);

            // Wait for user to close dialog
            await dialogPromise;

        } catch (error) {
            console.error('Error downloading all:', error);
            await this.app.cardUI.alert('Failed to create download. Please try again.', 'Download Error');
        }
    }

    /**
     * Delete a photosphere after confirmation
     * Shows confirmation dialog and removes from database
     * Prevents concurrent delete operations with mutex
     * @param {number} index - Index of panorama in loaded array
     */

    /**
     * Upload a single panorama (by index) to the server.
     * Shows a success / error modal when done.
     */
    async syncToSystem(index) {
        try {
            const panoramas = await this.database.loadAllPanoramas();
            const pano = panoramas[index];
            if (!pano) {
                await this.app.cardUI.alert('Panorama not found.', 'Sync Error');
                return;
            }

            // Resolve the blob
            let blob = pano.imageBlob;
            if (!blob && pano.imageData) {
                const base64 = pano.imageData.replace(/^data:image\/\w+;base64,/, '');
                const byteChars = atob(base64);
                const byteArr = new Uint8Array(byteChars.length);
                for (let i = 0; i < byteChars.length; i++) byteArr[i] = byteChars.charCodeAt(i);
                blob = new Blob([byteArr], { type: 'image/jpeg' });
            }
            if (!blob) {
                await this.app.cardUI.alert('Cannot read panorama image data.', 'Sync Error');
                return;
            }

            // Show loading state on the button that triggered this
            const btn = document.querySelector(`[onclick*="syncToSystem(${index})"]`);
            if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }

            const result = await this.app.uploadPanoramaToServer(blob, pano.width || 0, pano.height || 0, pano.imageCount || 36);

            if (btn) { btn.disabled = false; btn.style.opacity = '1'; }

            this._showSyncSuccess(1);
        } catch (err) {
            console.error('syncToSystem error:', err);
            await this.app.cardUI.alert('Upload failed: ' + err.message, 'Sync Error');
        }
    }

    /**
     * Upload ALL panoramas in the local roll to the server.
     * Shows a success / error modal when done.
     */
    async syncAllToSystem() {
        const panoramas = await this.database.loadAllPanoramas();
        if (!panoramas.length) {
            await this.app.cardUI.alert('No 360 captures to sync.', 'Nothing to Sync');
            return;
        }

        const btn = document.getElementById('sync-all-btn');
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.textContent = `Syncing 0 / ${panoramas.length}…`;
        }

        let success = 0;
        let failed = 0;
        for (let i = 0; i < panoramas.length; i++) {
            const pano = panoramas[i];
            try {
                let blob = pano.imageBlob;
                if (!blob && pano.imageData) {
                    const base64 = pano.imageData.replace(/^data:image\/\w+;base64,/, '');
                    const byteChars = atob(base64);
                    const byteArr = new Uint8Array(byteChars.length);
                    for (let j = 0; j < byteChars.length; j++) byteArr[j] = byteChars.charCodeAt(j);
                    blob = new Blob([byteArr], { type: 'image/jpeg' });
                }
                if (blob) {
                    await this.app.uploadPanoramaToServer(blob, pano.width || 0, pano.height || 0, pano.imageCount || 36);
                    success++;
                } else {
                    failed++;
                }
            } catch (e) {
                console.warn('Failed to sync panorama', i, e);
                failed++;
            }
            if (btn) btn.textContent = `Syncing ${i + 1} / ${panoramas.length}…`;
        }

        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerHTML = `<svg width="20" height="20" fill="none" stroke="rgb(52,211,153)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path></svg> Sync All to System`;
        }

        this._showSyncSuccess(success, failed);
    }

    /**
     * Show a stylised success (or partial) modal after a sync operation.
     */
    _showSyncSuccess(success, failed = 0) {
        const total = success + failed;
        const allOk = failed === 0;
        const title = allOk ? '✅ Synced to System!' : `⚠️ Sync Partial`;
        const message = allOk
            ? `${success} panorama${success > 1 ? 's' : ''} successfully saved to the system.

You can now view, download, and attach them to tour scenes in the AI Tools page.`
            : `${success} panorama${success > 1 ? 's' : ''} uploaded, ${failed} failed.

Check your internet connection and try again for the failed ones.`;
        if (this.app && this.app.cardUI) {
            this.app.cardUI.alert(message, title);
        }
    }

    async deletePanorama(index) {
        // Mutex prevents multiple simultaneous deletes
        if (this.isDeleting) return;

        // Use card UI confirmation if available, fallback to browser confirm
        const shouldDelete = this.app && this.app.cardUI ?
            await this.app.cardUI.confirm(
                'Are you sure you want to delete this photosphere?\n\nThis action cannot be undone.',
                'Delete Photosphere?'
            ) :
            confirm('Are you sure you want to delete this photosphere? This action cannot be undone.');

        if (!shouldDelete) {
            return;
        }

        this.isDeleting = true; // Set mutex

        try {
            const panoramas = await this.database.loadAllPanoramas();
            const pano = panoramas[index];

            if (pano && pano.id) {
                console.log(`Deleting panorama with id: ${pano.id}`);
                // Delete using IndexedDB auto-generated key
                await this.database.deletePanorama(pano.id);
                // Refresh gallery display
                await this.loadCameraRoll();
            } else {
                console.error('Panorama not found or missing id:', pano);
                if (this.app && this.app.cardUI) {
                    await this.app.cardUI.alert('Unable to delete photosphere. Please try again.', 'Delete Error');
                } else {
                    alert('Unable to delete photosphere. Please try again.');
                }
            }
        } catch (error) {
            console.error('Error deleting panorama:', error);
            if (this.app && this.app.cardUI) {
                await this.app.cardUI.alert('Failed to delete photosphere. Please try again.', 'Delete Error');
            } else {
                alert('Failed to delete photosphere. Please try again.');
            }
        } finally {
            this.isDeleting = false; // Always release mutex
        }
    }
}