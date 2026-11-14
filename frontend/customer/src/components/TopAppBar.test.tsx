
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
} from '@testing-library/react';

import TopAppBar from './TopAppBar';

describe('TopAppBar', () => {
    afterEach(() => {
        cleanup();
    });

    const renderTopAppBar = (
        props: Partial<React.ComponentProps<typeof TopAppBar>> = {},
    ) => {
        const defaultProps: React.ComponentProps<typeof TopAppBar> = {
            activePage: 'dashboard',
            accountName: 'Nazim',
            onLogout: vi.fn(),
            unreadNotificationTotal: 0,
            onOpenNotifications: vi.fn(),
        };

        return render(
            <TopAppBar
                {...defaultProps}
                {...props}
            />,
        );
    };

    it('renders the Super Runner customer branding', () => {
        renderTopAppBar();

        expect(screen.getByText('SR')).toBeInTheDocument();
        expect(screen.getByText('Super Runner')).toBeInTheDocument();
        expect(screen.getByText('Pelanggan')).toBeInTheDocument();
    });

    it('displays the active page label', () => {
        renderTopAppBar({
            activePage: 'requests',
        });

        expect(
            screen.getByText('Senarai Permintaan'),
        ).toBeInTheDocument();
    });

    it('displays the customer account name', () => {
        renderTopAppBar({
            accountName: 'Nazim Hud',
        });

        expect(screen.getByText('Akaun')).toBeInTheDocument();

        expect(
            screen.getByText('Nazim Hud'),
        ).toBeInTheDocument();

        expect(
            screen.getByText('Nazim Hud'),
        ).toHaveAttribute('title', 'Nazim Hud');
    });

    it('calls onLogout when the logout button is clicked', () => {
        const onLogout = vi.fn();

        renderTopAppBar({
            onLogout,
        });

        fireEvent.click(
            screen.getByRole('button', { name: 'Keluar' }),
        );

        expect(onLogout).toHaveBeenCalledTimes(1);
    });

    it('disables the logout button while loading', () => {
        renderTopAppBar({
            loading: true,
        });

        expect(
            screen.getByRole('button', { name: 'Keluar' }),
        ).toBeDisabled();

        expect(
            screen.getByRole('button', { name: 'Keluar' }),
        ).toHaveTextContent('...');
    });

    it('keeps the logout button enabled when not loading', () => {
        renderTopAppBar({
            activePage: 'profile',
            loading: false,
        });

        expect(
            screen.getByRole('button', { name: 'Keluar' }),
        ).toBeEnabled();

        expect(
            screen.getByRole('button', { name: 'Keluar' }),
        ).toHaveTextContent('Keluar');
    });

    it('calls onOpenNotifications when notification button is clicked', () => {
        const onOpenNotifications = vi.fn();

        renderTopAppBar({
            onOpenNotifications,
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Notifikasi',
            }),
        );

        expect(
            onOpenNotifications,
        ).toHaveBeenCalledTimes(1);
    });

    it('displays unread notification total', () => {
        renderTopAppBar({
            unreadNotificationTotal: 5,
        });

        expect(
            screen.getByRole('button', {
                name: 'Notifikasi, 5 belum dibaca',
            }),
        ).toBeInTheDocument();

        expect(
            screen.getByText('5'),
        ).toBeInTheDocument();
    });

    it('does not display notification count when there are no unread notifications', () => {
        renderTopAppBar({
            unreadNotificationTotal: 0,
        });

        expect(
            screen.getByRole('button', {
                name: 'Notifikasi',
            }),
        ).toBeInTheDocument();

        expect(
            screen.queryByText('0'),
        ).not.toBeInTheDocument();
    });

    it('displays 99+ when unread notification total exceeds 99', () => {
        renderTopAppBar({
            unreadNotificationTotal: 120,
        });

        expect(
            screen.getByRole('button', {
                name: 'Notifikasi, 120 belum dibaca',
            }),
        ).toBeInTheDocument();

        expect(
            screen.getByText('99+'),
        ).toBeInTheDocument();
    });
});
