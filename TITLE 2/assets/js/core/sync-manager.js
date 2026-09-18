/**
 * Sync Manager for Lavadora Offline System
 * 
 * This is the CORE ENGINE of the offline-first system.
 * It manages the sync_queue table in IndexedDB and pushes
 * locally created records to the PHP backend when online.
 * 
 * Flow:
 *   [User creates order offline]
 *      -> Order saved to IndexedDB (synced: false)
 *      -> SyncQueue entry created (entity_type: 'order', action: 'create')
 *   [Connection restored]
 *      -> SyncManager.processQueue() triggered
 *      -> Reads all pending sync_queue entries
 *      -> POSTs each to api/sync.php
 *      -> Marks records as synced on success
 */

export class SyncManager {
    constructor(db, offlineDetector) {
        this.db = db;
        this.offlineDetector = offlineDetector;
        this._syncUrl = './api/sync.php';
        this._isSyncing = false;
    }

    initialize() {
        // Listen for online status changes to auto-sync
        this.offlineDetector.onStatusChange(async (isOnline) => {
            if (isOnline) {
                console.log('[SyncManager] Back online — processing queue...');
                await this.processQueue();
            }
        });

        // Attempt an initial sync on startup if online
        if (this.offlineDetector.isOnline()) {
            this.processQueue();
        }

        console.log('[SyncManager] Initialized');
    }

    /**
     * Add an operation to the sync queue.
     * Called by services when offline changes are made.
     * 
     * @param {string} entityType - 'order', 'customer', etc.
     * @param {string} action - 'create', 'update', 'delete'
     * @param {object} payload - The data to sync
     * @param {number} priority - Lower = higher priority (default: 10)
     */
    async addToQueue(entityType, action, payload, priority = 10) {
        const queueEntry = {
            entity_type: entityType,
            action: action,
            payload: JSON.stringify(payload),
            status: 'pending',
            priority: priority,
            attempts: 0,
            created_at: new Date().toISOString()
        };
        await this.db.add('sync_queue', queueEntry);
        console.log(`[SyncManager] Queued: ${action} ${entityType}`);
        
        // Register for Background Sync API if supported
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            try {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('lavadora-sync');
                console.log('[SyncManager] Registered background sync');
            } catch (err) {
                console.warn('[SyncManager] Background sync registration failed:', err);
            }
        }
    }

    /**
     * Process all pending items in the sync queue.
     * Sends each to api/sync.php in priority order.
     */
    async processQueue() {
        if (this._isSyncing || !this.offlineDetector.isOnline()) return;
        this._isSyncing = true;

        try {
            const pending = await this.db.queryByIndex('sync_queue', 'status', 'pending');
            // Sort by priority (lower number = higher priority)
            pending.sort((a, b) => a.priority - b.priority);

            console.log(`[SyncManager] Processing ${pending.length} queued operations...`);

            for (const entry of pending) {
                await this._syncEntry(entry);
            }

            console.log('[SyncManager] Queue processed successfully');
        } catch (error) {
            console.error('[SyncManager] Queue processing failed:', error);
        } finally {
            this._isSyncing = false;
        }
    }

    async _syncEntry(entry) {
        try {
            const response = await fetch(this._syncUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    entity_type: entry.entity_type,
                    action: entry.action,
                    payload: JSON.parse(entry.payload)
                })
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'Sync failed');

            // If the PHP server assigned a new ID (e.g., server-side int ID), update local record
            if (result.server_id && result.local_id) {
                await this._updateLocalId(entry.entity_type, result.local_id, result.server_id);
            }

            // Mark as synced in the local record
            await this._markLocalAsSynced(entry.entity_type, JSON.parse(entry.payload).id);

            // Remove from sync queue
            await this.db.delete('sync_queue', entry.id);
            console.log(`[SyncManager] ✅ Synced: ${entry.action} ${entry.entity_type}`);

        } catch (error) {
            console.error(`[SyncManager] ❌ Failed to sync ${entry.entity_type}:`, error);

            // Increment attempt count, mark as failed after 3 tries
            const updated = {
                ...entry,
                attempts: (entry.attempts || 0) + 1,
                status: (entry.attempts || 0) >= 2 ? 'failed' : 'pending',
                last_error: error.message
            };
            await this.db.put('sync_queue', updated);
        }
    }

    async _markLocalAsSynced(entityType, localId) {
        const storeMap = {
            'order': 'orders',
            'customer': 'customers',
            'order_item': 'order_items'
        };
        const storeName = storeMap[entityType];
        if (!storeName) return;

        const record = await this.db.get(storeName, localId);
        if (record) {
            await this.db.put(storeName, { ...record, synced: true, sync_timestamp: new Date().toISOString() });
        }
    }

    async _updateLocalId(entityType, localId, serverId) {
        // Future: handle cases where server returns a different (int) ID
        // For now, UUIDs ensure local = server IDs
        console.log(`[SyncManager] ID mapping: local=${localId} -> server=${serverId}`);
    }

    /** Get sync status counts */
    async getStatus() {
        const pending = await this.db.queryByIndex('sync_queue', 'status', 'pending');
        const failed = await this.db.queryByIndex('sync_queue', 'status', 'failed');
        return { pending: pending.length, failed: failed.length };
    }
}
