
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

    it('renders the Super Runner customer branding', () => {
        render(
            <TopAppBar
                activePage="dashboard"
                accountName="Nazim"
                onLogout={vi.fn()}
            />,
        );

        expect(screen.getByText('SR')).toBeInTheDocument();
        expect(screen.getByText('Super Runner')).toBeInTheDocument();
        expect(screen.getByText('Pelanggan')).toBeInTheDocument();
    });

    it('displays the active page label', () => {
        render(
            <TopAppBar
                activePage="requests"
                accountName="Nazim"
                onLogout={vi.fn()}
            />,
        );

        expect(
            screen.getByText('Senarai Permintaan'),
        ).toBeInTheDocument();
    });

    it('displays the customer account name', () => {
        render(
            <TopAppBar
                activePage="dashboard"
                accountName="Nazim Hud"
                onLogout={vi.fn()}
            />,
        );

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

        render(
            <TopAppBar
                activePage="dashboard"
                accountName="Nazim"
                onLogout={onLogout}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Keluar' }),
        );

        expect(onLogout).toHaveBeenCalledTimes(1);
    });

    it('disables the logout button while loading', () => {
        render(
            <TopAppBar
                activePage="dashboard"
                accountName="Nazim"
                onLogout={vi.fn()}
                loading
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Keluar' }),
        ).toBeDisabled();

        expect(
            screen.getByRole('button', { name: 'Keluar' }),
        ).toHaveTextContent('...');
    });

    it('keeps the logout button enabled when not loading', () => {
        render(
            <TopAppBar
                activePage="profile"
                accountName="Nazim"
                onLogout={vi.fn()}
                loading={false}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Keluar' }),
        ).toBeEnabled();

        expect(
            screen.getByRole('button', { name: 'Keluar' }),
        ).toHaveTextContent('Keluar');
    });
});
