const CACHE_NAME = 'lavadora-cache-v1';
const STATIC_ASSETS = [
    './',
    './manifest.json',
    './assets/images/icon-192x192.png',
    './assets/images/icon-512x512.png',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    // JS Core
    './assets/js/app.js',
    './assets/js/core/database.js',
    './assets/js/core/uuid-generator.js',
    './assets/js/core/offline-detector.js',
    './assets/js/core/sync-manager.js',
    // JS Models
    './assets/js/models/order.model.js',
    './assets/js/models/customer.model.js',
    // JS Services
    './assets/js/services/order.service.js',
    './assets/js/services/customer.service.js',
    './assets/js/services/inventory.service.js',
    // UI JS
    './assets/js/ui/orders.offline.js',
    './assets/js/ui/customers.offline.js',
    './assets/js/ui/dashboard.offline.js',
    './assets/js/ui/inventory.offline.js',
    // Admin specific
    './admin/assets/css/dashboard.css',
    './admin/assets/js/dashboard.js',
    './admin/assets/img/logo.svg'
];

self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .catch(err => console.error('[SW] Error caching static assets:', err))
    );
});

self.addEventListener('activate', event => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    // Only intercept GET requests
    if (event.request.method !== 'GET') return;
    
    // Don't intercept API calls (we want them to fail naturally if offline so our sync logic kicks in)
    if (event.request.url.includes('/api/')) return;

    // Network First strategy (fall back to cache)
    event.respondWith(
        fetch(event.request)
            .then(response => {
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }
                let responseToCache = response.clone();
                caches.open(CACHE_NAME)
                    .then(cache => {
                        cache.put(event.request, responseToCache);
                    });
                return response;
            })
            .catch(() => {
                // If fetch fails (e.g. offline), try resolving from cache
                return caches.match(event.request).then(response => {
                    if (response) {
                        return response;
                    }
                });
            })
    );
});

// Background Sync API
self.addEventListener('sync', event => {
    if (event.tag === 'lavadora-sync') {
        console.log('[SW] Background sync triggered');
        // Notify all open clients (tabs) to trigger their SyncManager
        event.waitUntil(
            self.clients.matchAll().then(clients => {
                clients.forEach(client => {
                    client.postMessage({ action: 'processSyncQueue' });
                });
            })
        );
    }
});
