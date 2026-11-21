
import {
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';

import {
    RUNNER_SESSION_EXPIRED_EVENT,
    runnerApi,
    type RunnerAccount,
} from '../../api';

type RunnerSessionState = {
    account: RunnerAccount | null;
    checkingSession: boolean;
    logoutLoading: boolean;
    logoutError: string;
};

type RunnerSessionActions = {
    setAccount: (account: RunnerAccount | null) => void;
    logout: () => Promise<boolean>;
    clearLogoutError: () => void;
};

export type RunnerSession = RunnerSessionState &
    RunnerSessionActions;

export function useRunnerSession(): RunnerSession {
    const [account, setAccount] =
        useState<RunnerAccount | null>(null);

    const [checkingSession, setCheckingSession] =
        useState(true);

    const [logoutLoading, setLogoutLoading] =
        useState(false);

    const [logoutError, setLogoutError] =
        useState('');

    // Invalidates outdated session restoration responses.
    const sessionRequestId = useRef(0);

    useEffect(() => {
        let mounted = true;
        const requestId = ++sessionRequestId.current;

        async function restoreSession() {
            try {
                const currentAccount = await runnerApi.me();

                if (
                    mounted
                    && requestId === sessionRequestId.current
                ) {
                    setAccount(currentAccount);
                }
            } catch {
                if (
                    mounted
                    && requestId === sessionRequestId.current
                ) {
                    setAccount(null);
                }
            } finally {
                if (
                    mounted
                    && requestId === sessionRequestId.current
                ) {
                    setCheckingSession(false);
                }
            }
        }

        void restoreSession();

        return () => {
            mounted = false;
            sessionRequestId.current += 1;
        };
    }, []);

    useEffect(() => {
        function handleSessionExpired() {
            // Prevent an earlier me() response from restoring
            // an account after the session has expired.
            sessionRequestId.current += 1;

            setAccount(null);
            setCheckingSession(false);
            setLogoutError('');
            setLogoutLoading(false);
        }

        window.addEventListener(
            RUNNER_SESSION_EXPIRED_EVENT,
            handleSessionExpired,
        );

        return () => {
            window.removeEventListener(
                RUNNER_SESSION_EXPIRED_EVENT,
                handleSessionExpired,
            );
        };
    }, []);

    const updateAccount = useCallback(
        (nextAccount: RunnerAccount | null) => {
            // Manual account changes supersede pending restoration.
            sessionRequestId.current += 1;

            setAccount(nextAccount);
            setCheckingSession(false);
            setLogoutError('');
        },
        [],
    );

    const logout = useCallback(async (): Promise<boolean> => {
        if (logoutLoading) {
            return false;
        }

        // Invalidate pending restoration before logging out.
        sessionRequestId.current += 1;

        setLogoutLoading(true);
        setLogoutError('');

        try {
            await runnerApi.logout();

            setAccount(null);

            return true;
        } catch {
            setLogoutError(
                'Tidak berjaya log keluar. Sila cuba semula.',
            );

            return false;
        } finally {
            setLogoutLoading(false);
        }
    }, [logoutLoading]);

    const clearLogoutError = useCallback(() => {
        setLogoutError('');
    }, []);

    return {
        account,
        checkingSession,
        logoutLoading,
        logoutError,
        setAccount: updateAccount,
        logout,
        clearLogoutError,
    };
}
