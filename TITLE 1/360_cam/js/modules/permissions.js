export class PermissionsManager {
    constructor(app) {
        this.app = app;
        this.permissionStates = {
            camera: 'unknown',
            motion: 'unknown',
            location: 'unknown',
            offline: 'unknown',
            storage: 'unknown'
        };
        this.compassUtils = null; // Will be loaded when needed
        this.statusCheckInterval = null;
        this.pwaStatus = null;
    }

    async checkCameraPermission() {
        try {
            const result = await navigator.permissions.query({ name: 'camera' });
            this.permissionStates.camera = result.state;
            return result.state;
        } catch (error) {
            // Permissions API not supported (e.g., Safari)
            // Don't call getUserMedia here as it will trigger permission prompt
            // Instead, assume 'prompt' state unless we know otherwise
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                // Check if we have an active camera stream from the app
                if (this.app && this.app.camera && this.app.camera.stream) {
                    this.permissionStates.camera = 'granted';
                    return 'granted';
                }
                // Otherwise assume prompt state - permission will be requested when needed
                this.permissionStates.camera = 'prompt';
                return 'prompt';
            }
            this.permissionStates.camera = 'unavailable';
            return 'unavailable';
        }
    }

    async checkMotionPermission() {
        if (typeof DeviceOrientationEvent === 'undefined') {
            this.permissionStates.motion = 'unavailable';
            return 'unavailable';
        }

        if (typeof DeviceOrientationEvent.requestPermission === 'function') {
            // iOS 13+ requires permission
            const lastKnownState = localStorage.getItem('motionPermissionState');
            if (lastKnownState === 'granted') {
                this.permissionStates.motion = 'granted';
                return 'granted';
            } else if (lastKnownState === 'denied') {
                this.permissionStates.motion = 'denied';
                return 'denied';
            }
            // If no stored state, assume prompt
            this.permissionStates.motion = 'prompt';
            return 'prompt';
        } else {
            // Non-iOS devices don't need permission
            this.permissionStates.motion = 'granted';
            return 'granted';
        }
    }

    async checkOfflineStatus() {
        // Initialize PWA status if not already done
        if (!this.pwaStatus) {
            const { PWAStatus } = await import('./pwa-status.js');
            this.pwaStatus = new PWAStatus();
        }
        
        const status = this.pwaStatus.getStatus();
        
        // Use the isOfflineReady property which checks for service worker AND (installed OR persistent)
        if (status.isOfflineReady) {
            this.permissionStates.offline = 'granted';
            return 'granted';
        } else if (status.hasServiceWorker) {
            this.permissionStates.offline = 'prompt';
            return 'prompt';
        } else {
            this.permissionStates.offline = 'denied';
            return 'denied';
        }
    }
    
    async checkStorageStatus() {
        // Initialize PWA status if not already done
        if (!this.pwaStatus) {
            const { PWAStatus } = await import('./pwa-status.js');
            this.pwaStatus = new PWAStatus();
        }
        
        const status = this.pwaStatus.getStatus();
        
        // Only show storage protection if installed as PWA
        if (!status.isInstalled) {
            this.permissionStates.storage = 'hidden';
            return 'hidden';
        }
        
        // Check if storage is persistent
        if (status.isPersistent) {
            this.permissionStates.storage = 'granted';
            return 'granted';
        } else {
            this.permissionStates.storage = 'prompt';
            return 'prompt';
        }
    }

    async checkLocationPermission() {
        if (!navigator.geolocation) {
            this.permissionStates.location = 'unavailable';
            return 'unavailable';
        }

        // Check localStorage first since it's more reliable
        const lastKnownState = localStorage.getItem('locationPermissionState');
        console.log('Location permission from localStorage:', lastKnownState);
        
        if (lastKnownState === 'granted') {
            this.permissionStates.location = 'granted';
            return 'granted';
        } else if (lastKnownState === 'denied') {
            this.permissionStates.location = 'denied';
            return 'denied';
        }

        // Fall back to Permissions API if no localStorage value
        try {
            const result = await navigator.permissions.query({ name: 'geolocation' });
            console.log('Location permission from API:', result.state);
            // Only use API result if we don't have a localStorage value
            if (!lastKnownState) {
                this.permissionStates.location = result.state;
                return result.state;
            }
        } catch (error) {
            // Safari doesn't support permissions.query for geolocation
            console.log('Permissions API not supported for geolocation');
        }
        
        // Default to prompt if nothing else
        this.permissionStates.location = 'prompt';
        return 'prompt';
    }

    async requestCameraPermission() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                    facingMode: 'environment'
                } 
            });
            stream.getTracks().forEach(track => track.stop());
            this.permissionStates.camera = 'granted';
            return 'granted';
        } catch (error) {
            if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                this.permissionStates.camera = 'denied';
                return 'denied';
            }
            console.error('Camera permission error:', error);
            return 'error';
        }
    }

    async requestMotionPermission() {
        if (typeof DeviceOrientationEvent.requestPermission === 'function') {
            try {
                const permission = await DeviceOrientationEvent.requestPermission();
                this.permissionStates.motion = permission;
                localStorage.setItem('motionPermissionState', permission);
                return permission;
            } catch (error) {
                console.error('Motion permission error:', error);
                return 'error';
            }
        } else {
            window.addEventListener('deviceorientation', () => {}, { once: true });
            this.permissionStates.motion = 'granted';
            return 'granted';
        }
    }

    async requestLocationPermission() {
        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.permissionStates.location = 'granted';
                    localStorage.setItem('locationPermissionState', 'granted');
                    resolve('granted');
                },
                (error) => {
                    if (error.code === error.PERMISSION_DENIED) {
                        this.permissionStates.location = 'denied';
                        localStorage.setItem('locationPermissionState', 'denied');
                        resolve('denied');
                    } else {
                        resolve('error');
                    }
                },
                {
                    enableHighAccuracy: false,
                    timeout: 5000,
                    maximumAge: Infinity
                }
            );
        });
    }

    async checkAllPermissions() {
        await Promise.all([
            this.checkCameraPermission(),
            this.checkMotionPermission(),
            this.checkLocationPermission(),
            this.checkOfflineStatus(),
            this.checkStorageStatus()
        ]);
        return this.permissionStates;
    }

    startStatusMonitoring(callback) {
        this.stopStatusMonitoring();
        
        // Initial check
        this.checkAllPermissions().then(states => {
            console.log('Initial permission check:', states);
            callback(states);
        });
        
        // Periodic checks
        this.statusCheckInterval = setInterval(async () => {
            const states = await this.checkAllPermissions();
            console.log('Periodic permission check:', states);
            callback(states);
        }, 1000);
    }

    stopStatusMonitoring() {
        if (this.statusCheckInterval) {
            clearInterval(this.statusCheckInterval);
            this.statusCheckInterval = null;
        }
    }

    getPermissionInfo(type) {
        const info = {
            camera: {
                title: 'Camera',
                description: 'Required to capture 360° photos',
                icon: '<img src="./img/camera.svg" style="width: 24px; height: 24px; filter: brightness(0) invert(1);" alt=""/>',
                required: true
            },
            motion: {
                title: 'Motion & Orientation',
                description: 'Required for aligning capture points',
                icon: '<img src="./img/compass.svg" style="width: 24px; height: 24px; filter: brightness(0) invert(1);" alt=""/>',
                required: true,
                iosNote: 'On iOS, if previously denied, you will need to delete browsing data and refresh the app.',
            },
            location: {
                title: 'Location',
                description: 'Optional: Embeds GPS data into stitched photospheres',
                icon: '<img src="./img/geo-alt.svg" style="width: 24px; height: 24px; filter: brightness(0) invert(1);" alt=""/>',
                required: false
            },
            offline: {
                title: 'Offline Ready',
                description: 'App can work without internet connection',
                icon: '<img src="./img/cloud-slash.svg" style="width: 24px; height: 24px; filter: brightness(0) invert(1);" alt=""/>',
                required: false
            },
            storage: {
                title: 'Storage Protection',
                description: 'Prevent automatic deletion of your photospheres',
                icon: '<img src="./img/database-lock.svg" style="width: 24px; height: 24px; filter: brightness(0) invert(1);" alt=""/>',
                required: false
            }
        };
        return info[type];
    }

    async showPermissionsCard() {
        if (!this.app.cardUI) return;

        // Hide settings menu first
        await this.app.cardUI.hideSettingsMenu();
        
        // Check all permissions before showing the card
        await this.checkAllPermissions();
        console.log('Current permission states:', this.permissionStates);

        // Include storage only if installed as PWA
        const permissionTypes = ['camera', 'motion', 'location', 'offline'];
        if (this.permissionStates.storage !== 'hidden') {
            permissionTypes.push('storage');
        }
        
        const cardContent = document.createElement('div');
        cardContent.innerHTML = `
            <div class="permissions-card">
                <div class="permissions-list" style="display: flex; flex-direction: column; gap: 15px;">
                    ${permissionTypes.map(type => this.createPermissionItem(type)).join('')}
                </div>
                <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 10px;">
                    <p style="margin: 0; font-size: 14px; color: rgba(255, 255, 255, 0.8); line-height: 1.5;">
                        Tap any permission to request access. Resetting some permissions may require deleting browsing data and refreshing the app, or system settings changes.
                    </p>
                </div>
                
                <!-- Compass Calibration Section -->
                <div style="margin-top: 20px; padding: 15px; background: rgba(139, 69, 19, 0.1); border: 1px solid rgba(139, 69, 19, 0.3); border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: white; display: flex; align-items: center; gap: 10px;">
                        <img src="./img/compass.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt=""/>
                        Compass Calibration
                    </h3>
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                        <div style="flex: 1;">
                            <div style="font-size: 32px; font-weight: bold; color: white;" id="compass-heading-display">---°</div>
                            <div style="font-size: 12px; color: rgba(255, 255, 255, 0.6);" id="compass-status">Not initialized</div>
                        </div>
                        <button id="enable-compass-btn" style="
                            padding: 10px 20px;
                            background: #8C1515;
                            color: white;
                            border: none;
                            border-radius: 8px;
                            font-size: 14px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        ">
                            Enable Compass
                        </button>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="calibrate-north-btn" style="
                            flex: 1;
                            padding: 8px;
                            background: rgba(255,255,255,0.05);
                            color: white;
                            border: 1px solid rgba(255,255,255,0.2);
                            border-radius: 8px;
                            font-size: 13px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                            opacity: 0.5;
                        " disabled>
                            Point at North & Calibrate
                        </button>
                        <button id="reset-calibration-btn" style="
                            padding: 8px 15px;
                            background: rgba(255,255,255,0.05);
                            color: white;
                            border: 1px solid rgba(255,255,255,0.2);
                            border-radius: 8px;
                            font-size: 13px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                            opacity: 0.5;
                        " disabled>
                            Reset
                        </button>
                    </div>
                </div>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: rgba(255, 255, 255, 0.6); text-transform: uppercase; letter-spacing: 1px;">Developer Tools</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <button id="toggle-debug-btn" style="
                            flex: 1;
                            min-width: 120px;
                            padding: 10px;
                            background: rgba(255,255,255,0.05);
                            border: 1px solid rgba(255,255,255,0.2);
                            border-radius: 8px;
                            color: white;
                            font-size: 13px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
                            Toggle Debug
                        </button>
                    </div>
                </div>
            </div>
        `;

        const card = await this.app.cardUI.createCard({
            id: 'permissions-card',
            title: 'Permissions',
            content: cardContent.innerHTML,
            className: 'permissions-info-card',
            zIndex: 1005  // Higher than settings menu
        });

        this.startStatusMonitoring((states) => {
            this.updatePermissionDisplay(states);
        });

        permissionTypes.forEach(type => {
            const button = document.querySelector(`#permission-${type}-btn`);
            if (button) {
                button.addEventListener('click', () => this.handlePermissionRequest(type));
            }
        });

        // Add toggle debug button handler
        const toggleDebugBtn = document.querySelector('#toggle-debug-btn');
        if (toggleDebugBtn) {
            toggleDebugBtn.addEventListener('click', () => {
                let debugPanel = document.getElementById('debug-panel');
                
                // If debug panel doesn't exist, enable debug mode to create it
                if (!debugPanel && this.app && this.app.debug) {
                    this.app.debug.enable(true);
                    debugPanel = document.getElementById('debug-panel');
                    toggleDebugBtn.textContent = 'Hide Debug';
                } else if (debugPanel) {
                    // Toggle visibility if it exists
                    const isVisible = debugPanel.style.display !== 'none';
                    debugPanel.style.display = isVisible ? 'none' : 'block';
                    toggleDebugBtn.textContent = isVisible ? 'Show Debug' : 'Hide Debug';
                }
            });
        }
        
        // Initialize compass calibration UI
        await this.initializeCompassUI();

        const closeBtn = card.querySelector('.card-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.stopStatusMonitoring();
                // Stop compass if running
                if (this.compassUtils && this.compassUtils.isListening) {
                    this.compassUtils.stopListening();
                }
                this.app.cardUI.hideCard('permissions-card');
            });
        }

        return card;
    }

    createPermissionItem(type) {
        const info = this.getPermissionInfo(type);
        const state = this.permissionStates[type];
        console.log(`Creating permission item for ${type}: state = ${state}`);
        const statusIcon = this.getStatusIcon(state);
        const statusColor = this.getStatusColor(state);
        
        // Special handling for offline status
        let statusText = '';
        if (type === 'offline') {
            if (this.pwaStatus) {
                const status = this.pwaStatus.getStatus();
                if (status.isOfflineReady) {
                    statusText = status.isInstalled ? 'Installed & Ready' : 'Persistent Storage & Ready';
                } else if (status.hasServiceWorker) {
                    statusText = 'Service Worker Active • Tap to Install';
                } else if (!status.hasServiceWorker) {
                    statusText = 'Not Available';
                }
            }
        }

        return `
            <button id="permission-${type}-btn" class="permission-item" style="
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 15px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.2s ease;
                text-align: left;
                width: 100%;
            " onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
                <div style="font-size: 24px;">${info.icon}</div>
                <div style="flex: 1;">
                    <div style="font-size: 16px; font-weight: 600; margin-bottom: 4px; color: white; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">${info.title}</div>
                    <div style="font-size: 13px; color: rgba(255, 255, 255, 0.7);">${info.description}</div>
                    ${statusText ? `<div id="permission-${type}-text" style="font-size: 12px; color: ${state === 'prompt' ? 'rgba(255, 193, 7, 1)' : 'rgba(76, 175, 80, 1)'}; margin-top: 4px; font-weight: 500;">${statusText}</div>` : ''}
                    ${info.iosNote ? `<div style="font-size: 11px; color: rgba(255, 255, 255, 0.5); margin-top: 4px;">${info.iosNote}</div>` : ''}
                </div>
                <div id="permission-${type}-status" style="
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    background: ${statusColor};
                    color: white;
                    font-size: 20px;
                    font-weight: bold;
                    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
                ">
                    ${statusIcon}
                </div>
            </button>
        `;
    }

    getStatusIcon(state) {
        switch (state) {
            case 'granted': return '<img src="./img/check.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt=""/>';
            case 'denied': return '<img src="./img/x.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt=""/>';
            case 'prompt': return '<img src="./img/question.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt=""/>';
            case 'unavailable': return '<img src="./img/dash.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt=""/>';
            default: return '?';
        }
    }

    getStatusColor(state) {
        switch (state) {
            case 'granted': return 'rgba(76, 175, 80, 0.8)';
            case 'denied': return 'rgba(244, 67, 54, 0.8)';
            case 'prompt': return 'rgba(255, 152, 0, 0.8)';
            case 'unavailable': return 'rgba(158, 158, 158, 0.5)';
            default: return 'rgba(158, 158, 158, 0.5)';
        }
    }

    updatePermissionDisplay(states) {
        console.log('Updating permission display with states:', states);
        Object.keys(states).forEach(type => {
            const statusEl = document.querySelector(`#permission-${type}-status`);
            if (statusEl) {
                const state = states[type];
                console.log(`Updating ${type} to ${state}`);
                statusEl.innerHTML = this.getStatusIcon(state);
                statusEl.style.background = this.getStatusColor(state);
            } else {
                console.log(`Element #permission-${type}-status not found`);
            }
            
            // Update offline status text if it exists
            if (type === 'offline') {
                const offlineTextEl = document.querySelector(`#permission-offline-text`);
                if (offlineTextEl && this.pwaStatus) {
                    const status = this.pwaStatus.getStatus();
                    let statusText = '';
                    if (status.hasServiceWorker && status.isInstalled) {
                        statusText = 'Installed & Ready';
                    } else if (status.hasServiceWorker && !status.isInstalled) {
                        statusText = 'Service Worker Active • Tap to Install';
                    } else if (!status.hasServiceWorker) {
                        statusText = 'Not Available';
                    }
                    offlineTextEl.textContent = statusText;
                    const offlineState = states[type];
                    offlineTextEl.style.color = offlineState === 'prompt' ? 'rgba(255, 193, 7, 1)' : 'rgba(76, 175, 80, 1)';
                }
            }
        });
    }

    async handlePermissionRequest(type) {
        const currentState = this.permissionStates[type];
        
        if (currentState === 'granted') {
            await this.app.cardUI.alert(`${this.getPermissionInfo(type).title} permission is already granted.`, 'Permission Granted');
            return;
        }

        if (currentState === 'unavailable') {
            await this.app.cardUI.alert(`${this.getPermissionInfo(type).title} is not available on this device or browser.`, 'Not Available');
            return;
        }
        
        // Special handling for offline status
        if (type === 'offline') {
            await this.handleOfflineRequest();
            return;
        }
        
        // Special handling for storage protection
        if (type === 'storage') {
            await this.handleStorageRequest();
            return;
        }

        if (currentState === 'denied') {
            const info = this.getPermissionInfo(type);
            let message = `${info.title} permission was previously denied.\n\n`;
            
            if (type === 'camera') {
                message += 'To enable camera access:\n';
                message += '1. Click the lock/info icon in your browser\'s address bar\n';
                message += '2. Find "Camera" and change it to "Allow"\n';
                message += '3. Refresh the page';
            } else if (type === 'motion' && info.iosNote) {
                message += info.iosNote;
            } else if (type === 'location') {
                message += 'To enable location access:\n';
                message += '1. Click the lock/info icon in your browser\'s address bar\n';
                message += '2. Find "Location" and change it to "Allow"\n';
                message += '3. Refresh the page';
            }

            const tryAgain = await this.app.cardUI.confirm(message + '\n\nWould you like to try requesting permission again?', 'Permission Denied');
            
            if (!tryAgain) return;
        }

        let result;
        switch (type) {
            case 'camera':
                result = await this.requestCameraPermission();
                break;
            case 'motion':
                result = await this.requestMotionPermission();
                break;
            case 'location':
                result = await this.requestLocationPermission();
                break;
        }

        await this.checkAllPermissions();
        this.updatePermissionDisplay(this.permissionStates);

        if (result === 'granted') {
            await this.app.cardUI.alert(`${this.getPermissionInfo(type).title} permission granted successfully!`, 'Success');
        } else if (result === 'denied') {
            await this.app.cardUI.alert(`${this.getPermissionInfo(type).title} permission was denied. You can change this in your browser settings.`, 'Permission Denied');
        }
    }
    
    async handleOfflineRequest() {
        if (!this.pwaStatus) {
            const { PWAStatus } = await import('./pwa-status.js');
            this.pwaStatus = new PWAStatus();
        }
        
        const status = this.pwaStatus.getStatus();
        const instructions = this.pwaStatus.getInstallInstructions();
        
        if (status.isInstalled) {
            await this.app.cardUI.alert('The app is already installed and offline-ready!', 'Offline Ready');
            return;
        }
        
        // For iOS, show a bottom sheet style instruction
        if (instructions.title.includes('iPhone') || instructions.title.includes('iPad')) {
            // Create a bottom sheet overlay
            const overlay = document.createElement('div');
            overlay.id = 'ios-install-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 9999;
                display: flex;
                align-items: flex-end;
                animation: fadeIn 0.3s ease;
            `;
            
            const sheet = document.createElement('div');
            sheet.style.cssText = `
                width: 100%;
                background: rgba(50, 50, 50, 0.95);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 20px 20px 0 0;
                padding: 30px 20px;
                padding-bottom: max(30px, env(safe-area-inset-bottom, 30px));
                animation: slideUp 0.3s ease;
                text-align: center;
            `;
            
            sheet.innerHTML = `
                <div style="
                    width: 40px;
                    height: 4px;
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 2px;
                    margin: 0 auto 20px;
                "></div>
                <p style="
                    color: rgba(255, 255, 255, 0.9);
                    font-size: 18px;
                    margin: 0;
                    line-height: 1.5;
                ">
                    Just tap 
                    <img src="./img/box-arrow-up.svg" style="
                        width: 20px;
                        height: 20px;
                        filter: brightness(0) invert(1);
                        margin: 0 4px;
                    " alt="Share"> below, 
                    then<br/>
                    <span style="
                        display: inline-flex;
                        align-items: center;
                        padding: 2px 8px;
                        background: rgba(255, 255, 255, 0.1);
                        border-radius: 6px;
                        margin-left: 4px;
                    ">
                        Add to Home Screen&nbsp;
                        <img src="./img/plus-square.svg" style="
                            width: 20px;
                            height: 20px;
                            filter: brightness(0) invert(1);
                            margin-right: 6px;
                        " alt="Add">
                    </span>
                </p>
                <style>
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideUp {
                        from { transform: translateY(100%); }
                        to { transform: translateY(0); }
                    }
                </style>
            `;
            
            overlay.appendChild(sheet);
            document.body.appendChild(overlay);
            
            // Close on tap outside
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.remove();
                }
            });
            
            // Auto-close after 10 seconds
            setTimeout(() => {
                if (document.getElementById('ios-install-overlay')) {
                    overlay.remove();
                }
            }, 10000);
            
            return;
        }
        
        // For other platforms, show the detailed instructions
        const instructionHTML = `
            <div style="padding: 10px;">
                <h3 style="color: white; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">${instructions.icon}</span>
                    ${instructions.title}
                </h3>
                
                ${instructions.warning ? `
                    <div style="background: rgba(255, 100, 100, 0.2); border: 1px solid rgba(255, 100, 100, 0.4); border-radius: 8px; padding: 10px; margin-bottom: 15px;">
                        <p style="margin: 0; color: white; font-size: 14px;">⚠️ ${instructions.warning}</p>
                    </div>
                ` : ''}
                
                <ol style="margin: 15px 0; padding-left: 20px;">
                    ${instructions.steps.map(step => `
                        <li style="margin: 8px 0; color: rgba(255, 255, 255, 0.9);">${step}</li>
                    `).join('')}
                </ol>
                
                <div style="background: rgba(76, 175, 80, 0.2); border: 1px solid rgba(76, 175, 80, 0.4); border-radius: 8px; padding: 10px; margin-top: 15px;">
                    <p style="margin: 0; color: white; font-size: 14px;">
                        ✅ Installing protects your photospheres from automatic deletion
                    </p>
                </div>
                
                ${instructions.canAutoPrompt && window.deferredInstallPrompt ? `
                    <button onclick="window.sphereCapture?.permissions?.installNow()" style="
                        width: 100%;
                        margin-top: 15px;
                        padding: 12px;
                        background: linear-gradient(135deg, #4CAF50, #45a049);
                        border: none;
                        border-radius: 8px;
                        color: white;
                        font-weight: 600;
                        cursor: pointer;
                    ">Install Now</button>
                ` : ''}
            </div>
        `;
        
        await this.app.cardUI.showDialog(instructionHTML, 'Installation Instructions', [{
            text: 'Got it',
            style: 'default',
            callback: () => {}
        }]);
        
        // Re-check status after closing
        await this.checkOfflineStatus();
        this.updatePermissionDisplay(this.permissionStates);
    }
    
    async handleStorageRequest() {
        if (!this.pwaStatus) {
            const { PWAStatus } = await import('./pwa-status.js');
            this.pwaStatus = new PWAStatus();
        }
        
        const status = this.pwaStatus.getStatus();
        
        if (status.isPersistent) {
            await this.app.cardUI.alert('Storage protection is already enabled! Your photospheres are protected from automatic deletion.', 'Already Protected');
            return;
        }
        
        // Request persistent storage
        const granted = await this.pwaStatus.requestPersistence();
        
        if (granted) {
            await this.app.cardUI.alert('Storage protection enabled! Your photospheres are now protected from automatic deletion.', 'Success');
            // Re-check status and update display
            await this.checkStorageStatus();
            this.updatePermissionDisplay(this.permissionStates);
        } else {
            await this.app.cardUI.alert('Storage protection could not be enabled. Your browser may not support this feature or may have denied the request.', 'Request Denied');
        }
    }
    
    async installNow() {
        if (this.pwaStatus && window.deferredInstallPrompt) {
            const installed = await this.pwaStatus.showInstallPrompt();
            if (installed) {
                await this.checkOfflineStatus();
                this.updatePermissionDisplay(this.permissionStates);
                await this.app.cardUI.alert('App installed successfully! You can now use it offline.', 'Installation Complete');
            }
        }
    }
    
    async initializeCompassUI() {
        const enableBtn = document.querySelector('#enable-compass-btn');
        const calibrateBtn = document.querySelector('#calibrate-north-btn');
        const resetBtn = document.querySelector('#reset-calibration-btn');
        const headingDisplay = document.querySelector('#compass-heading-display');
        const statusDisplay = document.querySelector('#compass-status');
        
        if (!enableBtn) return;
        
        // Load CompassUtils if not already loaded
        if (!this.compassUtils) {
            const { CompassUtils } = await import('./compass-utils.js');
            this.compassUtils = new CompassUtils();
            
            // Set up heading update callback
            this.compassUtils.onHeadingUpdate = (heading, hasAbsolute) => {
                if (headingDisplay) {
                    headingDisplay.textContent = `${heading}°`;
                }
                if (statusDisplay) {
                    if (hasAbsolute) {
                        statusDisplay.textContent = 'True compass (absolute)';
                        statusDisplay.style.color = '#4CAF50';
                    } else {
                        statusDisplay.textContent = 'Relative orientation';
                        statusDisplay.style.color = '#FFA500';
                    }
                }
                
                // Enable calibration buttons when we have a heading
                if (calibrateBtn) {
                    calibrateBtn.disabled = false;
                    calibrateBtn.style.opacity = '1';
                }
                if (resetBtn) {
                    resetBtn.disabled = false;
                    resetBtn.style.opacity = '1';
                }
            };
        }
        
        // Enable compass button handler
        enableBtn.addEventListener('click', async () => {
            enableBtn.textContent = 'Requesting...';
            enableBtn.disabled = true;
            
            const result = await this.compassUtils.requestIOSPermission();
            
            if (result.granted) {
                enableBtn.textContent = 'Compass Active';
                enableBtn.style.background = '#4CAF50';
                
                if (statusDisplay) {
                    statusDisplay.textContent = 'Initializing...';
                }
                
                // Start listening for compass events
                this.compassUtils.startListening();
            } else {
                enableBtn.textContent = 'Permission Denied';
                enableBtn.style.background = '#f44336';
                
                if (statusDisplay) {
                    statusDisplay.textContent = result.message;
                    statusDisplay.style.color = '#f44336';
                }
                
                // Re-enable button to try again
                setTimeout(() => {
                    enableBtn.disabled = false;
                    enableBtn.textContent = 'Enable Compass';
                    enableBtn.style.background = '#8C1515';
                }, 3000);
            }
        });
        
        // Calibrate button handler
        if (calibrateBtn) {
            calibrateBtn.addEventListener('click', () => {
                const success = this.compassUtils.calibrateToNorth(0);
                
                if (success) {
                    // Visual feedback
                    calibrateBtn.textContent = 'Calibrated!';
                    calibrateBtn.style.background = '#4CAF50';
                    
                    setTimeout(() => {
                        calibrateBtn.textContent = 'Point at North & Calibrate';
                        calibrateBtn.style.background = 'rgba(255,255,255,0.05)';
                    }, 2000);
                    
                    // Update app's compass offset if needed
                    if (this.app && this.app.compassOffset !== undefined) {
                        this.app.compassOffset = this.compassUtils.userOffset;
                        console.log(`App compass offset updated: ${this.compassUtils.userOffset.toFixed(1)}°`);
                    }
                }
            });
        }
        
        // Reset calibration button handler
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.compassUtils.userOffset = 0;
                
                // Visual feedback
                resetBtn.textContent = 'Reset!';
                resetBtn.style.background = '#FFA500';
                
                setTimeout(() => {
                    resetBtn.textContent = 'Reset';
                    resetBtn.style.background = 'rgba(255,255,255,0.05)';
                }, 1000);
                
                // Update app's compass offset if needed
                if (this.app && this.app.compassOffset !== undefined) {
                    this.app.compassOffset = 0;
                    console.log('App compass offset reset to 0°');
                }
            });
        }
        
        // Auto-start compass if available
        // Check if iOS with permission or non-iOS (doesn't need permission)
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        if (this.permissionStates.motion === 'granted' || !isIOS) {
            // For non-iOS or iOS with permission, start compass
            if (!isIOS) {
                // Non-iOS doesn't need permission, just start listening
                this.compassUtils.startListening();
                enableBtn.textContent = 'Compass Active';
                enableBtn.style.background = '#4CAF50';
                enableBtn.disabled = true;
                if (statusDisplay) {
                    statusDisplay.textContent = 'Initializing...';
                }
            } else {
                // iOS with permission - click the button to trigger the flow
                setTimeout(() => {
                    enableBtn.click();
                }, 500);
            }
        }
    }
}