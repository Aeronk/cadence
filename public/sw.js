// Cadence service worker — versioned cache.
// Bump CACHE_VERSION whenever shell assets change so old SWs evict cleanly.
const CACHE_VERSION = 'v1';
const STATIC_CACHE = `cadence-static-${CACHE_VERSION}`;
const RUNTIME_CACHE = `cadence-runtime-${CACHE_VERSION}`;

const PRECACHE_URLS = [
    '/offline',
    '/favicon.svg',
    '/favicon.ico',
    '/apple-touch-icon.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) =>
                Promise.all(
                    PRECACHE_URLS.map((url) =>
                        cache.add(url).catch(() => {
                            /* missing assets are non-fatal */
                        }),
                    ),
                ),
            )
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((k) => !k.endsWith(CACHE_VERSION))
                        .map((k) => caches.delete(k)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only handle same-origin GETs. Skip POST/PATCH/DELETE and external hosts.
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Never intercept Inertia XHR or API-style endpoints — must stay fresh.
    if (request.headers.get('X-Inertia') || url.pathname.startsWith('/api')) return;

    // Navigations: network-first with offline fallback.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(RUNTIME_CACHE).then((cache) => cache.put(request, copy));
                    return response;
                })
                .catch(() =>
                    caches.match(request).then((cached) => cached || caches.match('/offline')),
                ),
        );
        return;
    }

    // Static assets: stale-while-revalidate.
    if (
        url.pathname.startsWith('/build/') ||
        /\.(?:js|css|png|jpg|jpeg|svg|webp|ico|woff2?)$/.test(url.pathname)
    ) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const networkFetch = fetch(request)
                    .then((response) => {
                        const copy = response.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                        return response;
                    })
                    .catch(() => cached);
                return cached || networkFetch;
            }),
        );
    }
});
