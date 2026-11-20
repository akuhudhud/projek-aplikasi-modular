
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import registerRunnerServiceWorker from '../../src/pwa/registerRunnerServiceWorker';

describe('registerRunnerServiceWorker', () => {
    const originalServiceWorker = navigator.serviceWorker;

    let register: ReturnType<typeof vi.fn>;
    let update: ReturnType<typeof vi.fn>;
    let loadHandler: (() => void) | undefined;
    let controllerChangeHandler: (() => void) | undefined;

    beforeEach(() => {
        register = vi.fn();
        update = vi.fn().mockResolvedValue(undefined);

        loadHandler = undefined;
        controllerChangeHandler = undefined;

        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: {
                controller: null,
                register,
                addEventListener: vi.fn((event, handler) => {
                    if (event === 'controllerchange') {
                        controllerChangeHandler = handler;
                    }
                }),
            },
        });

        vi.spyOn(window, 'addEventListener').mockImplementation(
            ((event: string, handler: EventListenerOrEventListenerObject) => {
                if (event === 'load') {
                    loadHandler = handler as () => void;
                }

                return window;
            }) as typeof window.addEventListener,
        );

        register.mockResolvedValue({ update });
    });

    afterEach(() => {
        vi.restoreAllMocks();

        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: originalServiceWorker,
        });
    });

    it('registers the Runner Service Worker after window load', async () => {
        registerRunnerServiceWorker();

        expect(register).not.toHaveBeenCalled();
        expect(loadHandler).toBeDefined();

        loadHandler?.();

        await Promise.resolve();

        expect(register).toHaveBeenCalledWith('/sw.js', {
            updateViaCache: 'none',
        });

        expect(update).toHaveBeenCalledOnce();
    });

    it('reloads once when an existing controller changes', () => {
        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: {
                controller: {},
                register,
                addEventListener: vi.fn((event, handler) => {
                    if (event === 'controllerchange') {
                        controllerChangeHandler = handler;
                    }
                }),
            },
        });

        const reload = vi
            .spyOn(window.location, 'reload')
            .mockImplementation(() => {});

        registerRunnerServiceWorker();
        loadHandler?.();

        expect(controllerChangeHandler).toBeDefined();

        controllerChangeHandler?.();
        controllerChangeHandler?.();

        expect(reload).toHaveBeenCalledOnce();
    });

    it('does not register when Service Worker is unsupported', () => {
        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: undefined,
        });

        registerRunnerServiceWorker();

        expect(loadHandler).toBeUndefined();
        expect(register).not.toHaveBeenCalled();
    });

    it('handles registration failure', async () => {
        const error = new Error('Registration failed');
        const consoleError = vi
            .spyOn(console, 'error')
            .mockImplementation(() => {});

        register.mockRejectedValue(error);

        registerRunnerServiceWorker();
        loadHandler?.();

        await Promise.resolve();
        await Promise.resolve();

        expect(consoleError).toHaveBeenCalledWith(
            'Runner Service Worker registration failed:',
            error,
        );
    });
});
