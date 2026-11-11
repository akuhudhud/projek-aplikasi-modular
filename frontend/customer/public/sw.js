
const CACHE_PREFIX = 'super-runner-customer-';
const CACHE_NAME = `${CACHE_PREFIX}v3`;

const APP_SHELL = [
    '/',
    '/manifest.webmanifest',
    '/manifest.json',
];

function isApiPath(pathname) {
    return ['/web', '/sanctum', '/api'].some(
        (prefix) =>
            pathname === prefix ||
            pathname.startsWith(`${prefix}/`),
    );
}

async function precacheAppShell(cache) {
    await cache.addAll(APP_SHELL);

    const response = await fetch('/manifest.json', {
        cache: 'no-store',
    });

    if (!response.ok) {
        throw new Error('Unable to load the Vite build manifest.');
    }

    const manifest = await response.json();
    const assets = new Set();

    Object.values(manifest).forEach((entry) => {
        if (!entry || typeof entry !== 'object') {
            return;
        }

        if (typeof entry.file === 'string') {
            assets.add(`/${entry.file.replace(/^\/+/, '')}`);
        }

        if (Array.isArray(entry.css)) {
            entry.css.forEach((file) => {
                if (typeof file === 'string') {
                    assets.add(`/${file.replace(/^\/+/, '')}`);
                }
            });
        }

        if (Array.isArray(entry.assets)) {
            entry.assets.forEach((file) => {
                if (typeof file === 'string') {
                    assets.add(`/${file.replace(/^\/+/, '')}`);
                }
            });
        }
    });

    if (assets.size > 0) {
        await cache.addAll([...assets]);
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(precacheAppShell),
    );

    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        Promise.all([
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
            self.clients.claim(),
        ]),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (
        url.origin !== self.location.origin ||
        isApiPath(url.pathname) ||
        !url.pathname.startsWith('/assets/')
    ) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
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
