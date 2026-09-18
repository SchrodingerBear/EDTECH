/**
 * Main Application Entry Point — Lavadora Offline-First System
 * 
 * This boots up the entire offline system:
 * 1. Opens IndexedDB
 * 2. Starts offline detector
 * 3. Initializes sync manager
 * 4. Seeds data from server (if online and first load)
 * 5. Exposes global `window.lavadora` for UI access
 */

import { Database } from './core/database.js';
import { OfflineDetector } from './core/offline-detector.js';
import { SyncManager } from './core/sync-manager.js';
import { OrderService } from './services/order.service.js';
import { CustomerService } from './services/customer.service.js';
import { InventoryService } from './services/inventory.service.js';

class LavadoraApp {
    constructor() {
        this.db = null;
        this.detector = null;
        this.sync = null;
        this.orders = null;
        this.customers = null;
        this.inventory = null;
        this.isReady = false;
    }

    async boot() {
        console.log('[Lavadora] Booting offline-first system...');

        try {
            // 1. Open the local IndexedDB
            this.db = new Database();
            await this.db.open();

            // 2. Start connectivity monitoring
            this.detector = new OfflineDetector();
            this.detector.initialize();

            // 3. Start the sync engine
            this.sync = new SyncManager(this.db, this.detector);
            this.sync.initialize();

            // 4. Initialize services
            this.orders = new OrderService(this.db, this.sync);
            this.customers = new CustomerService(this.db, this.sync);
            this.inventory = new InventoryService(this.db, this.sync);

            // 5. If online and no local data, seed from server
            if (this.detector.isOnline()) {
                await this._seedIfEmpty();
            }

            this.isReady = true;
            console.log('[Lavadora] ✅ System ready — running offline-first!');

            // Dispatch ready event so page scripts can start
            document.dispatchEvent(new CustomEvent('lavadora:ready'));

            // Show connectivity status in UI
            this._setupUINotifications();

            // Listen for Background Sync triggers from the Service Worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data && event.data.action === 'processSyncQueue') {
                        console.log('[Lavadora] Received background sync trigger from SW');
                        if (this.sync) {
                            this.sync.processQueue();
                        }
                    }
                });
            }

        } catch (error) {
            console.error('[Lavadora] ❌ Boot failed:', error);
        }
    }

    async _seedIfEmpty() {
        const orderCount = await this.db.count('orders');
        const customerCount = await this.db.count('customers');
        const serviceCount = await this.db.count('services');

        if (orderCount === 0 || customerCount === 0 || serviceCount === 0) {
            console.log('[Lavadora] Seeding initial data from server...');
            try {
                const res = await fetch('./api/data.php?action=initial_seed');
                if (!res.ok) return;
                const data = await res.json();

                if (data.orders) await this.orders.seedFromServer(data.orders);
                if (data.customers) await this.customers.seedFromServer(data.customers);
                if (data.inventory_items) await this.inventory.seedFromServer(data.inventory_items);
                if (data.inventory_movements) {
                    for (const mov of data.inventory_movements) {
                        await this.db.put('inventory_movements', mov);
                    }
                }
                if (data.services) {
                    for (const svc of data.services) {
                        await this.db.put('services', svc);
                    }
                }
                if (data.settings) {
                    for (const [key, value] of Object.entries(data.settings)) {
                        await this.db.setSetting(key, value);
                    }
                }
                console.log('[Lavadora] Initial seed complete');
            } catch (e) {
                console.warn('[Lavadora] Could not seed from server:', e.message);
            }
        }
    }

    _setupUINotifications() {
        // Create an offline banner if not already present
        let banner = document.getElementById('lavadora-offline-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'lavadora-offline-banner';
            banner.style.cssText = `
                display: none; position: fixed; bottom: 20px; left: 50%;
                transform: translateX(-50%); background: #dc3545; color: white;
                padding: 10px 24px; border-radius: 30px; font-weight: 600;
                font-size: 0.85rem; z-index: 9999; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                transition: all 0.3s ease;
            `;
            banner.innerHTML = `⚠️ Offline — Changes will sync when connection is restored`;
            document.body.appendChild(banner);
        }

        this.detector.onStatusChange((isOnline) => {
            banner.style.display = isOnline ? 'none' : 'block';
        });

        // Show immediately if offline
        if (!this.detector.isOnline()) {
            banner.style.display = 'block';
        }
    }
}

// Global singleton
window.lavadora = new LavadoraApp();

// Auto-boot when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.lavadora.boot());
} else {
    window.lavadora.boot();
}
