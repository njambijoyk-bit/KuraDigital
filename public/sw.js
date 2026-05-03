const CACHE_NAME = 'kuradigital-v1';
const API_CACHE = 'kuradigital-api-v1';
const OFFLINE_QUEUE_TAG = 'field-report-sync';

const STATIC_ASSETS = [
    '/',
    '/manifest.json',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
];

// Install: cache shell assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME && key !== API_CACHE)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// Fetch: network-first for API, cache-first for static assets
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests (POST/PUT/DELETE go straight to network)
    if (request.method !== 'GET') {
        return;
    }

    // API requests: network-first with cache fallback
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(API_CACHE).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => caches.match(request))
        );
        return;
    }

    // Static assets and pages: cache-first with network fallback
    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname === '/manifest.json'
    ) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // HTML navigation requests: network-first, serve cached app shell as fallback
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/'))
        );
        return;
    }
});

// Background sync for offline report queue
self.addEventListener('sync', (event) => {
    if (event.tag === OFFLINE_QUEUE_TAG) {
        event.waitUntil(syncOfflineReports());
    }
});

// Periodic sync (if supported)
self.addEventListener('periodicsync', (event) => {
    if (event.tag === OFFLINE_QUEUE_TAG) {
        event.waitUntil(syncOfflineReports());
    }
});

// Message handler for manual sync trigger
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SYNC_REPORTS') {
        syncOfflineReports().then((results) => {
            event.source?.postMessage({ type: 'SYNC_COMPLETE', results });
        });
    }

    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

async function syncOfflineReports() {
    const db = await openDB();
    const tx = db.transaction('pendingReports', 'readonly');
    const store = tx.objectStore('pendingReports');

    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = async () => {
            const reports = request.result;
            if (!reports || reports.length === 0) {
                resolve([]);
                return;
            }

            const results = [];

            for (const report of reports) {
                try {
                    const formData = new FormData();
                    formData.append('type', report.type);
                    formData.append('client_id', report.clientId);
                    if (report.title) formData.append('title', report.title);
                    if (report.body) formData.append('body', report.body);
                    if (report.latitude) formData.append('latitude', report.latitude);
                    if (report.longitude) formData.append('longitude', report.longitude);
                    if (report.ward) formData.append('ward', report.ward);
                    if (report.constituency) formData.append('constituency', report.constituency);
                    if (report.county) formData.append('county', report.county);
                    if (report.capturedAt) formData.append('captured_at', report.capturedAt);
                    if (report.tags) formData.append('tags', JSON.stringify(report.tags));

                    // Attach files from stored blobs
                    if (report.files && report.files.length > 0) {
                        for (const file of report.files) {
                            formData.append('files[]', file.blob, file.name);
                        }
                    }

                    const response = await fetch(
                        `/api/v1/campaigns/${report.campaignId}/field-reports`,
                        {
                            method: 'POST',
                            headers: {
                                Authorization: `Bearer ${report.token}`,
                                Accept: 'application/json',
                            },
                            body: formData,
                        }
                    );

                    if (response.ok) {
                        // Remove from IndexedDB on success
                        await deleteFromDB(report.id);
                        results.push({ clientId: report.clientId, status: 'synced' });
                    } else {
                        results.push({ clientId: report.clientId, status: 'failed', code: response.status });
                    }
                } catch (err) {
                    results.push({ clientId: report.clientId, status: 'failed', error: err.message });
                }
            }

            // Notify all clients of sync progress
            const clients = await self.clients.matchAll();
            clients.forEach((client) => {
                client.postMessage({ type: 'SYNC_PROGRESS', results });
            });

            resolve(results);
        };
        request.onerror = () => reject(request.error);
    });
}

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('KuraDigitalOffline', 1);
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('pendingReports')) {
                const store = db.createObjectStore('pendingReports', { keyPath: 'id', autoIncrement: true });
                store.createIndex('clientId', 'clientId', { unique: true });
                store.createIndex('campaignId', 'campaignId', { unique: false });
                store.createIndex('createdAt', 'createdAt', { unique: false });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function deleteFromDB(id) {
    return new Promise(async (resolve, reject) => {
        const db = await openDB();
        const tx = db.transaction('pendingReports', 'readwrite');
        const request = tx.objectStore('pendingReports').delete(id);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}
