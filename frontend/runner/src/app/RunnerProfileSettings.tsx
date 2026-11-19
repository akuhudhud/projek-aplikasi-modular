
import {
    useCallback,
    useEffect,
    useState,
    type FormEvent,
} from 'react';

import {
    runnerApi,
    type RunnerAccount,
    type RunnerPublicProfile,
    type RunnerProfileAnalytics,
} from '../api';

type ProfileTab = 'PROFILE' | 'SETTINGS';

interface RunnerProfileSettingsProps {
    account: RunnerAccount;
    onLogout: () => void;
    logoutLoading: boolean;
}

const EMPTY_ANALYTICS: RunnerProfileAnalytics = {
    profile_views: 0,
    requests_from_profile: 0,
    quotations_for_profile_requests: 0,
    completed_tasks_from_profile_requests: 0,
};

function createEmptyProfile(
    account: RunnerAccount,
): RunnerPublicProfile {
    return {
        public_slug: '',
        profile_name: account.profile_name,
        bio: '',
        base_location: '',
        service_area: '',
        services: [],
        is_published: false,
        views_count: 0,
    };
}

function RunnerProfileSettings({
    account,
    onLogout,
    logoutLoading,
}: RunnerProfileSettingsProps) {
    const [activeTab, setActiveTab] =
        useState<ProfileTab>('PROFILE');

    const [profile, setProfile] =
        useState<RunnerPublicProfile | null>(null);

    const [analytics, setAnalytics] =
        useState<RunnerProfileAnalytics>(EMPTY_ANALYTICS);

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    const [publicSlug, setPublicSlug] = useState('');
    const [bio, setBio] = useState('');
    const [baseLocation, setBaseLocation] = useState('');
    const [serviceArea, setServiceArea] = useState('');
    const [servicesText, setServicesText] = useState('');
    const [isPublished, setIsPublished] = useState(false);

    const loadProfile = useCallback(async () => {
        setLoading(true);
        setError('');

        try {
            const result = await runnerApi.getProfile();

            const currentProfile = result
                ?? createEmptyProfile(account);

            setProfile(result);
            setPublicSlug(currentProfile.public_slug);
            setBio(currentProfile.bio ?? '');
            setBaseLocation(currentProfile.base_location ?? '');
            setServiceArea(currentProfile.service_area ?? '');
            setServicesText(
                (currentProfile.services ?? []).join(', '),
            );
            setIsPublished(currentProfile.is_published);

            if (result) {
                try {
                    const statistics =
                        await runnerApi.getProfileAnalytics();

                    setAnalytics(statistics);
                } catch {
                    setAnalytics({
                        ...EMPTY_ANALYTICS,
                        profile_views: result.views_count,
                    });
                }
            } else {
                setAnalytics(EMPTY_ANALYTICS);
            }
        } catch {
            setError(
                'Tidak berjaya mendapatkan profil Runner. Sila cuba semula.',
            );
        } finally {
            setLoading(false);
        }
    }, [account]);

    useEffect(() => {
        void loadProfile();
    }, [loadProfile]);

    async function handleSaveProfile(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        if (saving) {
            return;
        }

        const normalizedSlug = publicSlug
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '-');

        if (!/^[a-z0-9_-]{3,50}$/.test(normalizedSlug)) {
            setError(
                'Pautan profil mesti 3 hingga 50 aksara dan hanya menggunakan huruf, nombor, tanda sempang atau garis bawah.',
            );
            return;
        }

        const services = servicesText
            .split(',')
            .map((service) => service.trim())
            .filter(Boolean)
            .slice(0, 20);

        setSaving(true);
        setError('');
        setSuccess('');

        try {
            const saved = await runnerApi.saveProfile({
                public_slug: normalizedSlug,
                bio: bio.trim() || null,
                base_location: baseLocation.trim() || null,
                service_area: serviceArea.trim() || null,
                services,
                is_published: isPublished,
            });

            setProfile(saved);
            setPublicSlug(saved.public_slug);
            setBio(saved.bio ?? '');
            setBaseLocation(saved.base_location ?? '');
            setServiceArea(saved.service_area ?? '');
            setServicesText((saved.services ?? []).join(', '));
            setIsPublished(saved.is_published);

            setSuccess('Profil Runner berjaya disimpan.');

            try {
                const statistics =
                    await runnerApi.getProfileAnalytics();

                setAnalytics(statistics);
            } catch {
                setAnalytics((current) => ({
                    ...current,
                    profile_views: saved.views_count,
                }));
            }
        } catch (saveError: unknown) {
            const message =
                saveError
                && typeof saveError === 'object'
                && 'response' in saveError
                && saveError.response
                && typeof saveError.response === 'object'
                && 'data' in saveError.response
                && saveError.response.data
                && typeof saveError.response.data === 'object'
                && 'message' in saveError.response.data
                && typeof saveError.response.data.message === 'string'
                    ? saveError.response.data.message
                    : 'Profil tidak berjaya disimpan. Semak maklumat dan cuba semula.';

            setError(message);
        } finally {
            setSaving(false);
        }
    }

    if (loading) {
        return (
            <section className="runner-dashboard__section">
                <p className="runner-dashboard__notice">
                    Memuatkan profil dan tetapan Runner...
                </p>
            </section>
        );
    }

    return (
        <section className="runner-dashboard__section">
            <div className="runner-dashboard__section-heading">
                <div>
                    <h2>Profil dan Tetapan</h2>
                    <p>
                        Urus maklumat profil awam dan maklumat akaun Runner.
                    </p>
                </div>

                <button
                    type="button"
                    onClick={() => void loadProfile()}
                    disabled={saving}
                >
                    SEGARKAN
                </button>
            </div>

            <nav
                className="runner-dashboard__tabs runner-profile__tabs"
                aria-label="Menu profil Runner"
            >
                <button
                    type="button"
                    className={activeTab === 'PROFILE' ? 'is-active' : ''}
                    onClick={() => setActiveTab('PROFILE')}
                >
                    PROFIL SAYA
                </button>

                <button
                    type="button"
                    className={activeTab === 'SETTINGS' ? 'is-active' : ''}
                    onClick={() => setActiveTab('SETTINGS')}
                >
                    TETAPAN AKAUN
                </button>
            </nav>

            {error && (
                <p className="runner-dashboard__alert" role="alert">
                    {error}
                </p>
            )}

            {success && (
                <p className="runner-dashboard__success" role="status">
                    {success}
                </p>
            )}

            {activeTab === 'PROFILE' && (
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

                    <form
                        className="runner-profile__form"
                        onSubmit={handleSaveProfile}
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
                                    setPublicSlug(event.target.value)
                                }
                                minLength={3}
                                maxLength={50}
                                placeholder="contoh: runner-tuaran"
                                required
                            />
                        </label>

                        <label>
                            Biodata
                            <textarea
                                rows={4}
                                maxLength={2000}
                                value={bio}
                                onChange={(event) =>
                                    setBio(event.target.value)
                                }
                                placeholder="Perkenalkan diri dan perkhidmatan anda..."
                            />
                        </label>

                        <label>
                            Lokasi asas
                            <input
                                type="text"
                                maxLength={120}
                                value={baseLocation}
                                onChange={(event) =>
                                    setBaseLocation(event.target.value)
                                }
                                placeholder="Contoh: Tuaran, Sabah"
                            />
                        </label>

                        <label>
                            Kawasan perkhidmatan
                            <input
                                type="text"
                                maxLength={120}
                                value={serviceArea}
                                onChange={(event) =>
                                    setServiceArea(event.target.value)
                                }
                                placeholder="Contoh: Tuaran dan Kota Kinabalu"
                            />
                        </label>

                        <label>
                            Perkhidmatan
                            <textarea
                                rows={3}
                                value={servicesText}
                                onChange={(event) =>
                                    setServicesText(event.target.value)
                                }
                                placeholder="Contoh: Penghantaran, Pembelian barang, Urusan dokumen"
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
                                    setIsPublished(event.target.checked)
                                }
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
                            disabled={saving}
                        >
                            {saving ? 'MENYIMPAN...' : 'SIMPAN PROFIL'}
                        </button>

                        {profile?.public_slug && (
                            <p className="runner-profile__slug">
                                Pautan profil: <strong>{profile.public_slug}</strong>
                            </p>
                        )}
                    </form>
                </>
            )}

            {activeTab === 'SETTINGS' && (
                <div className="runner-dashboard__list">
                    <article className="runner-dashboard__item">
                        <h3>Maklumat Akaun</h3>

                        <div className="runner-profile__account-row">
                            <span>Nama paparan</span>
                            <strong>{account.profile_name}</strong>
                        </div>

                        <div className="runner-profile__account-row">
                            <span>Nombor telefon</span>
                            <strong>{account.phone ?? 'Belum ditetapkan'}</strong>
                        </div>

                        <div className="runner-profile__account-row">
                            <span>E-mel</span>
                            <strong>{account.email ?? 'Belum ditetapkan'}</strong>
                        </div>

                        <div className="runner-profile__account-row">
                            <span>Status akaun</span>
                            <strong>{account.status}</strong>
                        </div>

                        <div className="runner-profile__account-row">
                            <span>Status Runner</span>
                            <strong>{account.runner_status}</strong>
                        </div>
                    </article>

                    <article className="runner-dashboard__item">
                        <h3>Keselamatan Akaun</h3>
                        <p>
                            Maklumat pengesahan identiti digunakan semasa
                            proses permohonan Runner. Maklumat peribadi
                            pengenalan tidak dipaparkan pada profil awam.
                        </p>

                        <button
                            type="button"
                            className="runner-dashboard__primary-action"
                            onClick={onLogout}
                            disabled={logoutLoading}
                        >
                            {logoutLoading
                                ? 'SEDANG LOG KELUAR...'
                                : 'LOG KELUAR AKAUN'}
                        </button>
                    </article>
                </div>
            )}
        </section>
    );
}

export default RunnerProfileSettings;
