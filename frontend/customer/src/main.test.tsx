
 // @vitest-environment jsdom

import { StrictMode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { createRootMock, renderMock } = vi.hoisted(() => ({
    createRootMock: vi.fn(),
    renderMock: vi.fn(),
}));

vi.mock('react-dom/client', () => ({
    createRoot: createRootMock,
}));

vi.mock('./app/CustomerApp', () => ({
    default: () => null,
}));

vi.mock('./RunnerPublicProfile', () => ({
    default: () => null,
}));

vi.mock('./RunnerPublicReviews', () => ({
    default: () => null,
}));

describe('Customer application routing', () => {
    beforeEach(() => {
        vi.resetModules();
        vi.clearAllMocks();

        createRootMock.mockReturnValue({
            render: renderMock,
        });

        document.body.innerHTML = '<div id="customer-app"></div>';

        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: {
                register: vi.fn().mockResolvedValue({}),
            },
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    async function loadApplication(path: string) {
        window.history.replaceState({}, '', path);
        await import('./main');
    }

    it('renders RunnerPublicProfile for the public profile URL', async () => {
        await loadApplication('/me/test-runner');

        expect(renderMock).toHaveBeenCalledTimes(1);

        const element = renderMock.mock.calls[0][0];
        const child = element.props.children;

        expect(element.type).toBe(StrictMode);
        expect(child.type.name).toBe('RunnerPublicProfile');
        expect(child.props.slug).toBe('test-runner');
    });

    it('renders RunnerPublicReviews for the reviews URL', async () => {
        await loadApplication('/me/test-runner/ulasan');

        expect(renderMock).toHaveBeenCalledTimes(1);

        const element = renderMock.mock.calls[0][0];
        const child = element.props.children;

        expect(child.type.name).toBe('RunnerPublicReviews');
        expect(child.props.slug).toBe('test-runner');
    });

    it('renders CustomerApp for other URLs', async () => {
        await loadApplication('/');

        expect(renderMock).toHaveBeenCalledTimes(1);

        const element = renderMock.mock.calls[0][0];
        const child = element.props.children;

        expect(child.type.name).toBe('CustomerApp');
    });

    it('registers the customer service worker on page load', async () => {
        await loadApplication('/');

        const register = navigator.serviceWorker.register;

        expect(register).toBeDefined();
    });
});
