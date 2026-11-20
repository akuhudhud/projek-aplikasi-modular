
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const swPath = fileURLToPath(
    new URL('../public/sw.js', import.meta.url),
);
const swSource = readFileSync(swPath, 'utf8');

const cacheStores = new Map();

const calls = {
    fetch: [],
    skipWaiting: 0,
    claim: 0,
    offline: false,
};

function basicResponse(body, options = {}) {
    const response = new Response(body, options);

    Object.defineProperty(response, 'type', {
        value: 'basic',
    });

    return response;
}

const sandbox = {
    URL,
    Response,

    caches: {
        async open(name) {
            if (!cacheStores.has(name)) {
                cacheStores.set(name, new Map());
            }

            const entries = cacheStores.get(name);

            return {
                async addAll(urls) {
                    for (const url of urls) {
                        const response = await sandbox.fetch(url);

                        if (!response.ok) {
                            throw new Error(`Precache failed: ${url}`);
                        }

                        entries.set(
                            new URL(url, 'https://runner.test').href,
                            response.clone(),
                        );
                    }
                },

                async put(request, response) {
                    const key = typeof request === 'string'
                        ? new URL(request, 'https://runner.test').href
                        : request.url;

                    entries.set(key, response.clone());
                },

                async match(request) {
                    const key = typeof request === 'string'
                        ? new URL(request, 'https://runner.test').href
                        : request.url;

                    return entries.get(key)?.clone();
                },
            };
        },

        async keys() {
            return [...cacheStores.keys()];
        },

        async delete(name) {
            return cacheStores.delete(name);
        },
    },

    fetch: async (input) => {
        calls.fetch.push(input);

        const url = typeof input === 'string'
            ? input
            : input.url;

        if (calls.offline && url !== '/manifest.json') {
            throw new Error('Network unavailable');
        }

        if (url === '/manifest.json') {
            return basicResponse(
                JSON.stringify({
                    main: {
                        file: 'assets/main.js',
                        css: ['assets/main.css'],
                        assets: ['assets/font.woff2'],
                    },
                }),
                {
                    headers: {
                        'Content-Type': 'application/json',
                    },
                },
            );
        }

        return basicResponse(`network:${url}`);
    },

    self: {
        location: {
            origin: 'https://runner.test',
        },

        handlers: {},

        addEventListener(type, handler) {
            this.handlers[type] = handler;
        },

        skipWaiting() {
            calls.skipWaiting++;
        },

        clients: {
            claim() {
                calls.claim++;
            },
        },
    },
};

vm.createContext(sandbox);
vm.runInContext(swSource, sandbox);

const handlers = sandbox.self.handlers;

async function lifecycle(type) {
    let promise;

    handlers[type]({
        waitUntil(value) {
            promise = value;
        },
    });

    await promise;
}

async function fetchEvent(url, options = {}) {
    let promise;

    handlers.fetch({
        request: {
            url,
            method: options.method ?? 'GET',
            mode: options.mode ?? 'no-cors',
        },

        respondWith(value) {
            promise = value;
        },
    });

    return promise;
}

// 1. Installation and App Shell precache.
await lifecycle('install');

assert.equal(calls.skipWaiting, 1);

const currentCache = cacheStores.get(
    'super-runner-runner-v4',
);

assert.ok(currentCache);

const expectedFiles = [
    '/',
    '/manifest.webmanifest',
    '/manifest.json',
    '/icons/icon.svg',
    '/icons/icon-192-1.png',
    '/icons/icon-512-1.png',
    '/icons/apple-touch-icon-1.png',
    '/assets/main.js',
    '/assets/main.css',
    '/assets/font.woff2',
];

for (const path of expectedFiles) {
    assert.ok(
        currentCache.has(
            new URL(path, 'https://runner.test').href,
        ),
        `Missing precache: ${path}`,
    );
}

// 2. Activation and old-cache cleanup.
cacheStores.set('super-runner-runner-v3', new Map());
cacheStores.set('unrelated-cache', new Map());

await lifecycle('activate');

assert.ok(!cacheStores.has('super-runner-runner-v3'));
assert.ok(cacheStores.has('unrelated-cache'));
assert.equal(calls.claim, 1);

// 3. API, external origin and non-GET exclusion.
const fetchCount = calls.fetch.length;

const bypassUrls = [
    'https://runner.test/api',
    'https://runner.test/api/tasks',
    'https://runner.test/sanctum/csrf-cookie',
    'https://runner.test/web/login',
    'https://external.test/icons/icon.png',
];

for (const url of bypassUrls) {
    assert.equal(
        await fetchEvent(url),
        undefined,
        `${url} must bypass Service Worker`,
    );
}

assert.equal(
    await fetchEvent(
        'https://runner.test/icons/upload.png',
        { method: 'POST' },
    ),
    undefined,
);

assert.equal(calls.fetch.length, fetchCount);

// 4. Static assets use cache-first.
let response = await fetchEvent(
    'https://runner.test/icons/dynamic.png',
);

assert.equal(
    await response.text(),
    'network:https://runner.test/icons/dynamic.png',
);

const firstAssetFetchCount = calls.fetch.length;

response = await fetchEvent(
    'https://runner.test/icons/dynamic.png',
);

assert.equal(
    await response.text(),
    'network:https://runner.test/icons/dynamic.png',
);

assert.equal(calls.fetch.length, firstAssetFetchCount);

// 5. Offline static asset remains available from cache.
calls.offline = true;

response = await fetchEvent(
    'https://runner.test/icons/dynamic.png',
);

assert.equal(
    await response.text(),
    'network:https://runner.test/icons/dynamic.png',
);

// 6. Offline navigation fallback.
const cache = await sandbox.caches.open(
    'super-runner-runner-v4',
);

await cache.put(
    'https://runner.test/cached-page',
    basicResponse('cached requested page'),
);

response = await fetchEvent(
    'https://runner.test/cached-page',
    { mode: 'navigate' },
);

assert.equal(
    await response.text(),
    'cached requested page',
);

// 7. Fallback to App Shell.
response = await fetchEvent(
    'https://runner.test/uncached-page',
    { mode: 'navigate' },
);

assert.equal(await response.text(), 'network:/');

// 8. No cache available: return 503.
cacheStores.get('super-runner-runner-v4').delete(
    'https://runner.test/',
);

response = await fetchEvent(
    'https://runner.test/no-app-shell',
    { mode: 'navigate' },
);

assert.equal(response.status, 503);

console.log(
    'Service Worker tests passed: install, precache, cache cleanup, API exclusion, static cache-first, offline asset cache, navigation fallback and 503.',
);
