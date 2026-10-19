/* DFQ Exchange service worker — safe, minimal offline support. */
const CACHE_NAME = 'dfq-shell-v5';
const OFFLINE_URL = './offline.html';

const SHELL_ASSETS = [
    OFFLINE_URL,
    './splash.html',
    './manifest.json',
    './static/assets/img/app-icon-192.png',
    './static/assets/img/app-icon-512.png',
    './static/assets/img/apple-touch-icon.png'
];

const CACHEABLE_DESTINATIONS = ['style', 'script', 'image', 'font'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only handle same-origin GET requests. Never touch POSTs, API calls,
    // authenticated PHP pages, or anything that could contain private data.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Page navigations: network first, then the cached shell page (e.g. the
    // app splash), then the offline page. Authenticated pages are never cached.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL)))
        );
        return;
    }

    // Static assets only: cache first, refresh in the background.
    if (CACHEABLE_DESTINATIONS.includes(request.destination)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const network = fetch(request).then((response) => {
                    if (response && response.status === 200 && response.type === 'basic') {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    }
                    return response;
                }).catch(() => cached);

                return cached || network;
            })
        );
    }
});
