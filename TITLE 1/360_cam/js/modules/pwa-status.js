/**
 * PWA Status Checker
 * Monitors Progressive Web App installation status and capabilities
 */

export class PWAStatus {
    constructor() {
        this.status = {
            isInstalled: false,
            hasServiceWorker: false,
            isOfflineReady: false,
            isPersistent: false,
            installable: false
        };
        this.storageEstimate = null;
        this.installInstructions = null;
    }

    /**
     * Check current PWA status
     */
    async checkStatus() {
        // Check if running as standalone PWA
        this.status.isInstalled = this.isStandaloneMode();
        
        // Check for service worker
        this.status.hasServiceWorker = await this.hasServiceWorker();
        
        // Check offline readiness
        this.status.isOfflineReady = this.status.hasServiceWorker && (this.status.isInstalled || this.status.isPersistent);
        
        // Check storage persistence
        this.status.isPersistent = await this.checkStoragePersistence();
        
        // Check if installable
        this.status.installable = this.isInstallable();
        
        // Get storage estimate
        await this.getStorageEstimate();
        
        // Set install instructions based on platform
        this.installInstructions = this.getInstallInstructions();
        
        return this.status;
    }

    /**
     * Get current status without rechecking
     */
    getStatus() {
        return this.status;
    }

    /**
     * Check if running in standalone mode (installed PWA)
     */
    isStandaloneMode() {
        return (window.matchMedia('(display-mode: standalone)').matches) ||
               (window.navigator.standalone === true) ||
               document.referrer.includes('android-app://');
    }

    /**
     * Check if service worker is registered
     */
    async hasServiceWorker() {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.getRegistration();
            return registration !== undefined;
        }
        return false;
    }

    /**
     * Check if storage is persistent
     */
    async checkStoragePersistence() {
        if (navigator.storage && navigator.storage.persist) {
            try {
                const isPersistent = await navigator.storage.persisted();
                return isPersistent;
            } catch (error) {
                console.warn('Storage persistence check failed:', error);
                return false;
            }
        }
        return false;
    }

    /**
     * Check if PWA is installable
     */
    isInstallable() {
        return window.deferredInstallPrompt !== undefined;
    }

    /**
     * Get storage usage estimate
     */
    async getStorageEstimate() {
        if (navigator.storage && navigator.storage.estimate) {
            try {
                this.storageEstimate = await navigator.storage.estimate();
            } catch (error) {
                console.warn('Storage estimate failed:', error);
            }
        }
        return this.storageEstimate;
    }

    /**
     * Get storage warning if storage is low
     */
    getStorageWarning() {
        if (!this.storageEstimate) return null;
        
        const { usage, quota } = this.storageEstimate;
        const usagePercent = (usage / quota) * 100;
        
        if (usagePercent > 80) {
            return {
                level: 'critical',
                message: 'Storage is critically low. Some photospheres may be deleted automatically.',
                usagePercent: usagePercent.toFixed(1)
            };
        } else if (usagePercent > 60) {
            return {
                level: 'warning',
                message: 'Storage is getting low. Consider clearing old photospheres.',
                usagePercent: usagePercent.toFixed(1)
            };
        }
        
        return null;
    }

    /**
     * Get platform-specific install instructions
     */
    getInstallInstructions() {
        const userAgent = navigator.userAgent;
        const platform = navigator.platform;

        // iOS
        if (/iPad|iPhone|iPod/.test(userAgent) || (platform === 'MacIntel' && navigator.maxTouchPoints > 1)) {
            return {
                title: 'Install on iPhone/iPad',
                steps: [
                    'Tap the Share button in Safari',
                    'Scroll down and tap "Add to Home Screen"',
                    'Tap "Add" in the top right corner'
                ],
                icon: 'ios-share'
            };
        }
        
        // Android
        if (/Android/.test(userAgent)) {
            return {
                title: 'Install on Android',
                steps: [
                    'Tap the menu button (three dots)',
                    'Tap "Add to Home Screen" or "Install App"',
                    'Follow the prompts to install'
                ],
                icon: 'android-install'
            };
        }
        
        // Desktop
        return {
            title: 'Install on Desktop',
            steps: [
                'Look for the install icon in your browser\'s address bar',
                'Click the install button',
                'Follow the prompts to install'
            ],
            icon: 'desktop-install'
        };
    }

    /**
     * Show install prompt if available
     */
    async showInstallPrompt() {
        if (!window.deferredInstallPrompt) {
            return false;
        }

        try {
            // Show the install prompt
            const promptResult = await window.deferredInstallPrompt.prompt();
            
            // Clear the deferred prompt
            window.deferredInstallPrompt = null;
            
            // Check if the user accepted
            if (promptResult.outcome === 'accepted') {
                await this.checkStatus();
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('Install prompt failed:', error);
            return false;
        }
    }

    /**
     * Request storage persistence
     */
    async requestStoragePersistence() {
        if (navigator.storage && navigator.storage.persist) {
            try {
                const isPersistent = await navigator.storage.persist();
                await this.checkStatus();
                return isPersistent;
            } catch (error) {
                console.error('Storage persistence request failed:', error);
                return false;
            }
        }
        return false;
    }

    /**
     * Format storage size for display
     */
    formatStorageSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Get formatted storage info
     */
    getStorageInfo() {
        if (!this.storageEstimate) {
            return {
                usage: 'Unknown',
                quota: 'Unknown',
                percent: 'Unknown'
            };
        }

        const { usage, quota } = this.storageEstimate;
        const percent = ((usage / quota) * 100).toFixed(1);

        return {
            usage: this.formatStorageSize(usage),
            quota: this.formatStorageSize(quota),
            percent: percent
        };
    }
}