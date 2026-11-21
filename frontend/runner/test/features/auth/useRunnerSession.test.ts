
import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    RUNNER_SESSION_EXPIRED_EVENT,
    runnerApi,
    type RunnerAccount,
} from '../../../src/api';

import { useRunnerSession } from '../../../src/features/auth/useRunnerSession';

vi.mock('../../../src/api', () => ({
    RUNNER_SESSION_EXPIRED_EVENT: 'runner-session-expired',
    runnerApi: {
        me: vi.fn(),
        logout: vi.fn(),
    },
}));

const account: RunnerAccount = {
    id: 1,
    profile_name: 'Runner Tuaran',
    phone: '0123456789',
    email: 'runner@example.com',
    profile_picture: null,
    role: 'USER',
    status: 'ACTIVE',
    runner_status: 'APPROVED',
    identity_verification_status: 'VERIFIED',
};

describe('useRunnerSession', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        vi.mocked(runnerApi.me).mockResolvedValue(account);
        vi.mocked(runnerApi.logout).mockResolvedValue(undefined);
    });

    it('restores the authenticated account when the hook mounts', async () => {
        const { result } = renderHook(() => useRunnerSession());

        expect(result.current.checkingSession).toBe(true);

        await waitFor(() => {
            expect(result.current.checkingSession).toBe(false);
        });

        expect(runnerApi.me).toHaveBeenCalledOnce();
        expect(result.current.account).toEqual(account);
    });

    it('sets account to null when session restoration fails', async () => {
        vi.mocked(runnerApi.me).mockRejectedValue(
            new Error('Unauthenticated'),
        );

        const { result } = renderHook(() => useRunnerSession());

        await waitFor(() => {
            expect(result.current.checkingSession).toBe(false);
        });

        expect(result.current.account).toBeNull();
    });

    it('does not update state after the hook is unmounted', async () => {
        let resolveSession!: (value: RunnerAccount) => void;

        vi.mocked(runnerApi.me).mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolveSession = resolve;
                }),
        );

        const { unmount } = renderHook(() => useRunnerSession());

        unmount();

        resolveSession(account);

        await Promise.resolve();

        expect(runnerApi.me).toHaveBeenCalledOnce();
    });

    it('does not restore an account after the session-expired event', async () => {
        let resolveSession!: (value: RunnerAccount) => void;

        vi.mocked(runnerApi.me).mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolveSession = resolve;
                }),
        );

        const { result } = renderHook(() => useRunnerSession());

        act(() => {
            window.dispatchEvent(
                new Event(RUNNER_SESSION_EXPIRED_EVENT),
            );
        });

        await act(async () => {
            resolveSession(account);
            await Promise.resolve();
        });

        expect(result.current.account).toBeNull();
        expect(result.current.checkingSession).toBe(false);
    });

    it('does not restore an account after manual account update', async () => {
        let resolveSession!: (value: RunnerAccount) => void;

        vi.mocked(runnerApi.me).mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolveSession = resolve;
                }),
        );

        const { result } = renderHook(() => useRunnerSession());

        act(() => {
            result.current.setAccount(null);
        });

        await act(async () => {
            resolveSession(account);
            await Promise.resolve();
        });

        expect(result.current.account).toBeNull();
        expect(result.current.checkingSession).toBe(false);
    });

    it('updates the account and clears logout errors', async () => {
        const { result } = renderHook(() => useRunnerSession());

        await waitFor(() => {
            expect(result.current.checkingSession).toBe(false);
        });

        act(() => {
            result.current.setAccount(null);
        });

        expect(result.current.account).toBeNull();
        expect(result.current.logoutError).toBe('');

        act(() => {
            result.current.setAccount(account);
        });

        expect(result.current.account).toEqual(account);
    });

    it('logs out successfully and clears the account', async () => {
        const { result } = renderHook(() => useRunnerSession());

        await waitFor(() => {
            expect(result.current.account).toEqual(account);
        });

        let logoutResult = false;

        await act(async () => {
            logoutResult = await result.current.logout();
        });

        expect(runnerApi.logout).toHaveBeenCalledOnce();
        expect(logoutResult).toBe(true);
        expect(result.current.account).toBeNull();
        expect(result.current.logoutError).toBe('');
        expect(result.current.logoutLoading).toBe(false);
    });

    it('shows an error when logout fails', async () => {
        vi.mocked(runnerApi.logout).mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() => useRunnerSession());

        await waitFor(() => {
            expect(result.current.account).toEqual(account);
        });

        let logoutResult = true;

        await act(async () => {
            logoutResult = await result.current.logout();
        });

        expect(logoutResult).toBe(false);
        expect(result.current.account).toEqual(account);
        expect(result.current.logoutError).toBe(
            'Tidak berjaya log keluar. Sila cuba semula.',
        );
        expect(result.current.logoutLoading).toBe(false);
    });

    it('clears the logout error when requested', async () => {
        vi.mocked(runnerApi.logout).mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() => useRunnerSession());

        await waitFor(() => {
            expect(result.current.checkingSession).toBe(false);
        });

        await act(async () => {
            await result.current.logout();
        });

        expect(result.current.logoutError).not.toBe('');

        act(() => {
            result.current.clearLogoutError();
        });

        expect(result.current.logoutError).toBe('');
    });

    it('clears the account when the session-expired event is dispatched', async () => {
        const { result } = renderHook(() => useRunnerSession());

        await waitFor(() => {
            expect(result.current.account).toEqual(account);
        });

        act(() => {
            window.dispatchEvent(
                new Event(RUNNER_SESSION_EXPIRED_EVENT),
            );
        });

        expect(result.current.account).toBeNull();
        expect(result.current.logoutError).toBe('');
        expect(result.current.logoutLoading).toBe(false);
    });
});
