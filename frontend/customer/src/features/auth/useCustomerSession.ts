
import { useCallback, useEffect, useState } from 'react';

import {
    CUSTOMER_SESSION_EXPIRED_EVENT,
    customerApi,
    type CustomerAccount,
} from '../../api';

type CustomerSessionState = {
    account: CustomerAccount | null;
    checkingSession: boolean;
    logoutLoading: boolean;
    logoutError: string;
};

type CustomerSessionActions = {
    setAccount: (account: CustomerAccount | null) => void;
    logout: () => Promise<boolean>;
    clearLogoutError: () => void;
};

export type CustomerSession = CustomerSessionState &
    CustomerSessionActions;

export function useCustomerSession(): CustomerSession {
    const [account, setAccount] =
        useState<CustomerAccount | null>(null);

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
                const currentAccount = await customerApi.me();

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
        if (typeof window === 'undefined') {
            return;
        }

        function handleSessionExpired() {
            setAccount(null);
            setLogoutError('');
            setLogoutLoading(false);
        }

        window.addEventListener(
            CUSTOMER_SESSION_EXPIRED_EVENT,
            handleSessionExpired,
        );

        return () => {
            window.removeEventListener(
                CUSTOMER_SESSION_EXPIRED_EVENT,
                handleSessionExpired,
            );
        };
    }, []);

    const updateAccount = useCallback(
        (nextAccount: CustomerAccount | null) => {
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
            await customerApi.logout();

            setAccount(null);

            return true;
        } catch {
            setLogoutError(
                'Tidak berjaya logout. Sila cuba semula.',
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
