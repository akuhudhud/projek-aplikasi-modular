
const CACHE_PREFIX = 'super-runner-customer-';
const CACHE_NAME = `${CACHE_PREFIX}v2`;

const APP_SHELL = [
    '/',
    '/manifest.webmanifest',
];

function isApiPath(pathname) {
    return ['/web', '/sanctum', '/api'].some(
        (prefix) =>
            pathname === prefix ||
            pathname.startsWith(`${prefix}/`),
    );
}

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

    if (
        url.origin !== self.location.origin ||
        isApiPath(url.pathname)
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
