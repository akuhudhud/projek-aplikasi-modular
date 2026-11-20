
import {
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import serviceWorkerSource from '../public/sw.js?raw';

type WorkerEvent = {
    waitUntil?: (promise: Promise<unknown>) => void;
};

type WorkerListener = (event: WorkerEvent) => void;

describe('Customer Service Worker Activation', () => {
    it(
        'waits for clients.claim() before completing activation',
        async () => {
            const listeners = new Map<string, WorkerListener>();

            let releaseClaim: (() => void) | undefined;

            const claimPromise = new Promise<void>((resolve) => {
                releaseClaim = resolve;
            });

            const clientsMock = {
                claim: vi.fn(() => claimPromise),
            };

            const selfMock = {
                location: {
                    origin: 'https://app.test',
                },
                clients: clientsMock,
                skipWaiting: vi.fn(),
                addEventListener: vi.fn(
                    (
                        eventName: string,
                        listener: WorkerListener,
                    ) => {
                        listeners.set(eventName, listener);
                    },
                ),
            };

            const cachesMock = {
                keys: vi.fn().mockResolvedValue([]),
                delete: vi.fn().mockResolvedValue(true),
                open: vi.fn(),
            };

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
                vi.fn(),
                URL,
                Response,
            );

            const activateHandler = listeners.get('activate');

            expect(activateHandler).toBeDefined();

            let activation: Promise<unknown> | undefined;

            activateHandler?.({
                waitUntil(promise) {
                    activation = promise;
                },
            });

            expect(activation).toBeDefined();

            if (!activation) {
                throw new Error(
                    'Activation promise was not registered.',
                );
            }

            expect(clientsMock.claim).toHaveBeenCalledTimes(1);

            let activationCompleted = false;

            void activation.then(() => {
                activationCompleted = true;
            });

            await Promise.resolve();

            expect(activationCompleted).toBe(false);

            releaseClaim?.();

            await activation;

            expect(activationCompleted).toBe(true);
        },
    );
});
