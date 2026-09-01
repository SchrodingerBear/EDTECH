/**
 * Sync Manager for Innovatech PH
 * Handles online/offline detection and data synchronization
 */

class SyncManager {
  constructor() {
    this.isOnline = navigator.onLine;
    this.syncInProgress = false;
    this.syncInterval = null;
    this.pendingRequests = [];
    this.init();
  }

  init() {
    // Listen for online/offline events
    window.addEventListener('online', () => this.handleOnline());
    window.addEventListener('offline', () => this.handleOffline());

    // Listen for service worker messages
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data.type === 'SYNC_REQUEST') {
          this.handleServiceWorkerSyncRequest(event.data.data);
        }
      });
    }

    // Start periodic sync check
    this.startPeriodicSync();

    // Initial sync if online
    if (this.isOnline) {
      this.sync();
    }

    console.log('[SyncManager] Initialized, online:', this.isOnline);
  }

  handleOnline() {
    console.log('[SyncManager] Back online');
    this.isOnline = true;
    this.updateUI(true);
    this.sync(); // Trigger sync immediately
  }

  handleOffline() {
    console.log('[SyncManager] Gone offline');
    this.isOnline = false;
    this.updateUI(false);
  }

  updateUI(isOnline) {
    // Update offline indicator in UI
    const indicator = document.getElementById('offline-indicator');
    if (indicator) {
      indicator.className = isOnline ? 'd-none' : 'd-block';
    }

    // Update document title
    document.title = isOnline
      ? document.title.replace(' (Offline)', '')
      : document.title + ' (Offline)';

    // Show toast notification
    this.showStatusNotification(isOnline);
  }

  showStatusNotification(isOnline) {
    // Create toast if not exists
    let toast = document.getElementById('sync-status-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'sync-status-toast';
      toast.className = 'position-fixed bottom-0 end-0 p-3';
      toast.style.zIndex = '9999';
      document.body.appendChild(toast);
    }

    const message = isOnline
      ? '<i class="fas fa-check-circle text-success me-2"></i>Back online - syncing data...'
      : '<i class="fas fa-exclamation-triangle text-warning me-2"></i>You\'re offline - changes will be synced later';

    toast.innerHTML = `
      <div class="toast show">
        <div class="toast-body">
          ${message}
        </div>
      </div>
    `;

    // Auto-hide after 3 seconds
    setTimeout(() => {
      if (toast.querySelector('.toast')) {
        toast.querySelector('.toast').remove();
      }
    }, 3000);
  }

  async sync() {
    if (!this.isOnline || this.syncInProgress) {
      return;
    }

    this.syncInProgress = true;
    console.log('[SyncManager] Starting sync...');

    try {
      // Sync pending requests
      await this.syncPendingRequests();

      // Refresh cached data from server
      await this.refreshCachedData();

      console.log('[SyncManager] Sync completed');
    } catch (error) {
      console.error('[SyncManager] Sync failed:', error);
    } finally {
      this.syncInProgress = false;
    }
  }

  async syncPendingRequests() {
    const pendingRequests = await offlineDB.getPendingSyncRequests();

    if (pendingRequests.length === 0) {
      console.log('[SyncManager] No pending requests to sync');
      return;
    }

    console.log(`[SyncManager] Syncing ${pendingRequests.length} pending requests`);

    for (const request of pendingRequests) {
      try {
        await this.executeSyncRequest(request);
        await offlineDB.deleteSyncRequest(request.id);
        console.log(`[SyncManager] Synced request: ${request.id}`);
      } catch (error) {
        console.error(`[SyncManager] Failed to sync request ${request.id}:`, error);
        await offlineDB.updateSyncRequestStatus(request.id, 'failed', error.message);
      }
    }

    // Clear failed requests with too many retries
    const cleared = await offlineDB.clearFailedSyncRequests(5);
    if (cleared > 0) {
      console.log(`[SyncManager] Cleared ${cleared} failed requests`);
    }
  }

  async executeSyncRequest(request) {
    const options = {
      method: request.method,
      headers: {
        'Content-Type': 'application/json',
        ...request.headers
      }
    };

    if (request.method !== 'GET' && request.data) {
      options.body = JSON.stringify(request.data);
    }

    const response = await fetch(request.endpoint, options);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return await response.json();
  }

  async refreshCachedData() {
    // Refresh buildings
    try {
      const response = await fetch('/api/buildings');
      if (response.ok) {
        const buildings = await response.json();
        await offlineDB.cacheBuildings(buildings);
      }
    } catch (error) {
      console.error('[SyncManager] Failed to refresh buildings:', error);
    }

    // Refresh locations
    try {
      const response = await fetch('/api/locations');
      if (response.ok) {
        const locations = await response.json();
        await offlineDB.cacheLocations(locations);
      }
    } catch (error) {
      console.error('[SyncManager] Failed to refresh locations:', error);
    }

    // Refresh tours
    try {
      const response = await fetch('/api/tours');
      if (response.ok) {
        const tours = await response.json();
        await offlineDB.cacheTours(tours);
      }
    } catch (error) {
      console.error('[SyncManager] Failed to refresh tours:', error);
    }
  }

  async handleServiceWorkerSyncRequest(requestData) {
    console.log('[SyncManager] Received sync request from service worker:', requestData);

    const syncId = await offlineDB.queueSyncRequest(
      requestData.url,
      requestData.method,
      requestData.body ? JSON.parse(requestData.body) : null
    );

    // Try to sync immediately if online
    if (this.isOnline) {
      this.sync();
    }

    return syncId;
  }

  // Intercept fetch requests for offline handling
  interceptFetch() {
    const originalFetch = window.fetch;

    window.fetch = async (...args) => {
      const [url, options = {}] = args;

      // Skip non-API requests
      if (!url.toString().startsWith('/api/')) {
        return originalFetch(...args);
      }

      try {
        // Try network first
        const response = await originalFetch(...args);

        if (response.ok) {
          return response;
        }

        throw new Error('Network request failed');
      } catch (error) {
        console.log('[SyncManager] Request failed, queuing for sync:', url);

        // Queue for sync
        const syncId = await offlineDB.queueSyncRequest(
          url.toString(),
          options.method || 'GET',
          options.body ? JSON.parse(options.body) : null
        );

        // Try to return cached data
        const cacheKey = this.getCacheKey(url, options);
        const cachedData = await this.getCachedData(cacheKey);

        if (cachedData) {
          return new Response(JSON.stringify(cachedData), {
            status: 200,
            headers: { 'Content-Type': 'application/json' }
          });
        }

        // Return offline error
        return new Response(
          JSON.stringify({
            error: 'Offline - request queued for sync',
            syncId,
            queued: true
          }),
          { status: 503, headers: { 'Content-Type': 'application/json' } }
        );
      }
    };
  }

  getCacheKey(url, options) {
    const urlObj = new URL(url);
    return `${urlObj.pathname}-${options.method || 'GET'}`;
  }

  async getCachedData(cacheKey) {
    // This would need to be implemented based on your caching strategy
    // For now, return null
    return null;
  }

  startPeriodicSync() {
    // Sync every 30 seconds when online
    this.syncInterval = setInterval(() => {
      if (this.isOnline && !this.syncInProgress) {
        this.sync();
      }
    }, 30000);
  }

  stopPeriodicSync() {
    if (this.syncInterval) {
      clearInterval(this.syncInterval);
      this.syncInterval = null;
    }
  }

  // Get sync status
  async getSyncStatus() {
    const pendingCount = await offlineDB.getSyncQueueCount();
    return {
      isOnline: this.isOnline,
      pendingRequests: pendingCount,
      syncInProgress: this.syncInProgress
    };
  }

  // Manual sync trigger
  async manualSync() {
    if (!this.isOnline) {
      throw new Error('Cannot sync while offline');
    }

    await this.sync();
    return await this.getSyncStatus();
  }
}

// Global instance
const syncManager = new SyncManager();

// Intercept fetch requests after page load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    syncManager.interceptFetch();
  });
} else {
  syncManager.interceptFetch();
}

// Expose to global scope for manual sync triggers
window.syncManager = syncManager;
