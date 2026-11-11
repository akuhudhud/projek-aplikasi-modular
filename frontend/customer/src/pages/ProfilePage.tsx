import React from 'react';
import type { CustomerAccount } from '../api';

type ProfilePageProps = {
    account: CustomerAccount;
    profileName: string;
    profileSaving: boolean;
    profileError: string;
    profileMessage: string;
    onNameChange: (value: string) => void;
    onSubmit: (event: React.FormEvent<HTMLFormElement>) => void;
};

export default function ProfilePage({
    account,
    profileName,
    profileSaving,
    profileError,
    profileMessage,
    onNameChange,
    onSubmit,
}: ProfilePageProps) {
    const avatarLetter =
        account.profile_name?.trim().charAt(0).toUpperCase() || '?';

    return (
        <div className="sr-profile-page">
            <section className="customer-panel">
                <div className="customer-section-heading">
                    <div>
                        <h2>Profil Saya</h2>
                        <p>Maklumat akaun pelanggan Super Runner.</p>
                    </div>
                </div>

                <div className="customer-profile-avatar">
                    {avatarLetter}
                </div>

                <form onSubmit={onSubmit}>
                    <div>
                        <label htmlFor="profile-name">
                            Nama
                        </label>

                        <input
                            id="profile-name"
                            type="text"
                            autoComplete="name"
                            value={profileName}
                            onChange={(event) =>
                                onNameChange(event.target.value)
                            }
                            required
                            maxLength={255}
                            disabled={profileSaving}
                        />
                    </div>

                    <dl className="customer-detail-list">
                        <div>
                            <dt>Nombor Telefon</dt>
                            <dd>
                                {account.phone || 'Belum ditetapkan'}
                            </dd>
                        </div>

                        <div>
                            <dt>E-mel</dt>
                            <dd>
                                {account.email || 'Belum ditetapkan'}
                            </dd>
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

                    {profileError && (
                        <p role="alert">{profileError}</p>
                    )}

                    {profileMessage && (
                        <p role="status">{profileMessage}</p>
                    )}

                    <button
                        type="submit"
                        disabled={profileSaving}
                    >
                        {profileSaving
                            ? 'Sedang menyimpan...'
                            : 'Simpan Perubahan'}
                    </button>
                </form>
            </section>
        </div>
    );
}
