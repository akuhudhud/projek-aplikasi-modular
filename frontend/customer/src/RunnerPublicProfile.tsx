
import { useState } from 'react';

import { useRunnerPublicProfile } from './features/runners/useRunnerPublicProfile';
import { useRunnerPublicReviews } from './features/runners/useRunnerPublicReviews';
import './RunnerPublicProfile.css';

interface Props {
    slug: string;
}

function formatReviewDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleDateString('ms-MY', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function RunnerPublicProfile({ slug }: Props) {
    const {
        profile,
        loading,
        notFound,
        error,
        reload,
    } = useRunnerPublicProfile(slug);

    const {
        reviews,
        pagination,
        loading: reviewsLoading,
        loadingMore,
        error: reviewsError,
        hasMore,
        loadMore,
        reload: reloadReviews,
    } = useRunnerPublicReviews(slug);

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
                    <p>Sedang memuatkan profil Runner...</p>
                </section>
            </main>
        );
    }

    if (notFound) {
        return (
            <main className="runner-public-page">
                <section className="runner-public-card runner-public-state">
                    <h1>Profil Tidak Ditemui</h1>
                    <p>
                        Pautan ini mungkin tidak sah atau profil Runner
                        belum diterbitkan.
                    </p>
                    <a href="/">Kembali ke halaman utama</a>
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
                        Cuba Semula
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

                {(profile.base_location || profile.service_area) && (
                    <section className="runner-public-section">
                        <h2>Lokasi Runner</h2>

                        {profile.base_location && (
                            <p>
                                <strong>Lokasi Asas:</strong>{' '}
                                {profile.base_location}
                            </p>
                        )}

                        {profile.service_area && (
                            <p>
                                <strong>Kawasan Liputan:</strong>{' '}
                                {profile.service_area}
                            </p>
                        )}
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
                        <ul className="runner-public-services">
                            {profile.services.map((service, index) => (
                                <li key={`${service}-${index}`}>
                                    {service}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="runner-public-section runner-public-reviews">
                    <h2>Ulasan Pelanggan</h2>

                    {pagination && (
                        <p className="runner-public-review-count">
                            {pagination.total} ulasan bertulis
                        </p>
                    )}

                    {reviewsLoading && reviews.length === 0 && (
                        <p aria-live="polite">
                            Sedang memuatkan ulasan pelanggan...
                        </p>
                    )}

                    {!reviewsLoading && reviews.length === 0 && !reviewsError && (
                        <p className="runner-public-review-empty">
                            Belum ada ulasan bertulis daripada pelanggan.
                        </p>
                    )}

                    {reviews.length > 0 && (
                        <div className="runner-public-review-list">
                            {reviews.map((review, index) => (
                                <article
                                    className="runner-public-review-item"
                                    key={`${review.created_at}-${index}`}
                                >
                                    <div className="runner-public-review-heading">
                                        <span
                                            className="runner-public-review-stars"
                                            aria-label={`${review.rating} daripada 5 bintang`}
                                        >
                                            {'★'.repeat(
                                                Math.max(
                                                    0,
                                                    Math.min(5, review.rating),
                                                ),
                                            )}
                                            <span className="runner-public-review-empty-stars">
                                                {'★'.repeat(
                                                    5 - Math.max(
                                                        0,
                                                        Math.min(5, review.rating),
                                                    ),
                                                )}
                                            </span>
                                        </span>

                                        <span className="runner-public-review-date">
                                            {formatReviewDate(review.created_at)}
                                        </span>
                                    </div>

                                    <p className="runner-public-review-text">
                                        {review.review}
                                    </p>
                                </article>
                            ))}
                        </div>
                    )}

                    {reviewsError && (
                        <div className="runner-public-review-error">
                            <p role="alert">{reviewsError}</p>
                            <button
                                type="button"
                                onClick={reloadReviews}
                            >
                                Cuba Semula
                            </button>
                        </div>
                    )}

                    {hasMore && !reviewsError && (
                        <button
                            className="runner-public-review-more"
                            type="button"
                            onClick={loadMore}
                            disabled={loadingMore}
                        >
                            {loadingMore
                                ? 'Sedang memuatkan...'
                                : 'Lihat Lebih Banyak Ulasan'}
                        </button>
                    )}
                </section>

                <footer className="runner-public-footer">
                    <button
                        className="runner-public-primary"
                        type="button"
                        onClick={openRequestForm}
                    >
                        Buat Permintaan
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
