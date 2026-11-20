
import {
    afterEach,
    describe,
    expect,
    it,
} from 'vitest';

import {
    cleanup,
    fireEvent,
    render,
    screen,
} from '@testing-library/react';

import ConnectionStatus from '../../../src/features/connectivity/ConnectionStatus';

describe('ConnectionStatus', () => {
    afterEach(() => {
        cleanup();
    });

    it('tidak memaparkan amaran apabila internet tersedia', () => {
        Object.defineProperty(navigator, 'onLine', {
            configurable: true,
            value: true,
        });

        render(<ConnectionStatus />);

        expect(
            screen.queryByRole('status'),
        ).not.toBeInTheDocument();
    });

    it('memaparkan amaran apabila internet terputus', () => {
        Object.defineProperty(navigator, 'onLine', {
            configurable: true,
            value: false,
        });

        render(<ConnectionStatus />);

        expect(
            screen.getByRole('status'),
        ).toHaveTextContent(
            'Tiada sambungan internet.',
        );
    });

    it('menyembunyikan amaran apabila internet pulih', () => {
        Object.defineProperty(navigator, 'onLine', {
            configurable: true,
            value: false,
        });

        render(<ConnectionStatus />);

        expect(
            screen.getByRole('status'),
        ).toBeInTheDocument();

        fireEvent(window, new Event('online'));

        expect(
            screen.queryByRole('status'),
        ).not.toBeInTheDocument();
    });

    it('memaparkan amaran apabila sambungan terputus semasa aplikasi dibuka', () => {
        Object.defineProperty(navigator, 'onLine', {
            configurable: true,
            value: true,
        });

        render(<ConnectionStatus />);

        expect(
            screen.queryByRole('status'),
        ).not.toBeInTheDocument();

        fireEvent(window, new Event('offline'));

        expect(
            screen.getByRole('status'),
        ).toBeInTheDocument();
    });
});
