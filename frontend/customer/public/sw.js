
const CACHE_PREFIX = 'super-runner-customer-';
const CACHE_NAME = `${CACHE_PREFIX}v1`;

const APP_SHELL = [
    '/',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)),
    );

    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) =>
            Promise.all(
                cacheNames
                    .filter(
                        (cacheName) =>
                            cacheName.startsWith(CACHE_PREFIX) &&
                            cacheName !== CACHE_NAME,
                    )
                    .map((cacheName) => caches.delete(cacheName)),
            ),
        ),
    );

    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Never cache authenticated or API responses.
    if (
        url.pathname.startsWith('/web/') ||
        url.pathname.startsWith('/sanctum/') ||
        url.pathname.startsWith('/api/')
    ) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            if (request.mode === 'navigate') {
                try {
                    return await fetch(request);
                } catch {
                    return (await cache.match('/')) || Response.error();
                }
            }

            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);

            if (response.ok && response.type === 'basic') {
                await cache.put(request, response.clone());
            }

            return response;
        }),
    );
});
