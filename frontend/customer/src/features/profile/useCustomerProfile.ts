
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
    type ChangePhonePayload,
    type ChangeEmailPayload,
    type ContactChangeVerifyPayload,
    type ContactChangeRequestResult,
    type ContactChangeVerifyResult,
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

    contactSaving: boolean;
    contactError: string;
    contactMessage: string;
    phoneChangeVerification: ContactChangeRequestResult | null;
    emailChangeVerification: ContactChangeRequestResult | null;
    phoneChangeToken: ContactChangeVerifyResult | null;
    emailChangeToken: ContactChangeVerifyResult | null;
};

type CustomerProfileActions = {
    setProfileName: (value: string) => void;
    updateProfile: () => Promise<boolean>;
    uploadProfilePicture: (file: File) => Promise<boolean>;
    changePassword: (payload: ChangePasswordPayload) => Promise<boolean>;

    requestPhoneChange: (payload: ChangePhonePayload) => Promise<boolean>;
    verifyPhoneChange: (payload: ContactChangeVerifyPayload) => Promise<boolean>;
    completePhoneChange: () => Promise<boolean>;

    requestEmailChange: (payload: ChangeEmailPayload) => Promise<boolean>;
    verifyEmailChange: (payload: ContactChangeVerifyPayload) => Promise<boolean>;
    completeEmailChange: () => Promise<boolean>;

    clearContactChange: () => void;
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

function getRequestError(
    requestError: unknown,
    fallback: string,
    field?: string,
): string {
    if (!axios.isAxiosError(requestError)) {
        return fallback;
    }

    const validationMessage = field
        ? requestError.response?.data?.errors?.[field]?.[0]
        : undefined;

    return (
        validationMessage ??
        requestError.response?.data?.message ??
        fallback
    );
}

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

    const [contactSaving, setContactSaving] = useState(false);
    const [contactError, setContactError] = useState('');
    const [contactMessage, setContactMessage] = useState('');

    const [phoneChangeVerification, setPhoneChangeVerification] =
        useState<ContactChangeRequestResult | null>(null);
    const [emailChangeVerification, setEmailChangeVerification] =
        useState<ContactChangeRequestResult | null>(null);

    const [phoneChangeToken, setPhoneChangeToken] =
        useState<ContactChangeVerifyResult | null>(null);
    const [emailChangeToken, setEmailChangeToken] =
        useState<ContactChangeVerifyResult | null>(null);

    useEffect(() => {
        setProfileNameState(account?.profile_name ?? '');
    }, [account?.id, account?.profile_name]);

    const setProfileName = useCallback((value: string) => {
        setProfileNameState(value);
        setError('');
        setMessage('');
    }, []);

    const clearContactChange = useCallback(() => {
        setContactError('');
        setContactMessage('');
        setPhoneChangeVerification(null);
        setEmailChangeVerification(null);
        setPhoneChangeToken(null);
        setEmailChangeToken(null);
    }, []);

    const clearMessages = useCallback(() => {
        setError('');
        setMessage('');
        setPictureError('');
        setPictureMessage('');
        setPasswordError('');
        setPasswordMessage('');
        setContactError('');
        setContactMessage('');
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
            setError(
                getRequestError(
                    requestError,
                    'Tidak berjaya mengemaskini profil.',
                    'profile_name',
                ),
            );

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

            if (!ALLOWED_PROFILE_PICTURE_TYPES.includes(file.type)) {
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
                setPictureError(
                    getRequestError(
                        requestError,
                        'Tidak berjaya memuat naik gambar profil.',
                        'profile_picture',
                    ),
                );

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
                setPasswordError(
                    getRequestError(
                        requestError,
                        'Tidak berjaya menukar kata laluan. Sila cuba semula.',
                        'current_password',
                    ),
                );

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

    const requestPhoneChange = useCallback(
        async (payload: ChangePhonePayload): Promise<boolean> => {
            if (!account || contactSaving) {
                return false;
            }

            setContactSaving(true);
            setContactError('');
            setContactMessage('');
            setPhoneChangeVerification(null);
            setPhoneChangeToken(null);

            try {
                const result =
                    await customerApi.requestPhoneChangeOtp(payload);

                setPhoneChangeVerification(result);
                setContactMessage(
                    'Kod pengesahan telah dihantar ke nombor telefon baharu.',
                );

                return true;
            } catch (requestError) {
                setContactError(
                    getRequestError(
                        requestError,
                        'Tidak berjaya menghantar kod pengesahan.',
                        'phone',
                    ),
                );

                return false;
            } finally {
                setContactSaving(false);
            }
        },
        [account, contactSaving],
    );

    const verifyPhoneChange = useCallback(
        async (
            payload: ContactChangeVerifyPayload,
        ): Promise<boolean> => {
            if (!account || contactSaving) {
                return false;
            }

            setContactSaving(true);
            setContactError('');
            setContactMessage('');

            try {
                const result =
                    await customerApi.verifyPhoneChangeOtp(payload);

                setPhoneChangeToken(result);
                setContactMessage(
                    'Kod pengesahan nombor telefon berjaya disahkan.',
                );

                return true;
            } catch (requestError) {
                setContactError(
                    getRequestError(
                        requestError,
                        'Kod pengesahan tidak sah atau telah tamat tempoh.',
                        'otp',
                    ),
                );

                return false;
            } finally {
                setContactSaving(false);
            }
        },
        [account, contactSaving],
    );

    const completePhoneChange = useCallback(
        async (): Promise<boolean> => {
            if (
                !account ||
                contactSaving ||
                !phoneChangeToken
            ) {
                return false;
            }

            setContactSaving(true);
            setContactError('');
            setContactMessage('');

            try {
                const updatedAccount =
                    await customerApi.completePhoneChange({
                        verification_token:
                            phoneChangeToken.verification_token,
                    });

                onAccountUpdated(updatedAccount);
                setPhoneChangeVerification(null);
                setPhoneChangeToken(null);
                setContactMessage(
                    'Nombor telefon berjaya dikemaskini.',
                );

                return true;
            } catch (requestError) {
                setContactError(
                    getRequestError(
                        requestError,
                        'Tidak berjaya mengemaskini nombor telefon.',
                        'verification_token',
                    ),
                );

                return false;
            } finally {
                setContactSaving(false);
            }
        },
        [
            account,
            contactSaving,
            phoneChangeToken,
            onAccountUpdated,
        ],
    );

    const requestEmailChange = useCallback(
        async (payload: ChangeEmailPayload): Promise<boolean> => {
            if (!account || contactSaving) {
                return false;
            }

            setContactSaving(true);
            setContactError('');
            setContactMessage('');
            setEmailChangeVerification(null);
            setEmailChangeToken(null);

            try {
                const result =
                    await customerApi.requestEmailChangeOtp(payload);

                setEmailChangeVerification(result);
                setContactMessage(
                    'Kod pengesahan telah dihantar ke e-mel baharu.',
                );

                return true;
            } catch (requestError) {
                setContactError(
                    getRequestError(
                        requestError,
                        'Tidak berjaya menghantar kod pengesahan.',
                        'email',
                    ),
                );

                return false;
            } finally {
                setContactSaving(false);
            }
        },
        [account, contactSaving],
    );

    const verifyEmailChange = useCallback(
        async (
            payload: ContactChangeVerifyPayload,
        ): Promise<boolean> => {
            if (!account || contactSaving) {
                return false;
            }

            setContactSaving(true);
            setContactError('');
            setContactMessage('');

            try {
                const result =
                    await customerApi.verifyEmailChangeOtp(payload);

                setEmailChangeToken(result);
                setContactMessage(
                    'Kod pengesahan e-mel berjaya disahkan.',
                );

                return true;
            } catch (requestError) {
                setContactError(
                    getRequestError(
                        requestError,
                        'Kod pengesahan tidak sah atau telah tamat tempoh.',
                        'otp',
                    ),
                );

                return false;
            } finally {
                setContactSaving(false);
            }
        },
        [account, contactSaving],
    );

    const completeEmailChange = useCallback(
        async (): Promise<boolean> => {
            if (
                !account ||
                contactSaving ||
                !emailChangeToken
            ) {
                return false;
            }

            setContactSaving(true);
            setContactError('');
            setContactMessage('');

            try {
                const updatedAccount =
                    await customerApi.completeEmailChange({
                        verification_token:
                            emailChangeToken.verification_token,
                    });

                onAccountUpdated(updatedAccount);
                setEmailChangeVerification(null);
                setEmailChangeToken(null);
                setContactMessage(
                    'E-mel berjaya dikemaskini.',
                );

                return true;
            } catch (requestError) {
                setContactError(
                    getRequestError(
                        requestError,
                        'Tidak berjaya mengemaskini e-mel.',
                        'verification_token',
                    ),
                );

                return false;
            } finally {
                setContactSaving(false);
            }
        },
        [
            account,
            contactSaving,
            emailChangeToken,
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
        passwordSaving,
        passwordError,
        passwordMessage,
        contactSaving,
        contactError,
        contactMessage,
        phoneChangeVerification,
        emailChangeVerification,
        phoneChangeToken,
        emailChangeToken,
        setProfileName,
        updateProfile,
        uploadProfilePicture,
        changePassword,
        requestPhoneChange,
        verifyPhoneChange,
        completePhoneChange,
        requestEmailChange,
        verifyEmailChange,
        completeEmailChange,
        clearContactChange,
        clearMessages,
    };
}
