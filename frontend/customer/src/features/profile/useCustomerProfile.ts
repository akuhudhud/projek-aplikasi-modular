
import {
    useCallback,
    useEffect,
    useState,
} from 'react';

import axios from 'axios';

import {
    customerApi,
    type CustomerAccount,
} from '../../api';

type CustomerProfileState = {
    profileName: string;
    saving: boolean;
    error: string;
    message: string;
};

type CustomerProfileActions = {
    setProfileName: (value: string) => void;
    updateProfile: () => Promise<boolean>;
    clearMessages: () => void;
};

export type CustomerProfile = CustomerProfileState &
    CustomerProfileActions;

type UseCustomerProfileOptions = {
    account: CustomerAccount | null;
    onAccountUpdated: (account: CustomerAccount) => void;
};

export function useCustomerProfile({
    account,
    onAccountUpdated,
}: UseCustomerProfileOptions): CustomerProfile {
    const [profileName, setProfileNameState] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    useEffect(() => {
        setProfileName(account?.name ?? '');
    }, [account?.id, account?.name]);

    const setProfileName = useCallback((value: string) => {
        setProfileNameState(value);
        setError('');
        setMessage('');
    }, []);

    const clearMessages = useCallback(() => {
        setError('');
        setMessage('');
    }, []);

    const updateProfile = useCallback(async (): Promise<boolean> => {
        if (!account || saving) {
            return false;
        }

        const name = profileName.trim();

        if (!name) {
            setError('Nama tidak boleh kosong.');
            setMessage('');
            return false;
        }

        setSaving(true);
        setError('');
        setMessage('');

        try {
            const updatedAccount =
                await customerApi.updateProfile({ name });

            setProfileNameState(updatedAccount.name);
            setMessage('Profil berjaya dikemaskini.');

            onAccountUpdated(updatedAccount);

            return true;
        } catch (requestError) {
            if (axios.isAxiosError(requestError)) {
                const validationMessage =
                    requestError.response?.data?.errors?.name?.[0];

                setError(
                    validationMessage ??
                        requestError.response?.data?.message ??
                        'Tidak berjaya mengemaskini profil.',
                );
            } else {
                setError(
                    'Tidak berjaya mengemaskini profil.',
                );
            }

            return false;
        } finally {
            setSaving(false);
        }
    }, [
        account,
        saving,
        profileName,
        onAccountUpdated,
    ]);

    return {
        profileName,
        saving,
        error,
        message,
        setProfileName,
        updateProfile,
        clearMessages,
    };
}
