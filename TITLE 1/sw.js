// Service Worker for Innovatech PH PWA
// Caches assets for offline use and handles sync

const CACHE_NAME = 'innovatech-ph-v1';
const OFFLINE_CACHE = 'innovatech-offline-v1';

// Assets to cache on install
const STATIC_ASSETS = [
  '/',
  '/admin/index.php',
  '/admin/institution/dashboard',
  '/admin/institution/buildings',
  '/admin/institution/locations',
  '/admin/institution/tours',
  '/admin/institution/files',
  '/assets/css/landing.css',
  '/admin/assets/css/dashboard.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/simple-datatables.min.js',
  '/public/icon.svg',
  '/manifest.json'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
  console.log('[SW] Installing service worker...');
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[SW] Caching static assets');
      return cache.addAll(STATIC_ASSETS);
    })
  );
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating service worker...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME && cacheName !== OFFLINE_CACHE) {
            console.log('[SW] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }

  // Skip API calls - handle them separately for sync
  if (url.pathname.startsWith('/api/')) {
    return handleAPIRequest(event);
  }

  // Skip Chrome extensions and other protocols
  if (!url.protocol.startsWith('http')) {
    return;
  }

  // Cache-first strategy for static assets
  if (isStaticAsset(request)) {
    event.respondWith(cacheFirst(request));
  } else {
    // Network-first strategy for dynamic content
    event.respondWith(networkFirst(request));
  }
});

// Check if request is for a static asset
function isStaticAsset(request) {
  const url = new URL(request.url);
  return (
    url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|webp|woff2|woff|ttf|eot)$/i) ||
    url.hostname.includes('cdn.jsdelivr.net') ||
    url.hostname.includes('cdnjs.cloudflare.com')
  );
}

// Cache-first strategy
async function cacheFirst(request) {
  try {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }

    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    console.error('[SW] Cache-first failed:', error);
    // Return offline fallback if available
    return caches.match('/offline.html');
  }
}

// Network-first strategy
async function networkFirst(request) {
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    console.error('[SW] Network-first failed, trying cache:', error);
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    // Return offline fallback
    return caches.match('/offline.html');
  }
}

// Handle API requests for sync
async function handleAPIRequest(event) {
  const { request } = event;

  try {
    // Try network first
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      return networkResponse;
    }
    throw new Error('Network request failed');
  } catch (error) {
    console.log('[SW] API request failed, queuing for sync:', request.url);

    // Store request for later sync
    const requestData = {
      url: request.url,
      method: request.method,
      headers: Object.fromEntries(request.headers),
      body: await request.clone().text(),
      timestamp: Date.now()
    };

    // Store in IndexedDB via message to client
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
      client.postMessage({
        type: 'SYNC_REQUEST',
        data: requestData
      });
    });

    // Return cached response if available
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }

    // Return error response
    return new Response(
      JSON.stringify({ error: 'Offline - request queued for sync' }),
      { status: 503, headers: { 'Content-Type': 'application/json' } }
    );
  }
}

// Handle sync events (for background sync)
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync triggered:', event.tag);
  if (event.tag === 'sync-data') {
    event.waitUntil(syncData());
  }
});

// Sync queued data
async function syncData() {
  try {
    // This would read from IndexedDB and send to server
    // Implementation depends on your sync queue structure
    console.log('[SW] Syncing queued data...');
    // TODO: Implement actual sync logic
  } catch (error) {
    console.error('[SW] Sync failed:', error);
  }
}

// Handle push notifications (optional)
self.addEventListener('push', (event) => {
  const options = {
    body: event.data ? event.data.text() : 'New notification',
    icon: '/public/icon-192x192.png',
    badge: '/public/icon-192x192.png',
    vibrate: [200, 100, 200]
  };

  event.waitUntil(
    self.registration.showNotification('Innovatech PH', options)
  );
});

// Handle notification clicks
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow('/admin/institution/dashboard')
  );
});
