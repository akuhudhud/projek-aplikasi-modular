
import {
    act,
    renderHook,
    waitFor,
} from '@testing-library/react';

import {
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import {
    RUNNER_SESSION_EXPIRED_EVENT,
    runnerApi,
    type RunnerAccount,
} from '../../../src/api';

vi.mock('../../../src/api', () => ({
    RUNNER_SESSION_EXPIRED_EVENT: 'runner-session-expired',
    runnerApi: {
        me: vi.fn(),
        logout: vi.fn(),
    },
}));

import {
    useRunnerSession,
} from '../../../src/features/auth/useRunnerSession';

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

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;

    const promise = new Promise<T>((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

describe('useRunnerSession restoration recovery', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        vi.mocked(runnerApi.me).mockResolvedValue(account);
        vi.mocked(runnerApi.logout).mockResolvedValue(undefined);
    });

    it('shows a recoverable error when session restoration fails', async () => {
        vi.mocked(runnerApi.me).mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() => useRunnerSession());

        await waitFor(() => {
            expect(result.current.checkingSession).toBe(false);
        });

        expect(result.current.account).toBeNull();
        expect(result.current.restoreSessionError).toContain(
            'Tidak dapat menghubungi server.',
        );
    });

    it('retries session restoration and recovers the account', async () => {
        vi.mocked(runnerApi.me)
            .mockRejectedValueOnce(new Error('Network error'))
            .mockResolvedValueOnce(account);

        const { result } = renderHook(() => useRunnerSession());

        await waitFor(() => {
            expect(result.current.restoreSessionError).not.toBe('');
        });

        act(() => {
            result.current.retrySession();
        });

        await waitFor(() => {
            expect(result.current.account).toEqual(account);
        });

        expect(result.current.restoreSessionError).toBe('');
        expect(result.current.checkingSession).toBe(false);
        expect(runnerApi.me).toHaveBeenCalledTimes(2);
    });

    it('keeps the latest restoration result when requests finish out of order', async () => {
        const first = deferred<RunnerAccount>();
        const second = deferred<RunnerAccount>();

        vi.mocked(runnerApi.me)
            .mockReturnValueOnce(first.promise)
            .mockReturnValueOnce(second.promise);

        const { result } = renderHook(() => useRunnerSession());

        await waitFor(() => {
            expect(runnerApi.me).toHaveBeenCalledTimes(1);
        });

        act(() => {
            result.current.retrySession();
        });

        await waitFor(() => {
            expect(runnerApi.me).toHaveBeenCalledTimes(2);
        });

        await act(async () => {
            second.resolve(account);
            await second.promise;
        });

        expect(result.current.account).toEqual(account);

        await act(async () => {
            first.reject(new Error('Old request failed'));
            await first.promise.catch(() => undefined);
        });

        expect(result.current.account).toEqual(account);
        expect(result.current.restoreSessionError).toBe('');
    });

    it('does not restore an account after session expiration', async () => {
        const pending = deferred<RunnerAccount>();

        vi.mocked(runnerApi.me).mockReturnValueOnce(pending.promise);

        const { result } = renderHook(() => useRunnerSession());

        act(() => {
            window.dispatchEvent(
                new Event(RUNNER_SESSION_EXPIRED_EVENT),
            );
        });

        await act(async () => {
            pending.resolve(account);
            await pending.promise;
        });

        expect(result.current.account).toBeNull();
        expect(result.current.checkingSession).toBe(false);
        expect(result.current.restoreSessionError).toBe('');
    });

    it('does not allow an old restoration to override manual login', async () => {
        const pending = deferred<RunnerAccount>();

        vi.mocked(runnerApi.me).mockReturnValueOnce(pending.promise);

        const { result } = renderHook(() => useRunnerSession());

        act(() => {
            result.current.setAccount(account);
        });

        await act(async () => {
            pending.reject(new Error('Old request failed'));
            await pending.promise.catch(() => undefined);
        });

        expect(result.current.account).toEqual(account);
        expect(result.current.restoreSessionError).toBe('');
    });
});
