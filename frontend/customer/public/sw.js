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

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                const cached = await caches.match('/');
                return cached || Response.error();
            }),
        );

        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) {
                return cached;
            }

            return fetch(request).then((response) => {
                if (response.ok && response.type === 'basic') {
                    const responseClone = response.clone();

                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseClone);
                    });
                }

                return response;
            });
        }),
    );
});
