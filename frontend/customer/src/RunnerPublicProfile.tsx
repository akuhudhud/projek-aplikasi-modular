
import { useState } from 'react';

import { useRunnerPublicProfile } from './features/runners/useRunnerPublicProfile';
import './RunnerPublicProfile.css';

interface Props {
    slug: string;
}

export default function RunnerPublicProfile({ slug }: Props) {
    const {
        profile,
        loading,
        notFound,
        error,
        reload,
    } = useRunnerPublicProfile(slug);

    const [shareError, setShareError] = useState('');

    function openRequestForm() {
        window.location.href = `/?runner=${encodeURIComponent(slug.toLowerCase())}`;
    }

    async function shareProfile() {
        const url = `${window.location.origin}/me/${encodeURIComponent(slug.toLowerCase())}`;

        setShareError('');

        if (navigator.share) {
            try {
                await navigator.share({
                    title: profile?.display_name ?? 'Profil Runner Super Runner',
                    text: 'Lihat profil Runner ini.',
                    url,
                });
            } catch (shareError) {
                if (
                    shareError instanceof Error &&
                    shareError.name === 'AbortError'
                ) {
                    return;
                }

                setShareError('Tidak berjaya berkongsi pautan profil.');
            }

            return;
        }

        try {
            await navigator.clipboard.writeText(url);
            window.alert('Pautan profil berjaya disalin.');
        } catch {
            setShareError('Tidak berjaya menyalin pautan profil.');
        }
    }

    if (loading) {
        return (
            <main className="runner-public-page">
                <section className="runner-public-card" aria-live="polite">
                    <p>Memuatkan profil Runner...</p>
                </section>
            </main>
        );
    }

    if (notFound) {
        return (
            <main className="runner-public-page">
                <section className="runner-public-card runner-public-state">
                    <h1>Profil tidak ditemui</h1>
                    <p>
                        Pautan ini mungkin tidak sah atau profil Runner
                        belum diterbitkan.
                    </p>
                    <a href="/">Ke halaman utama</a>
                </section>
            </main>
        );
    }

    if (error && !profile) {
        return (
            <main className="runner-public-page">
                <section className="runner-public-card runner-public-state">
                    <h1>Ralat</h1>
                    <p role="alert">{error}</p>
                    <button type="button" onClick={reload}>
                        Cuba Lagi
                    </button>
                </section>
            </main>
        );
    }

    if (!profile) return null;

    return (
        <main className="runner-public-page">
            <article className="runner-public-card">
                <header className="runner-public-header">
                    <span className="runner-public-brand">SUPER RUNNER</span>
                    <span className="runner-public-label">PROFIL RUNNER</span>
                </header>

                <section className="runner-public-identity">
                    <div className="runner-public-avatar" aria-hidden="true">
                        {profile.display_name.trim().charAt(0).toUpperCase() || 'R'}
                    </div>

                    <div>
                        <h1>{profile.display_name}</h1>

                        {profile.base_location && (
                            <p className="runner-public-base">
                                {profile.base_location}
                            </p>
                        )}

                        <div className="runner-public-rating">
                            <span>
                                {profile.rating_average === null
                                    ? 'Belum dinilai'
                                    : `★ ${profile.rating_average.toFixed(1)} / 5`}
                            </span>
                            <span>({profile.rating_count} penilaian)</span>
                        </div>
                    </div>
                </section>

                {profile.bio && (
                    <section className="runner-public-section">
                        <h2>Tentang Runner</h2>
                        <p>{profile.bio}</p>
                    </section>
                )}

                {profile.services.length > 0 && (
                    <section className="runner-public-section">
                        <h2>Servis</h2>
                        <ul className="runner-public-services">
                            {profile.services.map((service, index) => (
                                <li key={`${service}-${index}`}>
                                    {service}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {(profile.service_area || profile.coverage_area) && (
                    <section className="runner-public-section">
                        <h2>Kawasan Servis</h2>

                        {profile.service_area && (
                            <p>{profile.service_area}</p>
                        )}

                        {profile.coverage_area && (
                            <p>{profile.coverage_area}</p>
                        )}
                    </section>
                )}

                <footer className="runner-public-footer">
                    <button
                        className="runner-public-primary"
                        type="button"
                        onClick={openRequestForm}
                    >
                        Minta Servis Ini
                    </button>

                    <button
                        className="runner-public-secondary"
                        type="button"
                        onClick={shareProfile}
                    >
                        Kongsi Profil
                    </button>

                    {shareError && (
                        <p className="runner-public-error" role="alert">
                            {shareError}
                        </p>
                    )}
                </footer>
            </article>
        </main>
    );
}
