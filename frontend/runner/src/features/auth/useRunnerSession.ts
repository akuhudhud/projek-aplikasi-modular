
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
    restoreSessionError: string;
    logoutLoading: boolean;
    logoutError: string;
};

type RunnerSessionActions = {
    setAccount: (account: RunnerAccount | null) => void;
    retrySession: () => void;
    logout: () => Promise<boolean>;
    clearLogoutError: () => void;
};

export type RunnerSession = RunnerSessionState &
    RunnerSessionActions;

const RESTORE_SESSION_ERROR =
    'Tidak dapat menghubungi server. Periksa sambungan internet dan cuba semula.';

export function useRunnerSession(): RunnerSession {
    const [account, setAccount] =
        useState<RunnerAccount | null>(null);

    const [checkingSession, setCheckingSession] =
        useState(true);

    const [restoreSessionError, setRestoreSessionError] =
        useState('');

    const [logoutLoading, setLogoutLoading] =
        useState(false);

    const [logoutError, setLogoutError] =
        useState('');

    const sessionRequestId = useRef(0);

    const restoreSession = useCallback(async (): Promise<void> => {
        const requestId = ++sessionRequestId.current;

        setCheckingSession(true);
        setRestoreSessionError('');

        try {
            const currentAccount = await runnerApi.me();

            if (requestId !== sessionRequestId.current) {
                return;
            }

            setAccount(currentAccount);
        } catch {
            if (requestId !== sessionRequestId.current) {
                return;
            }

            // A 401 dispatches SESSION_EXPIRED and invalidates
            // this request. Other failures are treated as
            // temporary connectivity/server errors.
            setRestoreSessionError(RESTORE_SESSION_ERROR);
        } finally {
            if (requestId === sessionRequestId.current) {
                setCheckingSession(false);
            }
        }
    }, []);

    useEffect(() => {
        void restoreSession();

        return () => {
            sessionRequestId.current += 1;
        };
    }, [restoreSession]);

    useEffect(() => {
        function handleSessionExpired() {
            sessionRequestId.current += 1;

            setAccount(null);
            setCheckingSession(false);
            setRestoreSessionError('');
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
            sessionRequestId.current += 1;

            setAccount(nextAccount);
            setCheckingSession(false);
            setRestoreSessionError('');
            setLogoutError('');
        },
        [],
    );

    const retrySession = useCallback(() => {
        if (checkingSession) {
            return;
        }

        void restoreSession();
    }, [checkingSession, restoreSession]);

    const logout = useCallback(async (): Promise<boolean> => {
        if (logoutLoading) {
            return false;
        }

        sessionRequestId.current += 1;

        setLogoutLoading(true);
        setLogoutError('');

        try {
            await runnerApi.logout();

            setAccount(null);
            setRestoreSessionError('');

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
        restoreSessionError,
        logoutLoading,
        logoutError,
        setAccount: updateAccount,
        retrySession,
        logout,
        clearLogoutError,
    };
}
