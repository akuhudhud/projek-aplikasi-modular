
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

export default function RunnerPublicReviews({ slug }: Props) {
    const {
        reviews,
        pagination,
        loading,
        loadingMore,
        error,
        hasMore,
        loadMore,
        reload,
    } = useRunnerPublicReviews(slug);

    const profileUrl = `/me/${encodeURIComponent(slug.toLowerCase())}`;

    return (
        <main className="runner-public-page">
            <article className="runner-public-card">
                <header className="runner-public-header">
                    <span className="runner-public-brand">
                        SUPER RUNNER
                    </span>

                    <span className="runner-public-label">
                        ULASAN RUNNER
                    </span>
                </header>

                <section className="runner-public-section">
                    <h1>Ulasan Pelanggan</h1>

                    <p>
                        Ulasan daripada pelanggan yang pernah menggunakan
                        perkhidmatan Runner ini.
                    </p>

                    {pagination && (
                        <p className="runner-public-review-count">
                            {pagination.total} ulasan bertulis
                        </p>
                    )}
                </section>

                {loading && reviews.length === 0 && (
                    <section aria-live="polite">
                        <p>Sedang memuatkan ulasan pelanggan...</p>
                    </section>
                )}

                {!loading && reviews.length === 0 && !error && (
                    <section className="runner-public-section">
                        <p className="runner-public-review-empty">
                            Belum ada ulasan bertulis daripada pelanggan.
                        </p>
                    </section>
                )}

                {reviews.length > 0 && (
                    <section className="runner-public-section">
                        <div className="runner-public-review-list">
                            {reviews.map((review, index) => {
                                const rating = Math.max(
                                    0,
                                    Math.min(5, review.rating),
                                );

                                return (
                                    <article
                                        className="runner-public-review-item"
                                        key={`${review.created_at}-${index}`}
                                    >
                                        <div className="runner-public-review-heading">
                                            <span
                                                className="runner-public-review-stars"
                                                aria-label={`${rating} daripada 5 bintang`}
                                            >
                                                {'★'.repeat(rating)}
                                                <span className="runner-public-review-empty-stars">
                                                    {'★'.repeat(5 - rating)}
                                                </span>
                                            </span>

                                            <span className="runner-public-review-date">
                                                {formatReviewDate(
                                                    review.created_at,
                                                )}
                                            </span>
                                        </div>

                                        <p className="runner-public-review-text">
                                            {review.review}
                                        </p>

                                        <p className="runner-public-review-count">
                                            Pelanggan tanpa nama
                                        </p>
                                    </article>
                                );
                            })}
                        </div>
                    </section>
                )}

                {error && (
                    <section className="runner-public-review-error">
                        <p role="alert">{error}</p>

                        <button
                            type="button"
                            onClick={reload}
                            disabled={loading}
                        >
                            Cuba Semula
                        </button>
                    </section>
                )}

                {hasMore && !error && (
                    <section className="runner-public-section">
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
                    </section>
                )}

                <footer className="runner-public-footer">
                    <a
                        className="runner-public-secondary"
                        href={profileUrl}
                        style={{
                            display: 'flex',
                            minHeight: 46,
                            alignItems: 'center',
                            justifyContent: 'center',
                            borderRadius: 10,
                            textDecoration: 'none',
                            fontWeight: 700,
                        }}
                    >
                        Kembali ke Profil Runner
                    </a>
                </footer>
            </article>
        </main>
    );
}
