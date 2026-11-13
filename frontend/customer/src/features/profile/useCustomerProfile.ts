
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
    pictureUploading: boolean;
    pictureError: string;
    pictureMessage: string;
};

type CustomerProfileActions = {
    setProfileName: (value: string) => void;
    updateProfile: () => Promise<boolean>;
    uploadProfilePicture: (file: File) => Promise<boolean>;
    clearMessages: () => void;
};

export type CustomerProfile = CustomerProfileState &
    CustomerProfileActions;

type UseCustomerProfileOptions = {
    account: CustomerAccount | null;
    onAccountUpdated: (account: CustomerAccount) => void;
};

const ALLOWED_PROFILE_PICTURE_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

const MAX_PROFILE_PICTURE_SIZE = 5 * 1024 * 1024;

export function useCustomerProfile({
    account,
    onAccountUpdated,
}: UseCustomerProfileOptions): CustomerProfile {
    const [profileName, setProfileNameState] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    const [pictureUploading, setPictureUploading] =
        useState(false);
    const [pictureError, setPictureError] = useState('');
    const [pictureMessage, setPictureMessage] = useState('');

    useEffect(() => {
        setProfileNameState(account?.profile_name ?? '');
    }, [account?.id, account?.profile_name]);

    const setProfileName = useCallback((value: string) => {
        setProfileNameState(value);
        setError('');
        setMessage('');
    }, []);

    const clearMessages = useCallback(() => {
        setError('');
        setMessage('');
        setPictureError('');
        setPictureMessage('');
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
                await customerApi.updateProfile({
                    profile_name: name,
                });

            setProfileNameState(updatedAccount.profile_name);
            setMessage('Profil berjaya dikemaskini.');

            onAccountUpdated(updatedAccount);

            return true;
        } catch (requestError) {
            if (axios.isAxiosError(requestError)) {
                const validationMessage =
                    requestError.response?.data?.errors
                        ?.profile_name?.[0];

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

    const uploadProfilePicture = useCallback(
        async (file: File): Promise<boolean> => {
            if (!account || pictureUploading) {
                return false;
            }

            setPictureError('');
            setPictureMessage('');

            if (
                !ALLOWED_PROFILE_PICTURE_TYPES.includes(file.type)
            ) {
                setPictureError(
                    'Format gambar mesti JPG, PNG atau WebP.',
                );
                return false;
            }

            if (file.size > MAX_PROFILE_PICTURE_SIZE) {
                setPictureError(
                    'Saiz gambar tidak boleh melebihi 5 MB.',
                );
                return false;
            }

            setPictureUploading(true);

            try {
                const updatedAccount =
                    await customerApi.uploadProfilePicture(file);

                onAccountUpdated(updatedAccount);

                setPictureMessage(
                    'Gambar profil berjaya dikemaskini.',
                );

                return true;
            } catch (requestError) {
                if (axios.isAxiosError(requestError)) {
                    const validationMessage =
                        requestError.response?.data?.errors
                            ?.profile_picture?.[0];

                    setPictureError(
                        validationMessage ??
                            requestError.response?.data?.message ??
                            'Tidak berjaya memuat naik gambar profil.',
                    );
                } else {
                    setPictureError(
                        'Tidak berjaya memuat naik gambar profil.',
                    );
                }

                return false;
            } finally {
                setPictureUploading(false);
            }
        },
        [
            account,
            pictureUploading,
            onAccountUpdated,
        ],
    );

    return {
        profileName,
        saving,
        error,
        message,
        pictureUploading,
        pictureError,
        pictureMessage,
        setProfileName,
        updateProfile,
        uploadProfilePicture,
        clearMessages,
    };
}
