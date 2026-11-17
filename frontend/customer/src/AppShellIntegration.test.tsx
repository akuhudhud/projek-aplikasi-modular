
// @vitest-environment jsdom

import {
    afterEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/react';

import { useState } from 'react';

import AppShell from './app/AppShell';

import type { CustomerNotification } from './api';
import type { CustomerPage } from './app/navigation';
import type { CustomerNotifications } from './features/notifications/useCustomerNotifications';

function createNotifications(): CustomerNotifications {
    return {
        notifications: [],
        unreadTotal: 0,
        currentPage: 1,
        lastPage: 1,
        total: 0,
        loading: false,
        error: '',
        actionLoadingId: null,
        deletingAll: false,
        actionMessage: '',
        actionError: '',
        setCurrentPage: vi.fn(),
        refresh: vi.fn(),
        markAsRead: vi.fn().mockResolvedValue(true),
        deleteNotification: vi.fn().mockResolvedValue(true),
        deleteAll: vi.fn().mockResolvedValue(true),
        clearActionMessages: vi.fn(),
    };
}

type AppShellHarnessProps = {
    onLogout: () => void;
    onOpenNotification?: (notification: CustomerNotification) => void;
};

function AppShellHarness({
    onLogout,
    onOpenNotification = vi.fn(),
}: AppShellHarnessProps) {
    const [activePage, setActivePage] =
        useState<CustomerPage>('dashboard');

    const notifications = createNotifications();

    return (
        <AppShell
            activePage={activePage}
            accountName="Customer Test"
            onNavigate={setActivePage}
            onLogout={onLogout}
            onOpenNotification={onOpenNotification}
            notifications={notifications}
        >
            <section>
                <h1>Kandungan Ruang Kerja</h1>
            </section>
        </AppShell>
    );
}

describe('Integrasi AppShell Pelanggan', () => {
    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it(
        'memaparkan lima destinasi navigasi dan mengemas kini halaman aktif',
        () => {
            const onLogout = vi.fn();

            const { container } = render(
                <AppShellHarness onLogout={onLogout} />,
            );

            const navigation = screen.getByRole(
                'navigation',
                {
                    name: 'Navigasi utama',
                },
            );

            const navigationButtons =
                within(navigation).getAllByRole('button');

            expect(navigationButtons).toHaveLength(5);

            expect(
                screen.getByText('Super Runner'),
            ).toBeTruthy();

            expect(
                screen.getByText('Pelanggan'),
            ).toBeTruthy();

            expect(
                screen.getByText('Customer Test'),
            ).toBeTruthy();

            expect(
                screen.getByRole('heading', {
                    name: 'Kandungan Ruang Kerja',
                }),
            ).toBeTruthy();

            const pageTitle = container.querySelector(
                '.sr-top-app-bar__page',
            );

            expect(pageTitle).not.toBeNull();

            const destinations: Array<{
                page: CustomerPage;
                accessibleLabel: string;
                pageTitle: string;
            }> = [
                {
                    page: 'dashboard',
                    accessibleLabel: 'Utama',
                    pageTitle: 'Utama',
                },
                {
                    page: 'requests',
                    accessibleLabel: 'Permintaan',
                    pageTitle: 'Senarai Permintaan',
                },
                {
                    page: 'create',
                    accessibleLabel: 'Cipta Tugasan',
                    pageTitle: 'Cipta Tugasan',
                },
                {
                    page: 'tasks',
                    accessibleLabel: 'Tugasan',
                    pageTitle: 'Tugasan Saya',
                },
                {
                    page: 'profile',
                    accessibleLabel: 'Profil',
                    pageTitle: 'Profil Saya',
                },
            ];

            for (const destination of destinations) {
                const button = within(navigation).getByRole(
                    'button',
                    {
                        name: destination.accessibleLabel,
                    },
                );

                fireEvent.click(button);

                expect(pageTitle?.textContent).toBe(
                    destination.pageTitle,
                );

                expect(
                    button.getAttribute('aria-current'),
                ).toBe('page');

                for (const other of destinations) {
                    if (other.page === destination.page) {
                        continue;
                    }

                    const otherButton =
                        within(navigation).getByRole(
                            'button',
                            {
                                name: other.accessibleLabel,
                            },
                        );

                    expect(
                        otherButton.getAttribute('aria-current'),
                    ).toBeNull();
                }
            }

            expect(onLogout).not.toHaveBeenCalled();
        },
    );

    it(
        'memanggil fungsi log keluar apabila butang ditekan',
        () => {
            const onLogout = vi.fn();

            render(
                <AppShellHarness onLogout={onLogout} />,
            );

            fireEvent.click(
                screen.getByRole('button', {
                    name: 'Keluar',
                }),
            );

            expect(onLogout).toHaveBeenCalledTimes(1);
        },
    );

    it(
        'menyahaktifkan butang log keluar semasa proses sedang berjalan',
        () => {
            const onLogout = vi.fn();

            render(
                <AppShell
                    activePage="dashboard"
                    accountName="Customer Test"
                    onNavigate={vi.fn()}
                    onLogout={onLogout}
                    onOpenNotification={vi.fn()}
                    loading
                    notifications={createNotifications()}
                >
                    <p>Kandungan Ruang Kerja</p>
                </AppShell>,
            );

            const logoutButton = screen.getByRole(
                'button',
                {
                    name: 'Keluar',
                },
            );

            expect(
                (logoutButton as HTMLButtonElement).disabled,
            ).toBe(true);

            expect(logoutButton.textContent).toBe('...');
        },
    );
});
