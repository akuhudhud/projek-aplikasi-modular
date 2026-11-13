
import React, { useEffect, useState } from 'react';
import type { CustomerAccount, ChangePasswordPayload } from '../api';

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
    onNameChange: (value: string) => void;
    onSubmit: (event: React.FormEvent<HTMLFormElement>) => void;
    onPictureSelect: (file: File) => Promise<boolean>;
    onChangePassword?: (payload: ChangePasswordPayload) => Promise<boolean>;
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
    onNameChange,
    onSubmit,
    onPictureSelect,
    onChangePassword = async () => false,
    onReturnToLogin = () => undefined,
}: ProfilePageProps) {
    const [selectedPicture, setSelectedPicture] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('');
    const [passwordChanged, setPasswordChanged] = useState(false);

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

                        <button
                            type="button"
                            onClick={onReturnToLogin}
                        >
                            Kembali ke Log Masuk
                        </button>
                    </div>
                ) : (
                    <form onSubmit={handlePasswordSubmit}>
                        <div>
                            <label htmlFor="current-password">
                                Kata Laluan Semasa
                            </label>
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
                            <label htmlFor="new-password">
                                Kata Laluan Baharu
                            </label>
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

                        {passwordError && (
                            <p role="alert">{passwordError}</p>
                        )}

                        {passwordMessage && (
                            <p role="status">{passwordMessage}</p>
                        )}

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
        </div>
    );
}
