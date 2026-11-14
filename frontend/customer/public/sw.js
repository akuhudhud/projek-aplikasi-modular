
const CACHE_PREFIX = 'super-runner-customer-';
const CACHE_NAME = `${CACHE_PREFIX}v4`;

const APP_SHELL = [
    '/',
    '/manifest.webmanifest',
    '/manifest.json',
    '/icons/icon.svg',
];

function isApiPath(pathname) {
    return ['/web', '/sanctum', '/api'].some(
        (prefix) =>
            pathname === prefix ||
            pathname.startsWith(`${prefix}/`),
    );
}

function isStaticAsset(pathname) {
    return (
        pathname.startsWith('/assets/') ||
        pathname.startsWith('/icons/')
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

async function handleNavigationRequest(request, cache) {
    try {
        const response = await fetch(request);

        if (response.ok && response.type === 'basic') {
            await cache.put('/', response.clone());
        }

        return response;
    } catch {
        const requestedPage = await cache.match(request);

        if (requestedPage) {
            return requestedPage;
        }

        const appShell = await cache.match('/');

        if (appShell) {
            return appShell;
        }

        return new Response(
            'Anda sedang di luar talian. Sambungan internet diperlukan untuk memuatkan aplikasi.',
            {
                status: 503,
                statusText: 'Service Unavailable',
                headers: {
                    'Content-Type': 'text/plain; charset=utf-8',
                    'Cache-Control': 'no-store',
                },
            },
        );
    }
}

async function handleStaticAsset(request, cache) {
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok && response.type === 'basic') {
        await cache.put(request, response.clone());
    }

    return response;
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
        isApiPath(url.pathname)
    ) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) =>
                handleNavigationRequest(request, cache),
            ),
        );

        return;
    }

    if (!isStaticAsset(url.pathname)) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then((cache) =>
            handleStaticAsset(request, cache),
        ),
    );
});
