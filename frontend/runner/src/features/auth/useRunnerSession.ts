
import { useCallback, useEffect, useState } from 'react';

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

    useEffect(() => {
        let mounted = true;

        async function restoreSession() {
            try {
                const currentAccount = await runnerApi.me();

                if (mounted) {
                    setAccount(currentAccount);
                }
            } catch {
                if (mounted) {
                    setAccount(null);
                }
            } finally {
                if (mounted) {
                    setCheckingSession(false);
                }
            }
        }

        void restoreSession();

        return () => {
            mounted = false;
        };
    }, []);

    useEffect(() => {
        function handleSessionExpired() {
            setAccount(null);
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
            setAccount(nextAccount);
            setLogoutError('');
        },
        [],
    );

    const logout = useCallback(async (): Promise<boolean> => {
        if (logoutLoading) {
            return false;
        }

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
