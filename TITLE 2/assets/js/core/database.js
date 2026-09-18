/**
 * IndexedDB Wrapper for Lavadora Offline System
 * Provides a clean Promise-based API for all local database operations.
 * This is the OFFLINE DATABASE — it stores data locally in the browser.
 */

export class Database {
    constructor() {
        this.db = null;
        this.dbName = 'LavadoraOfflineDB';
        this.dbVersion = 2; // Incremented for inventory stores
    }

    async open() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                console.log('[DB] LavadoraOfflineDB opened successfully');
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                console.log('[DB] Creating database stores...');
                this._createStores(db);
            };
        });
    }

    _createStores(db) {
        // === ORDERS STORE ===
        if (!db.objectStoreNames.contains('orders')) {
            const orderStore = db.createObjectStore('orders', { keyPath: 'id' });
            orderStore.createIndex('customer_id', 'customer_id', { unique: false });
            orderStore.createIndex('status', 'status', { unique: false });
            orderStore.createIndex('synced', 'synced', { unique: false });
            orderStore.createIndex('created_at', 'created_at', { unique: false });
            orderStore.createIndex('order_no', 'order_no', { unique: false });
        }

        // === ORDER ITEMS STORE ===
        if (!db.objectStoreNames.contains('order_items')) {
            const itemStore = db.createObjectStore('order_items', { keyPath: 'id' });
            itemStore.createIndex('order_id', 'order_id', { unique: false });
            itemStore.createIndex('synced', 'synced', { unique: false });
        }

        // === CUSTOMERS STORE ===
        if (!db.objectStoreNames.contains('customers')) {
            const customerStore = db.createObjectStore('customers', { keyPath: 'id' });
            customerStore.createIndex('phone', 'phone', { unique: false });
            customerStore.createIndex('synced', 'synced', { unique: false });
        }

        // === SERVICES STORE (read-only, seeded from PHP on first load) ===
        if (!db.objectStoreNames.contains('services')) {
            db.createObjectStore('services', { keyPath: 'id' });
        }

        // === INVENTORY ITEMS STORE ===
        if (!db.objectStoreNames.contains('inventory_items')) {
            const invStore = db.createObjectStore('inventory_items', { keyPath: 'id' });
            invStore.createIndex('synced', 'synced', { unique: false });
        }

        // === INVENTORY MOVEMENTS STORE ===
        if (!db.objectStoreNames.contains('inventory_movements')) {
            const invMovStore = db.createObjectStore('inventory_movements', { keyPath: 'id' });
            invMovStore.createIndex('inventory_item_id', 'inventory_item_id', { unique: false });
            invMovStore.createIndex('synced', 'synced', { unique: false });
        }

        // === SETTINGS STORE ===
        if (!db.objectStoreNames.contains('settings')) {
            db.createObjectStore('settings', { keyPath: 'key' });
        }

        // === SYNC QUEUE STORE (pending operations waiting to be pushed to server) ===
        if (!db.objectStoreNames.contains('sync_queue')) {
            const syncStore = db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
            syncStore.createIndex('status', 'status', { unique: false });
            syncStore.createIndex('entity_type', 'entity_type', { unique: false });
            syncStore.createIndex('priority', 'priority', { unique: false });
        }

        console.log('[DB] All stores created successfully');
    }

    // ---- Generic CRUD ----

    async getAll(storeName) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const request = tx.objectStore(storeName).getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    async get(storeName, id) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const request = tx.objectStore(storeName).get(id);
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    async add(storeName, data) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const request = tx.objectStore(storeName).add(data);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async put(storeName, data) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const request = tx.objectStore(storeName).put(data);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async delete(storeName, id) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const request = tx.objectStore(storeName).delete(id);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async queryByIndex(storeName, indexName, value) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const index = tx.objectStore(storeName).index(indexName);
            const request = index.getAll(value);
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    async count(storeName) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const request = tx.objectStore(storeName).count();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async clearStore(storeName) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const request = tx.objectStore(storeName).clear();
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getSetting(key) {
        const record = await this.get('settings', key);
        return record ? record.value : null;
    }

    async setSetting(key, value) {
        return this.put('settings', { key, value });
    }
}
