/**
 * Offline Detector for Lavadora System
 * Monitors network connectivity with both browser events and active ping checks.
 * Other modules listen to this to know when to sync or to show offline UI.
 */

export class OfflineDetector {
    constructor() {
        this.isOnlineStatus = navigator.onLine;
        this.listeners = [];
        this._checkInterval = null;
        this._PING_URL = (window.IA_BASE_URL || '') + 'api/ping.php';
        this._PING_INTERVAL_MS = 30000;    // Check every 30 seconds
    }

    initialize() {
        window.addEventListener('online', () => this._handleOnline());
        window.addEventListener('offline', () => this._handleOffline());
        this._startPeriodicCheck();
        console.log(`[OfflineDetector] Initialized. Currently ${this.isOnlineStatus ? 'ONLINE' : 'OFFLINE'}`);
    }

    _handleOnline() {
        if (!this.isOnlineStatus) {
            this.isOnlineStatus = true;
            console.log('[OfflineDetector] Connection RESTORED');
            this._notifyListeners(true);
        }
    }

    _handleOffline() {
        if (this.isOnlineStatus) {
            this.isOnlineStatus = false;
            console.log('[OfflineDetector] Connection LOST');
            this._notifyListeners(false);
        }
    }

    _startPeriodicCheck() {
        this._checkInterval = setInterval(() => this._pingServer(), this._PING_INTERVAL_MS);
    }

    async _pingServer() {
        try {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 5000); // 5 second timeout
            const response = await fetch(this._PING_URL, {
                method: 'HEAD',
                cache: 'no-store',
                signal: controller.signal
            });
            clearTimeout(timeout);
            if (response.ok && !this.isOnlineStatus) {
                this._handleOnline();
            }
        } catch {
            if (this.isOnlineStatus) {
                this._handleOffline();
            }
        }
    }

    /** Returns current online status */
    isOnline() {
        return this.isOnlineStatus;
    }

    /** Register a callback to be notified when connectivity changes */
    onStatusChange(callback) {
        this.listeners.push(callback);
    }

    _notifyListeners(isOnline) {
        this.listeners.forEach(cb => cb(isOnline));
    }

    destroy() {
        window.removeEventListener('online', () => this._handleOnline());
        window.removeEventListener('offline', () => this._handleOffline());
        if (this._checkInterval) clearInterval(this._checkInterval);
    }
}
