
import { useCallback, useEffect, useState } from 'react';

import {
    runnerPublicApi,
    type RunnerPublicReview,
    type RunnerPublicReviewPagination,
} from './runnerPublicApi';

interface UseRunnerPublicReviewsResult {
    reviews: RunnerPublicReview[];
    pagination: RunnerPublicReviewPagination | null;
    loading: boolean;
    loadingMore: boolean;
    error: string;
    hasMore: boolean;
    loadMore: () => void;
    reload: () => void;
}

export function useRunnerPublicReviews(
    slug: string,
): UseRunnerPublicReviewsResult {
    const [reviews, setReviews] = useState<RunnerPublicReview[]>([]);
    const [pagination, setPagination] =
        useState<RunnerPublicReviewPagination | null>(null);

    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [error, setError] = useState('');
    const [reloadKey, setReloadKey] = useState(0);

    const reload = useCallback(() => {
        setReloadKey((current) => current + 1);
    }, []);

    const hasMore = pagination !== null
        && pagination.current_page < pagination.last_page;

    useEffect(() => {
        let active = true;

        setReviews([]);
        setPagination(null);
        setLoading(true);
        setLoadingMore(false);
        setError('');

        runnerPublicApi
            .getReviews(slug, 1)
            .then((data) => {
                if (!active) return;

                setReviews(data.reviews);
                setPagination(data.pagination);
            })
            .catch(() => {
                if (!active) return;

                setError(
                    'Tidak berjaya memuatkan ulasan Runner. Sila cuba lagi.',
                );
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [slug, reloadKey]);

    const loadMore = useCallback(() => {
        if (
            loading
            || loadingMore
            || !pagination
            || pagination.current_page >= pagination.last_page
        ) {
            return;
        }

        const nextPage = pagination.current_page + 1;

        setLoadingMore(true);
        setError('');

        runnerPublicApi
            .getReviews(slug, nextPage)
            .then((data) => {
                setReviews((current) => [
                    ...current,
                    ...data.reviews,
                ]);

                setPagination(data.pagination);
            })
            .catch(() => {
                setError(
                    'Tidak berjaya memuatkan ulasan seterusnya.',
                );
            })
            .finally(() => {
                setLoadingMore(false);
            });
    }, [slug, loading, loadingMore, pagination]);

    return {
        reviews,
        pagination,
        loading,
        loadingMore,
        error,
        hasMore,
        loadMore,
        reload,
    };
}
