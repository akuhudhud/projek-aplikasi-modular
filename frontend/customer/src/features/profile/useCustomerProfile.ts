
import {
    useCallback,
    useEffect,
    useState,
} from 'react';

import axios from 'axios';

import {
    customerApi,
    type CustomerAccount,
    type ChangePasswordPayload,
} from '../../api';

type CustomerProfileState = {
    profileName: string;
    saving: boolean;
    error: string;
    message: string;
    pictureUploading: boolean;
    pictureError: string;
    pictureMessage: string;
    passwordSaving: boolean;
    passwordError: string;
    passwordMessage: string;
};

type CustomerProfileActions = {
    setProfileName: (value: string) => void;
    updateProfile: () => Promise<boolean>;
    uploadProfilePicture: (file: File) => Promise<boolean>;
    changePassword: (payload: ChangePasswordPayload) => Promise<boolean>;
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

    const [passwordSaving, setPasswordSaving] = useState(false);
    const [passwordError, setPasswordError] = useState('');
    const [passwordMessage, setPasswordMessage] = useState('');

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
        setPasswordError('');
        setPasswordMessage('');
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

    const changePassword = useCallback(
        async (
            payload: ChangePasswordPayload,
        ): Promise<boolean> => {
            if (!account || passwordSaving) {
                return false;
            }

            setPasswordError('');
            setPasswordMessage('');

            if (
                payload.new_password !==
                payload.new_password_confirmation
            ) {
                setPasswordError(
                    'Pengesahan kata laluan baharu tidak sepadan.',
                );
                return false;
            }

            if (
                payload.new_password.length < 8 ||
                payload.new_password.length > 12
            ) {
                setPasswordError(
                    'Kata laluan baharu mesti antara 8 hingga 12 aksara.',
                );
                return false;
            }

            setPasswordSaving(true);

            try {
                await customerApi.changePassword(payload);

                setPasswordMessage(
                    'Kata laluan berjaya ditukar. Sila log masuk semula.',
                );

                return true;
            } catch (requestError) {
                if (axios.isAxiosError(requestError)) {
                    const validationErrors =
                        requestError.response?.data?.errors;

                    const validationMessage =
                        validationErrors?.current_password?.[0] ??
                        validationErrors?.new_password?.[0] ??
                        validationErrors?.new_password_confirmation?.[0];

                    setPasswordError(
                        validationMessage ??
                            requestError.response?.data?.message ??
                            'Tidak berjaya menukar kata laluan. Sila cuba semula.',
                    );
                } else {
                    setPasswordError(
                        'Tidak berjaya menukar kata laluan. Sila cuba semula.',
                    );
                }

                return false;
            } finally {
                setPasswordSaving(false);
            }
        },
        [
            account,
            passwordSaving,
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
        passwordSaving,
        passwordError,
        passwordMessage,
        setProfileName,
        updateProfile,
        uploadProfilePicture,
        changePassword,
        clearMessages,
    };
}
