import type { FormEvent } from 'react';

import type {
    RunnerAccount,
    RunnerProfileAnalytics,
    RunnerPublicProfile,
} from '../../api';

interface RunnerProfileFormProps {
    account: RunnerAccount;
    profile: RunnerPublicProfile | null;
    analytics: RunnerProfileAnalytics;
    profileLoadFailed: boolean;
    saving: boolean;
    publicSlug: string;
    bio: string;
    baseLocation: string;
    serviceArea: string;
    servicesText: string;
    isPublished: boolean;
    onPublicSlugChange: (value: string) => void;
    onBioChange: (value: string) => void;
    onBaseLocationChange: (value: string) => void;
    onServiceAreaChange: (value: string) => void;
    onServicesTextChange: (value: string) => void;
    onPublishedChange: (value: boolean) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

export default function RunnerProfileForm({
    account,
    profile,
    analytics,
    profileLoadFailed,
    saving,
    publicSlug,
    bio,
    baseLocation,
    serviceArea,
    servicesText,
    isPublished,
    onPublicSlugChange,
    onBioChange,
    onBaseLocationChange,
    onServiceAreaChange,
    onServicesTextChange,
    onPublishedChange,
    onSubmit,
}: RunnerProfileFormProps) {
    return (
        <>
            <div className="runner-profile__analytics">
                <article className="runner-dashboard__summary-card">
                    <span>JUMLAH TONTONAN</span>
                    <strong>{analytics.profile_views}</strong>
                </article>

                <article className="runner-dashboard__summary-card">
                    <span>PERMINTAAN PROFIL</span>
                    <strong>{analytics.requests_from_profile}</strong>
                </article>

                <article className="runner-dashboard__summary-card">
                    <span>SEBUT HARGA</span>
                    <strong>
                        {analytics.quotations_for_profile_requests}
                    </strong>
                </article>

                <article className="runner-dashboard__summary-card">
                    <span>TUGASAN SELESAI</span>
                    <strong>
                        {analytics.completed_tasks_from_profile_requests}
                    </strong>
                </article>
            </div>

            {profileLoadFailed && (
                <div className="runner-dashboard__alert">
                    Profil belum berjaya dimuatkan. Fungsi simpan
                    dikunci untuk melindungi data profil sedia ada.
                    Tekan SEGARKAN untuk mencuba semula.
                </div>
            )}

            <form
                className="runner-profile__form"
                onSubmit={onSubmit}
            >
                <div className="runner-profile__form-heading">
                    <h3>Maklumat Profil Awam</h3>
                    <p>
                        Maklumat ini boleh dilihat pelanggan apabila
                        profil anda diterbitkan.
                    </p>
                </div>

                <label>
                    Nama Paparan
                    <input
                        type="text"
                        value={account.profile_name}
                        readOnly
                    />
                    <small>
                        Nama paparan diambil daripada profil akaun.
                    </small>
                </label>

                <label>
                    Nama pautan profil
                    <input
                        type="text"
                        value={publicSlug}
                        onChange={(event) =>
                            onPublicSlugChange(event.target.value)
                        }
                        minLength={3}
                        maxLength={50}
                        placeholder="contoh: runner-tuaran"
                        required
                        disabled={profileLoadFailed}
                    />
                </label>

                <label>
                    Biodata
                    <textarea
                        rows={4}
                        maxLength={2000}
                        value={bio}
                        onChange={(event) =>
                            onBioChange(event.target.value)
                        }
                        placeholder="Perkenalkan diri dan perkhidmatan anda..."
                        disabled={profileLoadFailed}
                    />
                </label>

                <label>
                    Lokasi asas
                    <input
                        type="text"
                        maxLength={120}
                        value={baseLocation}
                        onChange={(event) =>
                            onBaseLocationChange(event.target.value)
                        }
                        placeholder="Contoh: Tuaran, Sabah"
                        disabled={profileLoadFailed}
                    />
                </label>

                <label>
                    Kawasan perkhidmatan
                    <input
                        type="text"
                        maxLength={120}
                        value={serviceArea}
                        onChange={(event) =>
                            onServiceAreaChange(event.target.value)
                        }
                        placeholder="Contoh: Tuaran dan Kota Kinabalu"
                        disabled={profileLoadFailed}
                    />
                </label>

                <label>
                    Perkhidmatan
                    <textarea
                        rows={3}
                        value={servicesText}
                        onChange={(event) =>
                            onServicesTextChange(event.target.value)
                        }
                        placeholder="Contoh: Penghantaran, Pembelian barang, Urusan dokumen"
                        disabled={profileLoadFailed}
                    />
                    <small>
                        Pisahkan setiap perkhidmatan menggunakan koma.
                    </small>
                </label>

                <label className="runner-profile__publish">
                    <input
                        type="checkbox"
                        checked={isPublished}
                        onChange={(event) =>
                            onPublishedChange(event.target.checked)
                        }
                        disabled={profileLoadFailed}
                    />

                    <span>
                        <strong>Terbitkan profil awam</strong>
                        <small>
                            Benarkan pelanggan melihat profil Runner anda.
                        </small>
                    </span>
                </label>

                <button
                    type="submit"
                    className="runner-dashboard__primary-action"
                    disabled={saving || profileLoadFailed}
                >
                    {profileLoadFailed
                        ? 'PROFIL BELUM DIMUATKAN'
                        : saving
                            ? 'MENYIMPAN...'
                            : 'SIMPAN PROFIL'}
                </button>

                {profile?.public_slug && (
                    <p className="runner-profile__slug">
                        Pautan profil: <strong>{profile.public_slug}</strong>
                    </p>
                )}
            </form>
        </>
    );
}
