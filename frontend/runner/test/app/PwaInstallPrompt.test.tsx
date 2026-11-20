
import {
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';

import {
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import PwaInstallPrompt from '../../src/app/PwaInstallPrompt';

interface MockBeforeInstallPromptEvent extends Event {
    prompt: ReturnType<typeof vi.fn>;
    userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
}

function setUserAgent(userAgent: string) {
    Object.defineProperty(navigator, 'userAgent', {
        configurable: true,
        value: userAgent,
    });
}

function setStandalone(value: boolean) {
    Object.defineProperty(navigator, 'standalone', {
        configurable: true,
        value,
    });
}

function createMediaQuery(matches = false) {
    const listeners = new Set<
        (event: MediaQueryListEvent) => void
    >();

    const mediaQuery = {
        matches,
        media: '(display-mode: standalone)',
        onchange: null,
        addEventListener: vi.fn(
            (
                _type: string,
                listener: (event: MediaQueryListEvent) => void,
            ) => {
                listeners.add(listener);
            },
        ),
        removeEventListener: vi.fn(
            (
                _type: string,
                listener: (event: MediaQueryListEvent) => void,
            ) => {
                listeners.delete(listener);
            },
        ),
        addListener: vi.fn(),
        removeListener: vi.fn(),
        dispatchEvent: vi.fn(() => true),
    } as unknown as MediaQueryList;

    return {
        mediaQuery,
        emit(matches: boolean) {
            Object.defineProperty(mediaQuery, 'matches', {
                configurable: true,
                value: matches,
            });

            const event = {
                matches,
                media: '(display-mode: standalone)',
            } as MediaQueryListEvent;

            listeners.forEach((listener) => listener(event));
        },
    };
}

function createInstallEvent(
    outcome: 'accepted' | 'dismissed' = 'accepted',
) {
    const event = new Event('beforeinstallprompt', {
        cancelable: true,
    }) as MockBeforeInstallPromptEvent;

    event.prompt = vi.fn().mockResolvedValue(undefined);
    event.userChoice = Promise.resolve({
        outcome,
        platform: 'web',
    });

    return event;
}

describe('PWA install prompt', () => {
    let mediaQueryController: ReturnType<typeof createMediaQuery>;

    beforeEach(() => {
        vi.clearAllMocks();

        setStandalone(false);

        mediaQueryController = createMediaQuery(false);

        Object.defineProperty(window, 'matchMedia', {
            configurable: true,
            writable: true,
            value: vi.fn(() => mediaQueryController.mediaQuery),
        });
    });

    it('detects Android and displays the installation guide', async () => {
        setUserAgent(
            'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        );

        render(<PwaInstallPrompt />);

        expect(
            await screen.findByText('Pasang aplikasi pada Android'),
        ).toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'PANDUAN PEMASANGAN',
            }),
        );

        expect(
            screen.getByText('Buka menu tiga titik pada Chrome.'),
        ).toBeInTheDocument();

        expect(
            screen.getByText('Sahkan pemasangan.'),
        ).toBeInTheDocument();
    });

    it('detects iPhone and displays the iOS installation guide', async () => {
        setUserAgent(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1',
        );

        render(<PwaInstallPrompt />);

        expect(
            await screen.findByText('Pasang aplikasi pada iPhone / iOS'),
        ).toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'PANDUAN PEMASANGAN',
            }),
        );

        expect(
            screen.getByText('Buka SUPER RUNNER menggunakan Safari.'),
        ).toBeInTheDocument();

        expect(
            screen.getByText('Pilih Add to Home Screen.'),
        ).toBeInTheDocument();
    });

    it('detects Huawei HarmonyOS and displays its installation guide', async () => {
        setUserAgent(
            'Mozilla/5.0 (Linux; HarmonyOS 4.0) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        );

        render(<PwaInstallPrompt />);

        expect(
            await screen.findByText(
                'Pasang aplikasi pada Huawei HarmonyOS',
            ),
        ).toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'PANDUAN PEMASANGAN',
            }),
        );

        expect(
            screen.getByText(
                'Buka SUPER RUNNER menggunakan pelayar Huawei.',
            ),
        ).toBeInTheDocument();

        expect(
            screen.getByText(
                /Add to Home Screen atau pilihan pemasangan aplikasi/,
            ),
        ).toBeInTheDocument();
    });

    it('displays the generic installation guide for other platforms', async () => {
        setUserAgent(
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        );

        render(<PwaInstallPrompt />);

        expect(
            await screen.findByText('Pasang aplikasi pada peranti anda'),
        ).toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'PANDUAN PEMASANGAN',
            }),
        );

        expect(
            screen.getByText(/Install App atau Add to Home Screen/),
        ).toBeInTheDocument();
    });

    it('uses the browser installation prompt when available', async () => {
        setUserAgent(
            'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        );

        render(<PwaInstallPrompt />);

        const installEvent = createInstallEvent('accepted');

        window.dispatchEvent(installEvent);

        const installButton = await screen.findByRole('button', {
            name: 'PASANG APLIKASI',
        });

        fireEvent.click(installButton);

        await waitFor(() => {
            expect(installEvent.prompt).toHaveBeenCalledTimes(1);
        });

        expect(
            await screen.findByRole('button', {
                name: 'CARA PEMASANGAN',
            }),
        ).toBeInTheDocument();
    });

    it('shows the manual guide after the user dismisses the browser prompt', async () => {
        setUserAgent(
            'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        );

        render(<PwaInstallPrompt />);

        const installEvent = createInstallEvent('dismissed');

        window.dispatchEvent(installEvent);

        fireEvent.click(
            await screen.findByRole('button', {
                name: 'PASANG APLIKASI',
            }),
        );

        await waitFor(() => {
            expect(installEvent.prompt).toHaveBeenCalledTimes(1);
        });

        expect(
            await screen.findByRole('button', {
                name: 'CARA PEMASANGAN',
            }),
        ).toBeInTheDocument();

        expect(
            screen.getByText('Cara memasang SUPER RUNNER'),
        ).toBeInTheDocument();

        expect(
            screen.getByText('Buka menu tiga titik pada Chrome.'),
        ).toBeInTheDocument();
    });

    it('hides the panel after the application is installed', async () => {
        setUserAgent(
            'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        );

        render(<PwaInstallPrompt />);

        expect(
            await screen.findByLabelText('Pemasangan Super Runner'),
        ).toBeInTheDocument();

        window.dispatchEvent(new Event('appinstalled'));

        await waitFor(() => {
            expect(
                screen.queryByLabelText('Pemasangan Super Runner'),
            ).not.toBeInTheDocument();
        });
    });

    it('does not display the panel when already running in standalone mode', async () => {
        setUserAgent(
            'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        );

        Object.defineProperty(window, 'matchMedia', {
            configurable: true,
            writable: true,
            value: vi.fn(() => ({
                ...mediaQueryController.mediaQuery,
                matches: true,
            })),
        });

        render(<PwaInstallPrompt />);

        await waitFor(() => {
            expect(
                screen.queryByLabelText('Pemasangan Super Runner'),
            ).not.toBeInTheDocument();
        });
    });

    it('hides the panel when display mode changes to standalone', async () => {
        setUserAgent(
            'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        );

        render(<PwaInstallPrompt />);

        expect(
            await screen.findByLabelText('Pemasangan Super Runner'),
        ).toBeInTheDocument();

        mediaQueryController.emit(true);

        await waitFor(() => {
            expect(
                screen.queryByLabelText('Pemasangan Super Runner'),
            ).not.toBeInTheDocument();
        });
    });

    it('removes browser event listeners when the component unmounts', () => {
        setUserAgent(
            'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        );

        const addEventListenerSpy = vi.spyOn(
            window,
            'addEventListener',
        );

        const removeEventListenerSpy = vi.spyOn(
            window,
            'removeEventListener',
        );

        const view = render(<PwaInstallPrompt />);

        view.unmount();

        expect(addEventListenerSpy).toHaveBeenCalledWith(
            'beforeinstallprompt',
            expect.any(Function),
        );

        expect(removeEventListenerSpy).toHaveBeenCalledWith(
            'beforeinstallprompt',
            expect.any(Function),
        );

        expect(removeEventListenerSpy).toHaveBeenCalledWith(
            'appinstalled',
            expect.any(Function),
        );

        expect(
            mediaQueryController.mediaQuery.removeEventListener,
        ).toHaveBeenCalledWith(
            'change',
            expect.any(Function),
        );
    });
});
