
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

import BottomNavigation from './BottomNavigation';

describe('BottomNavigation', () => {
    afterEach(() => {
        cleanup();
    });

    it('renders all five navigation items', () => {
        render(
            <BottomNavigation
                activePage="dashboard"
                onNavigate={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('navigation', {
                name: 'Navigasi utama',
            }),
        ).toBeInTheDocument();

        expect(screen.getByRole('button', { name: 'Utama' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Permintaan' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Cipta' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Tugasan' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Profil' })).toBeInTheDocument();
    });

    it('marks the active page with aria-current', () => {
        render(
            <BottomNavigation
                activePage="requests"
                onNavigate={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Permintaan' }),
        ).toHaveAttribute('aria-current', 'page');

        expect(
            screen.getByRole('button', { name: 'Utama' }),
        ).not.toHaveAttribute('aria-current');

        expect(
            screen.getByRole('button', { name: 'Profil' }),
        ).not.toHaveAttribute('aria-current');
    });

    it('marks the create button as the primary navigation item', () => {
        render(
            <BottomNavigation
                activePage="dashboard"
                onNavigate={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Cipta' }),
        ).toHaveClass('is-primary');
    });

    it('calls onNavigate with the selected page', () => {
        const onNavigate = vi.fn();

        render(
            <BottomNavigation
                activePage="dashboard"
                onNavigate={onNavigate}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Permintaan' }),
        );

        expect(onNavigate).toHaveBeenCalledExactlyOnceWith(
            'requests',
        );
    });

    it('calls onNavigate when the create button is clicked', () => {
        const onNavigate = vi.fn();

        render(
            <BottomNavigation
                activePage="dashboard"
                onNavigate={onNavigate}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Cipta' }),
        );

        expect(onNavigate).toHaveBeenCalledExactlyOnceWith(
            'create',
        );
    });

    it('provides descriptions through button titles', () => {
        render(
            <BottomNavigation
                activePage="dashboard"
                onNavigate={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Utama' }),
        ).toHaveAttribute('title', 'Halaman utama pelanggan');

        expect(
            screen.getByRole('button', { name: 'Permintaan' }),
        ).toHaveAttribute('title', 'Senarai Open Request anda');

        expect(
            screen.getByRole('button', { name: 'Cipta' }),
        ).toHaveAttribute('title', 'Cipta Open Request baharu');
    });
});
