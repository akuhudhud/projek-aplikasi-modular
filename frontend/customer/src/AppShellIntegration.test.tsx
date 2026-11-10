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

import type { CustomerPage } from './app/navigation';

type AppShellHarnessProps = {
    onLogout: () => void;
};

function AppShellHarness({
    onLogout,
}: AppShellHarnessProps) {
    const [activePage, setActivePage] =
        useState<CustomerPage>('dashboard');

    return (
        <AppShell
            activePage={activePage}
            accountName="Customer Test"
            onNavigate={setActivePage}
            onLogout={onLogout}
        >
            <section>
                <h1>Workspace Content</h1>
            </section>
        </AppShell>
    );
}

describe('Customer AppShell integration', () => {
    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it(
        'renders all five navigation destinations and updates the active page',
        () => {
            const onLogout = vi.fn();

            render(
                <AppShellHarness
                    onLogout={onLogout}
                />,
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
                screen.getByText('Customer'),
            ).toBeTruthy();

            expect(
                screen.getByText('Customer Test'),
            ).toBeTruthy();

            expect(
                screen.getByRole('heading', {
                    name: 'Workspace Content',
                }),
            ).toBeTruthy();

            const destinations: Array<{
                page: CustomerPage;
                accessibleLabel: string;
                pageTitle: string;
            }> = [
                {
                    page: 'dashboard',
                    accessibleLabel: 'Home',
                    pageTitle: 'Home',
                },
                {
                    page: 'requests',
                    accessibleLabel: 'Open Request',
                    pageTitle: 'Requests',
                },
                {
                    page: 'create',
                    accessibleLabel: 'Create',
                    pageTitle: 'Create Request',
                },
                {
                    page: 'tasks',
                    accessibleLabel: 'Tugasan Saya',
                    pageTitle: 'My Tasks',
                },
                {
                    page: 'profile',
                    accessibleLabel: 'Profil',
                    pageTitle: 'My Profile',
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

                expect(
                    screen.getByText(
                        destination.pageTitle,
                    ),
                ).toBeTruthy();

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
                        otherButton.getAttribute(
                            'aria-current',
                        ),
                    ).toBeNull();
                }
            }

            expect(onLogout).not.toHaveBeenCalled();
        },
    );

    it(
        'calls the logout handler when the logout button is clicked',
        () => {
            const onLogout = vi.fn();

            render(
                <AppShellHarness
                    onLogout={onLogout}
                />,
            );

            fireEvent.click(
                screen.getByRole('button', {
                    name: 'Logout',
                }),
            );

            expect(onLogout).toHaveBeenCalledTimes(1);
        },
    );

    it(
        'disables the logout button while logout is loading',
        () => {
            const onLogout = vi.fn();

            render(
                <AppShell
                    activePage="dashboard"
                    accountName="Customer Test"
                    onNavigate={vi.fn()}
                    onLogout={onLogout}
                    loading
                >
                    <p>Workspace Content</p>
                </AppShell>,
            );

            const logoutButton = screen.getByRole(
                'button',
                {
                    name: 'Logout',
                },
            );

            expect(
                (logoutButton as HTMLButtonElement).disabled,
            ).toBe(true);

            expect(logoutButton.textContent).toBe('...');
        },
    );
});
