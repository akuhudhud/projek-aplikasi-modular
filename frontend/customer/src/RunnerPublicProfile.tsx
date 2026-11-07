import { useEffect, useState } from 'react';
import axios from 'axios';
import './RunnerPublicProfile.css';

interface RunnerPublicProfileData {
    public_slug: string;
    display_name: string;
    bio: string | null;
    service_area: string | null;
    base_location: string | null;
    coverage_area: string | null;
    services: string[];
    rating_average: number | null;
    rating_count: number;
    views_count: number;
}

interface PublicProfileResponse {
    success: boolean;
    data: {
        profile: RunnerPublicProfileData;
    };
}

interface Props {
    slug: string;
}

export default function RunnerPublicProfile({ slug }: Props) {
    const [profile, setProfile] = useState<RunnerPublicProfileData | null>(null);
    const [loading, setLoading] = useState(true);
    const [notFound, setNotFound] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        let active = true;

        setLoading(true);
        setNotFound(false);
        setError('');

        axios.get<PublicProfileResponse>(
            `/api/runners/${encodeURIComponent(slug.toLowerCase())}`,
            {
                baseURL: import.meta.env.VITE_API_BASE_URL ?? '/',
                withCredentials: true,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        )
            .then((response) => {
                if (active) setProfile(response.data.data.profile);
            })
            .catch((requestError: unknown) => {
                if (!active) return;

                if (
                    axios.isAxiosError(requestError) &&
                    requestError.response?.status === 404
                ) {
                    setNotFound(true);
                    return;
                }

                setError('Tidak berjaya memuatkan profil Runner. Sila cuba lagi.');
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [slug]);

    function openRequestForm() {
        window.location.href = `/?runner=${encodeURIComponent(slug.toLowerCase())}`;
    }

    async function shareProfile() {
        const url = `${window.location.origin}/me/${encodeURIComponent(slug.toLowerCase())}`;

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
                ) return;

                setError('Tidak berjaya berkongsi pautan profil.');
            }

            return;
        }

        try {
            await navigator.clipboard.writeText(url);
            window.alert('Pautan profil berjaya disalin.');
        } catch {
            setError('Tidak berjaya menyalin pautan profil.');
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
                    <button
                        type="button"
                        onClick={() => window.location.reload()}
                    >
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

                    {error && (
                        <p className="runner-public-error" role="alert">
                            {error}
                        </p>
                    )}
                </footer>
            </article>
        </main>
    );
}
