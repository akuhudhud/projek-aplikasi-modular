
import { useRunnerPublicProfile } from './features/runners/useRunnerPublicProfile';
import './RunnerPublicProfile.css';

interface Props {
    slug: string;
}

export default function RunnerPublicProfile({ slug }: Props) {
    const {
        profile,
        loading,
        error,
        reload,
    } = useRunnerPublicProfile(slug);

    const reviewsUrl = `/me/${encodeURIComponent(slug.toLowerCase())}/ulasan`;

    if (loading && !profile) {
        return (
            <main className="runner-public-page">
                <article className="runner-public-card">
                    <p>Sedang memuatkan profil Runner...</p>
                </article>
            </main>
        );
    }

    if (error || !profile) {
        return (
            <main className="runner-public-page">
                <article className="runner-public-card">
                    <p className="runner-public-error" role="alert">
                        {error || 'Profil Runner tidak dijumpai.'}
                    </p>

                    <button
                        type="button"
                        className="runner-public-primary"
                        onClick={reload}
                    >
                        Cuba Semula
                    </button>
                </article>
            </main>
        );
    }

    return (
        <main className="runner-public-page">
            <article className="runner-public-card">
                <header className="runner-public-header">
                    <span className="runner-public-brand">
                        SUPER RUNNER
                    </span>

                    <span className="runner-public-label">
                        PROFIL RUNNER
                    </span>
                </header>

                <section className="runner-public-section">
                    <h1>{profile.profile_name}</h1>

                    {profile.rating_average !== null && (
                        <p>
                            <strong>
                                ★ {Number(profile.rating_average).toFixed(1)}
                            </strong>
                            {' '}daripada 5
                        </p>
                    )}

                    <p className="runner-public-review-count">
                        {profile.rating_count} penilaian pelanggan
                    </p>

                    <a
                        className="runner-public-secondary"
                        href={reviewsUrl}
                        style={{
                            display: 'flex',
                            minHeight: 46,
                            alignItems: 'center',
                            justifyContent: 'center',
                            borderRadius: 10,
                            textDecoration: 'none',
                            fontWeight: 700,
                            marginTop: 12,
                        }}
                    >
                        Lihat Ulasan
                    </a>
                </section>

                {profile.base_location && (
                    <section className="runner-public-section">
                        <h2>Lokasi</h2>
                        <p>{profile.base_location}</p>
                    </section>
                )}

                {profile.service_area && (
                    <section className="runner-public-section">
                        <h2>Kawasan Perkhidmatan</h2>
                        <p>{profile.service_area}</p>
                    </section>
                )}

                {profile.bio && (
                    <section className="runner-public-section">
                        <h2>Tentang Runner</h2>
                        <p>{profile.bio}</p>
                    </section>
                )}

                {profile.services.length > 0 && (
                    <section className="runner-public-section">
                        <h2>Perkhidmatan</h2>

                        <ul>
                            {profile.services.map((service, index) => (
                                <li key={`${service}-${index}`}>
                                    {service}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <footer className="runner-public-footer">
                    <button
                        type="button"
                        className="runner-public-primary"
                    >
                        Buat Permintaan
                    </button>

                    <button
                        type="button"
                        className="runner-public-secondary"
                        onClick={() => {
                            if (navigator.share) {
                                void navigator.share({
                                    title: profile.profile_name,
                                    url: window.location.href,
                                });
                            } else {
                                void navigator.clipboard.writeText(
                                    window.location.href,
                                );
                            }
                        }}
                    >
                        Kongsi Profil
                    </button>
                </footer>
            </article>
        </main>
    );
}
