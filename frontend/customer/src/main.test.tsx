
// @vitest-environment jsdom

import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import { StrictMode } from 'react';

const mocks = vi.hoisted(() => {
    const createRoot = vi.fn();
    const render = vi.fn();
    const registerServiceWorker = vi.fn();

    function CustomerAppMock() {
        return null;
    }

    function RunnerPublicProfileMock() {
        return null;
    }

    function RunnerPublicReviewsMock() {
        return null;
    }

    return {
        createRoot,
        render,
        registerServiceWorker,
        CustomerAppMock,
        RunnerPublicProfileMock,
        RunnerPublicReviewsMock,
    };
});

vi.mock('react-dom/client', () => ({
    createRoot: mocks.createRoot,
}));

vi.mock('./app/CustomerApp', () => ({
    default: mocks.CustomerAppMock,
}));

vi.mock('./RunnerPublicProfile', () => ({
    default: mocks.RunnerPublicProfileMock,
}));

vi.mock('./RunnerPublicReviews', () => ({
    default: mocks.RunnerPublicReviewsMock,
}));

import CustomerApp from './app/CustomerApp';
import RunnerPublicProfile from './RunnerPublicProfile';
import RunnerPublicReviews from './RunnerPublicReviews';

describe('Customer application entry point', () => {
    let loadCallbacks: EventListener[];
    let addEventListenerSpy: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        vi.resetModules();
        vi.clearAllMocks();

        loadCallbacks = [];

        mocks.createRoot.mockReturnValue({
            render: mocks.render,
        });

        mocks.registerServiceWorker.mockResolvedValue({});

        document.body.innerHTML =
            '<div id="customer-app"></div>';

        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: {
                register: mocks.registerServiceWorker,
            },
        });

        addEventListenerSpy = vi
            .spyOn(window, 'addEventListener')
            .mockImplementation(((eventName: string, listener: EventListenerOrEventListenerObject) => {
                if (
                    eventName === 'load' &&
                    typeof listener === 'function'
                ) {
                    loadCallbacks.push(listener);
                }
            }) as typeof window.addEventListener);
    });

    afterEach(() => {
        addEventListenerSpy.mockRestore();
        vi.restoreAllMocks();
        document.body.innerHTML = '';
    });

    async function loadApplication(path: string) {
        window.history.replaceState({}, '', path);
        await import('./main');
    }

    function getRenderedElement() {
        expect(mocks.createRoot).toHaveBeenCalledTimes(1);
        expect(mocks.render).toHaveBeenCalledTimes(1);

        const element = mocks.render.mock.calls[0][0];

        expect(element.type).toBe(StrictMode);

        return element.props.children;
    }

    it('renders the public Runner profile route', async () => {
        await loadApplication('/me/test-runner');

        const child = getRenderedElement();

        expect(child.type).toBe(RunnerPublicProfile);
        expect(child.props.slug).toBe('test-runner');
    });

    it('renders the public Runner profile with trailing slash', async () => {
        await loadApplication('/me/test-runner/');

        const child = getRenderedElement();

        expect(child.type).toBe(RunnerPublicProfile);
        expect(child.props.slug).toBe('test-runner');
    });

    it('renders the public Runner reviews route', async () => {
        await loadApplication('/me/test-runner/ulasan');

        const child = getRenderedElement();

        expect(child.type).toBe(RunnerPublicReviews);
        expect(child.props.slug).toBe('test-runner');
    });

    it('prioritizes the reviews route over the profile route', async () => {
        await loadApplication('/me/test-runner/ulasan/');

        const child = getRenderedElement();

        expect(child.type).toBe(RunnerPublicReviews);
        expect(child.props.slug).toBe('test-runner');
    });

    it('renders CustomerApp for the homepage', async () => {
        await loadApplication('/');

        const child = getRenderedElement();

        expect(child.type).toBe(CustomerApp);
    });

    it('renders CustomerApp for unsupported Runner slug formats', async () => {
        await loadApplication('/me/ab');

        const child = getRenderedElement();

        expect(child.type).toBe(CustomerApp);
    });

    it('renders CustomerApp for unrelated routes', async () => {
        await loadApplication('/permintaan');

        const child = getRenderedElement();

        expect(child.type).toBe(CustomerApp);
    });

    it('does not render when the application root is missing', async () => {
        document.body.innerHTML = '';

        await loadApplication('/');

        expect(mocks.createRoot).not.toHaveBeenCalled();
        expect(mocks.render).not.toHaveBeenCalled();
    });

    it('registers the Service Worker only after the page load event', async () => {
        await loadApplication('/');

        expect(addEventListenerSpy).toHaveBeenCalledWith(
            'load',
            expect.any(Function),
        );

        expect(loadCallbacks).toHaveLength(1);

        expect(
            mocks.registerServiceWorker,
        ).not.toHaveBeenCalled();

        loadCallbacks[0](new Event('load'));

        expect(
            mocks.registerServiceWorker,
        ).toHaveBeenCalledTimes(1);

        expect(
            mocks.registerServiceWorker,
        ).toHaveBeenCalledWith('/sw.js');
    });

    it('handles Service Worker registration failure', async () => {
        const registrationError = new Error(
            'Service Worker registration failed',
        );

        mocks.registerServiceWorker.mockRejectedValue(
            registrationError,
        );

        const consoleErrorSpy = vi
            .spyOn(console, 'error')
            .mockImplementation(() => undefined);

        await loadApplication('/');

        expect(loadCallbacks).toHaveLength(1);

        loadCallbacks[0](new Event('load'));

        await vi.waitFor(() => {
            expect(consoleErrorSpy).toHaveBeenCalledWith(
                'Customer Service Worker registration failed:',
                registrationError,
            );
        });
    });
});
