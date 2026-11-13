import React, { useEffect, useState } from 'react';

import type {
    CustomerAccount,
    ChangePasswordPayload,
    ChangePhonePayload,
    ChangeEmailPayload,
    ContactChangeVerifyPayload,
} from '../api';

type ProfilePageProps = {
    account: CustomerAccount;
    profileName: string;
    profileSaving: boolean;
    profileError: string;
    profileMessage: string;
    pictureUploading: boolean;
    pictureError: string;
    pictureMessage: string;
    passwordSaving?: boolean;
    passwordError?: string;
    passwordMessage?: string;
    contactSaving?: boolean;
    contactError?: string;
    contactMessage?: string;
    phoneChangeVerificationId?: string | null;
    phoneChangeVerified?: boolean;
    emailChangeVerificationId?: string | null;
    emailChangeVerified?: boolean;
    accountActionSaving?: boolean;
    accountActionError?: string;
    onNameChange: (value: string) => void;
    onSubmit: (event: React.FormEvent<HTMLFormElement>) => void;
    onPictureSelect: (file: File) => Promise<boolean>;
    onChangePassword?: (payload: ChangePasswordPayload) => Promise<boolean>;
    onRequestPhoneChange?: (payload: ChangePhonePayload) => Promise<boolean>;
    onVerifyPhoneChange?: (payload: ContactChangeVerifyPayload) => Promise<boolean>;
    onCompletePhoneChange?: () => Promise<boolean>;
    onRequestEmailChange?: (payload: ChangeEmailPayload) => Promise<boolean>;
    onVerifyEmailChange?: (payload: ContactChangeVerifyPayload) => Promise<boolean>;
    onCompleteEmailChange?: () => Promise<boolean>;
    onDeactivateAccount?: () => Promise<boolean>;
    onDeleteAccount?: () => Promise<boolean>;
    onReturnToLogin?: () => void;
};

export default function ProfilePage({
    account,
    profileName,
    profileSaving,
    profileError,
    profileMessage,
    pictureUploading,
    pictureError,
    pictureMessage,
    passwordSaving = false,
    passwordError = '',
    passwordMessage = '',
    contactSaving = false,
    contactError = '',
    contactMessage = '',
    phoneChangeVerificationId = null,
    phoneChangeVerified = false,
    emailChangeVerificationId = null,
    emailChangeVerified = false,
    accountActionSaving = false,
    accountActionError = '',
    onNameChange,
    onSubmit,
    onPictureSelect,
    onChangePassword = async () => false,
    onRequestPhoneChange = async () => false,
    onVerifyPhoneChange = async () => false,
    onCompletePhoneChange = async () => false,
    onRequestEmailChange = async () => false,
    onVerifyEmailChange = async () => false,
    onCompleteEmailChange = async () => false,
    onDeactivateAccount = async () => false,
    onDeleteAccount = async () => false,
    onReturnToLogin = () => undefined,
}: ProfilePageProps) {
    const [selectedPicture, setSelectedPicture] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('');
    const [passwordChanged, setPasswordChanged] = useState(false);

    const [newPhone, setNewPhone] = useState('');
    const [phoneOtp, setPhoneOtp] = useState('');
    const [newEmail, setNewEmail] = useState('');
    const [emailOtp, setEmailOtp] = useState('');

    const [phoneChanged, setPhoneChanged] = useState(false);
    const [emailChanged, setEmailChanged] = useState(false);

    const [accountAction, setAccountAction] =
        useState<'deactivate' | 'delete' | null>(null);

    const avatarLetter =
        account.profile_name?.trim().charAt(0).toUpperCase() || '?';

    const profilePictureUrl = account.profile_picture
        ? new URL(
            `/storage/${account.profile_picture.replace(/^\/+/, '')}`,
            new URL(
                import.meta.env.VITE_API_BASE_URL ?? '/',
                window.location.origin,
            ).origin,
        ).toString()
        : null;

    useEffect(() => {
        if (!selectedPicture) {
            setPreviewUrl(null);
            return;
        }

        const url = URL.createObjectURL(selectedPicture);
        setPreviewUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [selectedPicture]);

    function handlePictureChange(
        event: React.ChangeEvent<HTMLInputElement>,
    ) {
        const file = event.target.files?.[0];

        if (file) {
            setSelectedPicture(file);
        }

        event.target.value = '';
    }

    async function handlePictureUpload() {
        if (!selectedPicture || pictureUploading) return;

        const success = await onPictureSelect(selectedPicture);

        if (success) {
            setSelectedPicture(null);
        }
    }

    async function handlePasswordSubmit(
        event: React.FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        if (passwordSaving || passwordChanged) return;

        const success = await onChangePassword({
            current_password: currentPassword,
            new_password: newPassword,
            new_password_confirmation: newPasswordConfirmation,
        });

        if (success) {
            setCurrentPassword('');
            setNewPassword('');
            setNewPasswordConfirmation('');
            setPasswordChanged(true);
        }
    }

    async function handlePhoneRequest(
        event: React.FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        if (contactSaving || !newPhone.trim()) return;

        const success = await onRequestPhoneChange({
            phone: newPhone.trim(),
        });

        if (success) setPhoneOtp('');
    }

    async function handlePhoneVerify(
        event: React.FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        if (
            contactSaving ||
            !phoneChangeVerificationId ||
            phoneOtp.length !== 6
        ) return;

        await onVerifyPhoneChange({
            verification_id: phoneChangeVerificationId,
            otp: phoneOtp.trim(),
        });
    }

    async function handlePhoneComplete() {
        if (contactSaving || !phoneChangeVerified) return;

        const success = await onCompletePhoneChange();

        if (success) {
            setPhoneChanged(true);
            setNewPhone('');
            setPhoneOtp('');
        }
    }

    async function handleEmailRequest(
        event: React.FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        if (contactSaving || !newEmail.trim()) return;

        const success = await onRequestEmailChange({
            email: newEmail.trim(),
        });

        if (success) setEmailOtp('');
    }

    async function handleEmailVerify(
        event: React.FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        if (
            contactSaving ||
            !emailChangeVerificationId ||
            emailOtp.length !== 6
        ) return;

        await onVerifyEmailChange({
            verification_id: emailChangeVerificationId,
            otp: emailOtp.trim(),
        });
    }

    async function handleEmailComplete() {
        if (contactSaving || !emailChangeVerified) return;

        const success = await onCompleteEmailChange();

        if (success) {
            setEmailChanged(true);
            setNewEmail('');
            setEmailOtp('');
        }
    }

    async function handleAccountAction() {
        if (!accountAction || accountActionSaving) return;

        const success = accountAction === 'deactivate'
            ? await onDeactivateAccount()
            : await onDeleteAccount();

        if (success) {
            setAccountAction(null);
        }
    }

    return (
        <div className="sr-profile-page">
            <section className="customer-panel">
                <div className="customer-section-heading">
                    <div>
                        <h2>Profil Saya</h2>
                        <p>Maklumat akaun pelanggan Super Runner.</p>
                    </div>
                </div>

                <div className="customer-profile-picture-section">
                    <div className="customer-profile-avatar">
                        {previewUrl ? (
                            <img
                                className="customer-profile-avatar-image"
                                src={previewUrl}
                                alt="Pratonton gambar profil"
                            />
                        ) : profilePictureUrl ? (
                            <img
                                className="customer-profile-avatar-image"
                                src={profilePictureUrl}
                                alt={`Gambar profil ${account.profile_name}`}
                            />
                        ) : avatarLetter}
                    </div>

                    <div className="customer-profile-picture-actions">
                        <label htmlFor="profile-picture">
                            {selectedPicture
                                ? 'Pratonton Gambar Profil'
                                : 'Gambar Profil'}
                        </label>

                        <input
                            id="profile-picture"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={handlePictureChange}
                            disabled={pictureUploading}
                        />

                        <small>JPG, PNG atau WebP. Saiz maksimum 5 MB.</small>

                        {selectedPicture && (
                            <div className="customer-profile-picture-preview">
                                <p>{selectedPicture.name}</p>
                                <button
                                    type="button"
                                    onClick={() => void handlePictureUpload()}
                                    disabled={pictureUploading}
                                >
                                    Gunakan Gambar Ini
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setSelectedPicture(null)}
                                    disabled={pictureUploading}
                                >
                                    Batal
                                </button>
                            </div>
                        )}

                        {pictureUploading && (
                            <p role="status">Sedang memuat naik gambar...</p>
                        )}
                        {pictureError && <p role="alert">{pictureError}</p>}
                        {pictureMessage && <p role="status">{pictureMessage}</p>}
                    </div>
                </div>

                <form onSubmit={onSubmit}>
                    <div>
                        <label htmlFor="profile-name">Nama</label>
                        <input
                            id="profile-name"
                            type="text"
                            autoComplete="name"
                            value={profileName}
                            onChange={(event) => onNameChange(event.target.value)}
                            required
                            maxLength={255}
                            disabled={profileSaving}
                        />
                    </div>

                    <dl className="customer-detail-list">
                        <div>
                            <dt>Nombor Telefon</dt>
                            <dd>{account.phone || 'Belum ditetapkan'}</dd>
                        </div>
                        <div>
                            <dt>E-mel</dt>
                            <dd>{account.email || 'Belum ditetapkan'}</dd>
                        </div>
                        <div>
                            <dt>Peranan</dt>
                            <dd>{account.role}</dd>
                        </div>
                        <div>
                            <dt>Status Akaun</dt>
                            <dd>{account.status}</dd>
                        </div>
                    </dl>

                    {profileError && <p role="alert">{profileError}</p>}
                    {profileMessage && <p role="status">{profileMessage}</p>}

                    <button type="submit" disabled={profileSaving}>
                        {profileSaving ? 'Sedang menyimpan...' : 'Simpan Perubahan'}
                    </button>
                </form>
            </section>

            <section className="customer-panel">
                <h2>Tukar Nombor Telefon</h2>
                <p>Nombor baharu perlu disahkan sebelum dikemaskini.</p>

                {phoneChanged ? (
                    <p role="status">Nombor telefon berjaya dikemaskini.</p>
                ) : phoneChangeVerified ? (
                    <div>
                        <p role="status">Nombor telefon baharu telah disahkan.</p>
                        <button
                            type="button"
                            onClick={() => void handlePhoneComplete()}
                            disabled={contactSaving}
                        >
                            Sahkan Nombor Baharu
                        </button>
                    </div>
                ) : phoneChangeVerificationId ? (
                    <form onSubmit={handlePhoneVerify}>
                        <label htmlFor="phone-change-otp">Kod Pengesahan Telefon</label>
                        <input
                            id="phone-change-otp"
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            value={phoneOtp}
                            onChange={(event) => setPhoneOtp(event.target.value)}
                            minLength={6}
                            maxLength={6}
                            pattern="[0-9]{6}"
                            required
                            disabled={contactSaving}
                        />
                        <button
                            type="submit"
                            disabled={contactSaving || phoneOtp.length !== 6}
                        >
                            Sahkan Kod
                        </button>
                    </form>
                ) : (
                    <form onSubmit={handlePhoneRequest}>
                        <label htmlFor="new-phone">Nombor Telefon Baharu</label>
                        <input
                            id="new-phone"
                            type="tel"
                            autoComplete="tel"
                            value={newPhone}
                            onChange={(event) => setNewPhone(event.target.value)}
                            placeholder="Contoh: 60123456789"
                            maxLength={20}
                            required
                            disabled={contactSaving}
                        />
                        <button
                            type="submit"
                            disabled={contactSaving || !newPhone.trim()}
                        >
                            Hantar Kod Pengesahan
                        </button>
                    </form>
                )}
            </section>

            <section className="customer-panel">
                <h2>Tukar E-mel</h2>
                <p>E-mel baharu perlu disahkan sebelum dikemaskini.</p>

                {emailChanged ? (
                    <p role="status">E-mel berjaya dikemaskini.</p>
                ) : emailChangeVerified ? (
                    <div>
                        <p role="status">E-mel baharu telah disahkan.</p>
                        <button
                            type="button"
                            onClick={() => void handleEmailComplete()}
                            disabled={contactSaving}
                        >
                            Sahkan E-mel Baharu
                        </button>
                    </div>
                ) : emailChangeVerificationId ? (
                    <form onSubmit={handleEmailVerify}>
                        <label htmlFor="email-change-otp">Kod Pengesahan E-mel</label>
                        <input
                            id="email-change-otp"
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            value={emailOtp}
                            onChange={(event) => setEmailOtp(event.target.value)}
                            minLength={6}
                            maxLength={6}
                            pattern="[0-9]{6}"
                            required
                            disabled={contactSaving}
                        />
                        <button
                            type="submit"
                            disabled={contactSaving || emailOtp.length !== 6}
                        >
                            Sahkan Kod
                        </button>
                    </form>
                ) : (
                    <form onSubmit={handleEmailRequest}>
                        <label htmlFor="new-email">E-mel Baharu</label>
                        <input
                            id="new-email"
                            type="email"
                            autoComplete="email"
                            value={newEmail}
                            onChange={(event) => setNewEmail(event.target.value)}
                            maxLength={255}
                            required
                            disabled={contactSaving}
                        />
                        <button
                            type="submit"
                            disabled={contactSaving || !newEmail.trim()}
                        >
                            Hantar Kod Pengesahan
                        </button>
                    </form>
                )}
            </section>

            {contactSaving && (
                <p role="status">Sedang memproses pengesahan maklumat hubungan...</p>
            )}
            {contactError && <p role="alert">{contactError}</p>}
            {contactMessage && <p role="status">{contactMessage}</p>}

            <section className="customer-panel">
                <div className="customer-section-heading">
                    <div>
                        <h2>Tukar Kata Laluan</h2>
                        <p>Kemas kini kata laluan akaun untuk keselamatan.</p>
                    </div>
                </div>

                {passwordChanged ? (
                    <div>
                        <p role="status">
                            Kata laluan berjaya ditukar. Semua sesi akaun telah ditamatkan.
                            Sila log masuk semula menggunakan kata laluan baharu.
                        </p>
                        <button type="button" onClick={onReturnToLogin}>
                            Kembali ke Log Masuk
                        </button>
                    </div>
                ) : (
                    <form onSubmit={handlePasswordSubmit}>
                        <div>
                            <label htmlFor="current-password">Kata Laluan Semasa</label>
                            <input
                                id="current-password"
                                type="password"
                                autoComplete="current-password"
                                value={currentPassword}
                                onChange={(event) => setCurrentPassword(event.target.value)}
                                required
                                disabled={passwordSaving}
                            />
                        </div>

                        <div>
                            <label htmlFor="new-password">Kata Laluan Baharu</label>
                            <input
                                id="new-password"
                                type="password"
                                autoComplete="new-password"
                                value={newPassword}
                                onChange={(event) => setNewPassword(event.target.value)}
                                minLength={8}
                                maxLength={12}
                                required
                                disabled={passwordSaving}
                            />
                        </div>

                        <div>
                            <label htmlFor="new-password-confirmation">
                                Sahkan Kata Laluan Baharu
                            </label>
                            <input
                                id="new-password-confirmation"
                                type="password"
                                autoComplete="new-password"
                                value={newPasswordConfirmation}
                                onChange={(event) => setNewPasswordConfirmation(event.target.value)}
                                minLength={8}
                                maxLength={12}
                                required
                                disabled={passwordSaving}
                            />
                        </div>

                        <small>
                            8 hingga 12 aksara, mesti mengandungi huruf besar,
                            huruf kecil dan nombor. Kata laluan baharu mesti
                            berbeza daripada kata laluan semasa.
                        </small>

                        {passwordError && <p role="alert">{passwordError}</p>}
                        {passwordMessage && <p role="status">{passwordMessage}</p>}

                        <button
                            type="submit"
                            disabled={
                                passwordSaving ||
                                !currentPassword ||
                                !newPassword ||
                                !newPasswordConfirmation
                            }
                        >
                            {passwordSaving
                                ? 'Sedang menukar kata laluan...'
                                : 'Tukar Kata Laluan'}
                        </button>
                    </form>
                )}
            </section>

            <section className="customer-panel">
                <h2>Urus Akaun</h2>
                <p>
                    Pilihan untuk menyahaktifkan atau memadam akaun Super Runner.
                    Tindakan ini akan menamatkan sesi log masuk akaun.
                </p>

                <div>
                    <h3>Nyahaktifkan Akaun</h3>
                    <p>
                        Akaun akan dinyahaktifkan dan anda akan log keluar.
                        Anda tidak boleh menggunakan akaun sehingga ia diaktifkan
                        semula oleh pihak pentadbir.
                    </p>
                    <button
                        type="button"
                        disabled={accountActionSaving}
                        onClick={() => setAccountAction('deactivate')}
                    >
                        Nyahaktifkan Akaun
                    </button>
                </div>

                <hr />

                <div>
                    <h3>Padam Akaun</h3>
                    <p>
                        Status akaun akan ditukar kepada dipadam. Akaun tidak lagi
                        boleh digunakan untuk log masuk. Rekod akaun tidak dipadam
                        secara fizikal daripada pangkalan data.
                    </p>
                    <button
                        type="button"
                        disabled={accountActionSaving}
                        onClick={() => setAccountAction('delete')}
                    >
                        Padam Akaun
                    </button>
                </div>

                {accountActionError && (
                    <p role="alert">{accountActionError}</p>
                )}
            </section>

            {accountAction && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="account-action-title"
                    className="customer-account-confirmation"
                >
                    <section className="customer-panel">
                        <h2 id="account-action-title">
                            {accountAction === 'deactivate'
                                ? 'Sahkan Penyahaktifan Akaun'
                                : 'Sahkan Pemadaman Akaun'}
                        </h2>

                        <p>
                            {accountAction === 'deactivate'
                                ? 'Adakah anda pasti mahu menyahaktifkan akaun? Anda akan dilog keluar dan tidak dapat menggunakan akaun sehingga diaktifkan semula oleh pentadbir.'
                                : 'Adakah anda pasti mahu memadam akaun? Status akaun akan menjadi DELETED dan anda tidak lagi boleh log masuk.'}
                        </p>

                        <p>Tindakan ini akan menamatkan semua sesi akaun.</p>

                        <button
                            type="button"
                            onClick={() => void handleAccountAction()}
                            disabled={accountActionSaving}
                        >
                            {accountActionSaving
                                ? 'Sedang diproses...'
                                : 'Ya, Teruskan'}
                        </button>

                        <button
                            type="button"
                            onClick={() => setAccountAction(null)}
                            disabled={accountActionSaving}
                        >
                            Batal
                        </button>
                    </section>
                </div>
            )}
        </div>
    );
}
