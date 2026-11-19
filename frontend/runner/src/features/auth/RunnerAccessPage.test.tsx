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

import RunnerAccessPage, {
    type RunnerAccessReason,
} from './RunnerAccessPage';

import type {
    RunnerAccount,
} from '../../api';

const mockAccount: RunnerAccount = {
    id: 1,
    profile_name: 'Runner Test',
    phone: '0123456789',
    email: 'runner@example.com',
    profile_picture: null,
    role: 'USER',
    status: 'ACTIVE',
    runner_status: 'PENDING',
    identity_verification_status: 'VERIFIED',
};

describe('RunnerAccessPage', () => {
    const logout = vi.fn<() => Promise<boolean>>();

    beforeEach(() => {
        vi.clearAllMocks();
        logout.mockResolvedValue(true);
    });

    function renderPage(reason: RunnerAccessReason) {
        return render(
            <RunnerAccessPage
                account={mockAccount}
                reason={reason}
                logout={logout}
                logoutLoading={false}
                logoutError=""
            />,
        );
    }

    it('shows pending Runner application information', () => {
        renderPage('RUNNER_PENDING');

        expect(
            screen.getByRole('heading', {
                name: 'Permohonan Dalam Semakan',
            }),
        ).toBeInTheDocument();

        expect(
            screen.getByText('Runner Test'),
        ).toBeInTheDocument();
    });

    it('shows rejected Runner application information', () => {
        renderPage('RUNNER_REJECTED');

        expect(
            screen.getByRole('heading', {
                name: 'Permohonan Ditolak',
            }),
        ).toBeInTheDocument();
    });

    it('shows suspended Runner information', () => {
        renderPage('RUNNER_SUSPENDED');

        expect(
            screen.getByRole('heading', {
                name: 'Status Runner Digantung',
            }),
        ).toBeInTheDocument();
    });

    it('shows account inactive information', () => {
        renderPage('ACCOUNT_INACTIVE');

        expect(
            screen.getByRole('heading', {
                name: 'Akaun Tidak Aktif',
            }),
        ).toBeInTheDocument();
    });

    it('does not display private identity information', () => {
        renderPage('RUNNER_PENDING');

        expect(
            screen.queryByText(/mykad/i),
        ).not.toBeInTheDocument();

        expect(
            screen.queryByText(/verified name/i),
        ).not.toBeInTheDocument();
    });

    it('calls logout when the logout button is pressed', async () => {
        renderPage('RUNNER_PENDING');

        fireEvent.click(
            screen.getByRole('button', {
                name: 'LOG KELUAR',
            }),
        );

        await waitFor(() => {
            expect(logout).toHaveBeenCalledTimes(1);
        });
    });

    it('disables logout while logout is loading', () => {
        render(
            <RunnerAccessPage
                account={mockAccount}
                reason="RUNNER_PENDING"
                logout={logout}
                logoutLoading={true}
                logoutError=""
            />,
        );

        expect(
            screen.getByRole('button', {
                name: 'Sedang log keluar...',
            }),
        ).toBeDisabled();
    });

    it('displays logout errors', () => {
        render(
            <RunnerAccessPage
                account={mockAccount}
                reason="RUNNER_PENDING"
                logout={logout}
                logoutLoading={false}
                logoutError="Tidak berjaya log keluar. Sila cuba semula."
            />,
        );

        expect(
            screen.getByRole('alert'),
        ).toHaveTextContent(
            'Tidak berjaya log keluar. Sila cuba semula.',
        );
    });
});
