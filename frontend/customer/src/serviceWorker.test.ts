
import {
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import serviceWorkerSource from '../public/sw.js?raw';

type WorkerRequest = {
    method: string;
    url: string;
    mode: string;
};

type WorkerEvent = {
    request?: WorkerRequest;
    waitUntil?: (promise: Promise<unknown>) => void;
    respondWith?: (promise: Promise<Response>) => void;
};

type WorkerListener = (event: WorkerEvent) => void;

function createServiceWorkerHarness() {
    const listeners = new Map<string, WorkerListener>();

    const cache = {
        addAll: vi.fn().mockResolvedValue(undefined),
        match: vi.fn(
            async (): Promise<Response | undefined> => undefined,
        ),
        put: vi.fn().mockResolvedValue(undefined),
    };

    const cachesMock = {
        open: vi.fn().mockResolvedValue(cache),
        keys: vi.fn().mockResolvedValue([
            'super-runner-customer-v2',
            'super-runner-customer-v3',
            'another-application-cache',
        ]),
        delete: vi.fn().mockResolvedValue(true),
    };

    const selfMock = {
        location: {
            origin: 'https://app.test',
        },
        clients: {
            claim: vi.fn().mockResolvedValue(undefined),
        },
        skipWaiting: vi.fn(),
        addEventListener: vi.fn(
            (eventName: string, listener: WorkerListener) => {
                listeners.set(eventName, listener);
            },
        ),
    };

    const fetchMock = vi.fn(
        async (_input: RequestInfo | URL) => {
            return new Response('network response', {
                status: 200,
            });
        },
    );

    const executeWorker = new Function(
        'self',
        'caches',
        'fetch',
        'URL',
        'Response',
        serviceWorkerSource,
    ) as (
        self: unknown,
        caches: unknown,
        fetch: unknown,
        url: unknown,
        response: unknown,
    ) => void;

    executeWorker(
        selfMock,
        cachesMock,
        fetchMock,
        URL,
        Response,
    );

    return {
        listeners,
        cache,
        cachesMock,
        selfMock,
        fetchMock,
    };
}

describe('Customer Service Worker', () => {
    it('registers install, activate and fetch event handlers', () => {
        const worker = createServiceWorkerHarness();

        expect(worker.listeners.has('install')).toBe(true);
        expect(worker.listeners.has('activate')).toBe(true);
        expect(worker.listeners.has('fetch')).toBe(true);
    });

    it('precaches the application shell and build assets during installation', async () => {
        const worker = createServiceWorkerHarness();

        worker.fetchMock.mockImplementation(
            async (input: RequestInfo | URL) => {
                if (String(input) === '/manifest.json') {
                    return new Response(
                        JSON.stringify({
                            'index.html': {
                                file: 'assets/index.js',
                                css: ['assets/index.css'],
                                assets: ['assets/logo.svg'],
                            },
                        }),
                        {
                            status: 200,
                            headers: {
                                'Content-Type': 'application/json',
                            },
                        },
                    );
                }

                return new Response('network response');
            },
        );

        const installHandler = worker.listeners.get('install');

        expect(installHandler).toBeDefined();

        let installation: Promise<unknown> | undefined;

        installHandler?.({
            waitUntil(promise) {
                installation = promise;
            },
        });

        expect(installation).toBeDefined();

        if (!installation) {
            throw new Error('Installation promise was not registered.');
        }

        await installation;

        expect(worker.cache.addAll).toHaveBeenNthCalledWith(1, [
            '/',
            '/manifest.webmanifest',
            '/manifest.json',
        ]);

        expect(worker.cache.addAll).toHaveBeenNthCalledWith(2, [
            '/assets/index.js',
            '/assets/index.css',
            '/assets/logo.svg',
        ]);

        expect(worker.selfMock.skipWaiting).toHaveBeenCalledTimes(1);
    });

    it('fails installation when the build manifest cannot be loaded', async () => {
        const worker = createServiceWorkerHarness();

        worker.fetchMock.mockResolvedValue(
            new Response('Not found', {
                status: 404,
            }),
        );

        const installHandler = worker.listeners.get('install');

        let installation: Promise<unknown> | undefined;

        installHandler?.({
            waitUntil(promise) {
                installation = promise;
            },
        });

        expect(installation).toBeDefined();

        if (!installation) {
            throw new Error('Installation promise was not registered.');
        }

        await expect(installation).rejects.toThrow(
            'Unable to load the Vite build manifest.',
        );
    });

    it('deletes old application caches but preserves unrelated caches', async () => {
        const worker = createServiceWorkerHarness();

        const activateHandler = worker.listeners.get('activate');

        let activation: Promise<unknown> | undefined;

        activateHandler?.({
            waitUntil(promise) {
                activation = promise;
            },
        });

        expect(activation).toBeDefined();

        if (!activation) {
            throw new Error('Activation promise was not registered.');
        }

        await activation;

        expect(worker.cachesMock.delete).toHaveBeenCalledTimes(1);
        expect(worker.cachesMock.delete).toHaveBeenCalledWith(
            'super-runner-customer-v2',
        );

        expect(worker.cachesMock.delete).not.toHaveBeenCalledWith(
            'super-runner-customer-v3',
        );

        expect(worker.cachesMock.delete).not.toHaveBeenCalledWith(
            'another-application-cache',
        );

        expect(worker.selfMock.clients.claim).toHaveBeenCalledTimes(1);
    });

    it('does not intercept API, web or Sanctum requests', () => {
        const worker = createServiceWorkerHarness();

        const fetchHandler = worker.listeners.get('fetch');

        expect(fetchHandler).toBeDefined();

        const excludedPaths = [
            '/api/tasks',
            '/web/auth/me',
            '/sanctum/csrf-cookie',
        ];

        for (const path of excludedPaths) {
            const respondWith = vi.fn();

            fetchHandler?.({
                request: {
                    method: 'GET',
                    url: `https://app.test${path}`,
                    mode: 'cors',
                },
                respondWith,
            });

            expect(respondWith).not.toHaveBeenCalled();
        }
    });

    it('does not intercept requests to another origin', () => {
        const worker = createServiceWorkerHarness();

        const respondWith = vi.fn();

        worker.listeners.get('fetch')?.({
            request: {
                method: 'GET',
                url: 'https://external.test/resource.js',
                mode: 'cors',
            },
            respondWith,
        });

        expect(respondWith).not.toHaveBeenCalled();
    });

    it('does not intercept non-GET requests', () => {
        const worker = createServiceWorkerHarness();

        const respondWith = vi.fn();

        worker.listeners.get('fetch')?.({
            request: {
                method: 'POST',
                url: 'https://app.test/web/auth/login',
                mode: 'cors',
            },
            respondWith,
        });

        expect(respondWith).not.toHaveBeenCalled();
    });

    it('uses cached application shell when navigation fails offline', async () => {
        const worker = createServiceWorkerHarness();

        worker.fetchMock.mockRejectedValue(
            new Error('Network unavailable'),
        );

        worker.cache.match.mockResolvedValue(
            new Response('offline application shell', {
                status: 200,
            }),
        );

        let navigationResponse: Promise<Response> | undefined;

        worker.listeners.get('fetch')?.({
            request: {
                method: 'GET',
                url: 'https://app.test/customer/tasks',
                mode: 'navigate',
            },
            respondWith(promise) {
                navigationResponse = promise;
            },
        });

        expect(navigationResponse).toBeDefined();

        if (!navigationResponse) {
            throw new Error('Navigation response was not registered.');
        }

        const response = await navigationResponse;

        expect(response.status).toBe(200);
        expect(await response.text()).toBe('offline application shell');

        expect(worker.cache.match).toHaveBeenCalledWith('/');
    });

    it('uses cached assets before requesting the network', async () => {
        const worker = createServiceWorkerHarness();

        worker.cache.match.mockResolvedValue(
            new Response('cached stylesheet', {
                status: 200,
            }),
        );

        let assetResponse: Promise<Response> | undefined;

        worker.listeners.get('fetch')?.({
            request: {
                method: 'GET',
                url: 'https://app.test/assets/app.css',
                mode: 'cors',
            },
            respondWith(promise) {
                assetResponse = promise;
            },
        });

        expect(assetResponse).toBeDefined();

        if (!assetResponse) {
            throw new Error('Asset response was not registered.');
        }

        const response = await assetResponse;

        expect(await response.text()).toBe('cached stylesheet');
        expect(worker.fetchMock).not.toHaveBeenCalled();
    });

    it('caches successful same-origin GET assets', async () => {
        const worker = createServiceWorkerHarness();

        worker.cache.match.mockResolvedValue(undefined);

        const networkResponse = new Response('network asset', {
            status: 200,
        });

        Object.defineProperty(networkResponse, 'type', {
            value: 'basic',
        });

        worker.fetchMock.mockResolvedValue(networkResponse);

        let assetResponse: Promise<Response> | undefined;

        const request: WorkerRequest = {
            method: 'GET',
            url: 'https://app.test/assets/app.js',
            mode: 'cors',
        };

        worker.listeners.get('fetch')?.({
            request,
            respondWith(promise) {
                assetResponse = promise;
            },
        });

        expect(assetResponse).toBeDefined();

        if (!assetResponse) {
            throw new Error('Asset response was not registered.');
        }

        const response = await assetResponse;

        expect(response.status).toBe(200);
        expect(worker.fetchMock).toHaveBeenCalledTimes(1);
        expect(worker.cache.put).toHaveBeenCalledTimes(1);
        expect(worker.cache.put.mock.calls[0][0]).toBe(request);
    });
});
