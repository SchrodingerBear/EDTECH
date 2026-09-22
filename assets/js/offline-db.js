/**
 * IndexedDB Wrapper for Innovatech PH Offline Storage
 * Handles local data storage and sync queue management
 */

class OfflineDB {
  constructor(dbName = 'InnovatechPH', version = 1) {
    this.dbName = dbName;
    this.version = version;
    this.db = null;
    this._ready = this.open().catch(err => {
      console.error('[OfflineDB] Failed to open database:', err);
    });
  }

  // Open database connection
  async open() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.version);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve(this.db);
      };

      request.onupgradeneeded = (event) => {
        const db = event.target.result;

        // Sync queue for offline changes
        if (!db.objectStoreNames.contains('syncQueue')) {
          const syncStore = db.createObjectStore('syncQueue', { keyPath: 'id' });
          syncStore.createIndex('timestamp', 'timestamp', { unique: false });
          syncStore.createIndex('endpoint', 'endpoint', { unique: false });
        }

        // Cached data stores
        if (!db.objectStoreNames.contains('buildings')) {
          const buildingStore = db.createObjectStore('buildings', { keyPath: 'id' });
          buildingStore.createIndex('institution_id', 'institution_id', { unique: false });
        }

        if (!db.objectStoreNames.contains('locations')) {
          const locationStore = db.createObjectStore('locations', { keyPath: 'id' });
          locationStore.createIndex('building_id', 'building_id', { unique: false });
          locationStore.createIndex('institution_id', 'institution_id', { unique: false });
        }

        if (!db.objectStoreNames.contains('tours')) {
          const tourStore = db.createObjectStore('tours', { keyPath: 'id' });
          tourStore.createIndex('institution_id', 'institution_id', { unique: false });
        }

        if (!db.objectStoreNames.contains('panoramas')) {
          const panoramaStore = db.createObjectStore('panoramas', { keyPath: 'id' });
          panoramaStore.createIndex('institution_id', 'institution_id', { unique: false });
        }

        if (!db.objectStoreNames.contains('institutions')) {
          db.createObjectStore('institutions', { keyPath: 'id' });
        }

        // User session data
        if (!db.objectStoreNames.contains('session')) {
          db.createObjectStore('session', { keyPath: 'key' });
        }

        console.log('[OfflineDB] Database upgraded successfully');
      };
    });
  }

  // Generic add operation
  async add(storeName, data) {
    await this._ready;
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([storeName], 'readwrite');
      const store = transaction.objectStore(storeName);
      const request = store.add(data);

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  // Generic put operation (upsert)
  async put(storeName, data) {
    await this._ready;
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([storeName], 'readwrite');
      const store = transaction.objectStore(storeName);
      const request = store.put(data);

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  // Generic get operation
  async get(storeName, key) {
    await this._ready;
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([storeName], 'readonly');
      const store = transaction.objectStore(storeName);
      const request = store.get(key);

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  // Generic getAll operation
  async getAll(storeName) {
    await this._ready;
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([storeName], 'readonly');
      const store = transaction.objectStore(storeName);
      const request = store.getAll();

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  // Generic delete operation
  async delete(storeName, key) {
    await this._ready;
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([storeName], 'readwrite');
      const store = transaction.objectStore(storeName);
      const request = store.delete(key);

      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }

  // Generic clear operation
  async clear(storeName) {
    await this._ready;
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([storeName], 'readwrite');
      const store = transaction.objectStore(storeName);
      const request = store.clear();

      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }

  // Query by index
  async getByIndex(storeName, indexName, value) {
    await this._ready;
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([storeName], 'readonly');
      const store = transaction.objectStore(storeName);
      const index = store.index(indexName);
      const request = index.getAll(value);

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  // Queue a sync request
  async queueSyncRequest(endpoint, method, data, id = null) {
    const syncItem = {
      id: id || `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      endpoint,
      method,
      data,
      timestamp: Date.now(),
      status: 'pending',
      retryCount: 0
    };

    await this.add('syncQueue', syncItem);
    console.log('[OfflineDB] Sync request queued:', syncItem);
    return syncItem.id;
  }

  // Get pending sync requests
  async getPendingSyncRequests() {
    await this._ready;
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction(['syncQueue'], 'readonly');
      const store = transaction.objectStore('syncQueue');
      const index = store.index('status');
      const request = index.getAll('pending');

      request.onsuccess = () => {
        const results = request.result;
        // Sort by timestamp (oldest first)
        results.sort((a, b) => a.timestamp - b.timestamp);
        resolve(results);
      };
      request.onerror = () => reject(request.error);
    });
  }

  // Update sync request status
  async updateSyncRequestStatus(id, status, error = null) {
    const item = await this.get('syncQueue', id);
    if (item) {
      item.status = status;
      item.error = error;
      if (status === 'failed') {
        item.retryCount = (item.retryCount || 0) + 1;
      }
      await this.put('syncQueue', item);
    }
  }

  // Delete sync request
  async deleteSyncRequest(id) {
    await this.delete('syncQueue', id);
  }

  // Clear failed sync requests (too many retries)
  async clearFailedSyncRequests(maxRetries = 5) {
    const allRequests = await this.getAll('syncQueue');
    const toDelete = allRequests.filter(req =>
      req.status === 'failed' && req.retryCount >= maxRetries
    );

    for (const req of toDelete) {
      await this.deleteSyncRequest(req.id);
    }

    return toDelete.length;
  }

  // Cache buildings
  async cacheBuildings(buildings) {
    await this.clear('buildings');
    for (const building of buildings) {
      await this.put('buildings', building);
    }
    console.log(`[OfflineDB] Cached ${buildings.length} buildings`);
  }

  // Cache locations
  async cacheLocations(locations) {
    await this.clear('locations');
    for (const location of locations) {
      await this.put('locations', location);
    }
    console.log(`[OfflineDB] Cached ${locations.length} locations`);
  }

  // Cache tours
  async cacheTours(tours) {
    await this.clear('tours');
    for (const tour of tours) {
      await this.put('tours', tour);
    }
    console.log(`[OfflineDB] Cached ${tours.length} tours`);
  }

  // Cache panoramas
  async cachePanoramas(panoramas) {
    await this.clear('panoramas');
    for (const panorama of panoramas) {
      await this.put('panoramas', panorama);
    }
    console.log(`[OfflineDB] Cached ${panoramas.length} panoramas`);
  }

  // Cache institutions
  async cacheInstitutions(institutions) {
    await this.clear('institutions');
    for (const institution of institutions) {
      await this.put('institutions', institution);
    }
    console.log(`[OfflineDB] Cached ${institutions.length} institutions`);
  }

  // Save session data
  async saveSession(key, value) {
    await this.put('session', { key, value, timestamp: Date.now() });
  }

  // Get session data
  async getSession(key) {
    const data = await this.get('session', key);
    return data ? data.value : null;
  }

  // Clear session
  async clearSession() {
    await this.clear('session');
  }

  // Get sync queue count
  async getSyncQueueCount() {
    const pending = await this.getPendingSyncRequests();
    return pending.length;
  }

  // Close database
  close() {
    if (this.db) {
      this.db.close();
      this.db = null;
    }
  }
}

// Global instance
const offlineDB = new OfflineDB();
