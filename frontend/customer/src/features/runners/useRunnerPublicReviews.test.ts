
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import {
    act,
    cleanup,
    renderHook,
    waitFor,
} from '@testing-library/react';

const {
    getReviewsMock,
} = vi.hoisted(() => ({
    getReviewsMock: vi.fn(),
}));

vi.mock('./runnerPublicApi', () => ({
    runnerPublicApi: {
        getReviews: getReviewsMock,
    },
}));

import { useRunnerPublicReviews } from './useRunnerPublicReviews';

const firstPage = {
    reviews: [
        {
            rating: 5,
            review: 'Servis sangat baik.',
            created_at: '2026-11-10T10:00:00.000Z',
        },
    ],
    pagination: {
        current_page: 1,
        last_page: 2,
        per_page: 1,
        total: 2,
    },
};

const secondPage = {
    reviews: [
        {
            rating: 4,
            review: 'Penghantaran memuaskan.',
            created_at: '2026-11-09T10:00:00.000Z',
        },
    ],
    pagination: {
        current_page: 2,
        last_page: 2,
        per_page: 1,
        total: 2,
    },
};

describe('useRunnerPublicReviews', () => {
    beforeEach(() => {
        getReviewsMock.mockReset();
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('loads the first review page when initialized', async () => {
        getReviewsMock.mockResolvedValue(firstPage);

        const { result } = renderHook(() =>
            useRunnerPublicReviews('test-runner'),
        );

        expect(result.current.loading).toBe(true);

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(getReviewsMock).toHaveBeenCalledWith(
            'test-runner',
            1,
        );

        expect(result.current.reviews).toEqual(
            firstPage.reviews,
        );

        expect(result.current.pagination).toEqual(
            firstPage.pagination,
        );
    });

    it('sets hasMore when additional pages are available', async () => {
        getReviewsMock.mockResolvedValue(firstPage);

        const { result } = renderHook(() =>
            useRunnerPublicReviews('test-runner'),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.hasMore).toBe(true);
    });

    it('loads and appends the next review page', async () => {
        getReviewsMock
            .mockResolvedValueOnce(firstPage)
            .mockResolvedValueOnce(secondPage);

        const { result } = renderHook(() =>
            useRunnerPublicReviews('test-runner'),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            result.current.loadMore();
        });

        await waitFor(() => {
            expect(result.current.loadingMore).toBe(false);
        });

        expect(getReviewsMock).toHaveBeenNthCalledWith(
            2,
            'test-runner',
            2,
        );

        expect(result.current.reviews).toHaveLength(2);

        expect(result.current.reviews).toEqual([
            ...firstPage.reviews,
            ...secondPage.reviews,
        ]);

        expect(result.current.pagination?.current_page).toBe(2);
        expect(result.current.hasMore).toBe(false);
    });

    it('does not load another page when no more pages exist', async () => {
        getReviewsMock.mockResolvedValue(secondPage);

        const { result } = renderHook(() =>
            useRunnerPublicReviews('test-runner'),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.hasMore).toBe(false);

        await act(async () => {
            result.current.loadMore();
        });

        expect(getReviewsMock).toHaveBeenCalledTimes(1);
    });

    it('sets an error when the first page request fails', async () => {
        getReviewsMock.mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() =>
            useRunnerPublicReviews('test-runner'),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.error).toBe(
            'Tidak berjaya memuatkan ulasan Runner. Sila cuba lagi.',
        );

        expect(result.current.reviews).toEqual([]);
    });

    it('sets an error when loading the next page fails', async () => {
        getReviewsMock
            .mockResolvedValueOnce(firstPage)
            .mockRejectedValueOnce(new Error('Network error'));

        const { result } = renderHook(() =>
            useRunnerPublicReviews('test-runner'),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            result.current.loadMore();
        });

        await waitFor(() => {
            expect(result.current.loadingMore).toBe(false);
        });

        expect(result.current.error).toBe(
            'Tidak berjaya memuatkan ulasan seterusnya.',
        );

        expect(result.current.reviews).toEqual(
            firstPage.reviews,
        );
    });

    it('reloads the first page when reload is called', async () => {
        getReviewsMock
            .mockResolvedValueOnce(firstPage)
            .mockResolvedValueOnce(secondPage);

        const { result } = renderHook(() =>
            useRunnerPublicReviews('test-runner'),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.reload();
        });

        await waitFor(() => {
            expect(getReviewsMock).toHaveBeenCalledTimes(2);
        });

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(getReviewsMock).toHaveBeenLastCalledWith(
            'test-runner',
            1,
        );

        expect(result.current.reviews).toEqual(
            secondPage.reviews,
        );
    });

    it('loads reviews again when the runner slug changes', async () => {
        getReviewsMock.mockResolvedValue(firstPage);

        const { result, rerender } = renderHook(
            ({ slug }) => useRunnerPublicReviews(slug),
            {
                initialProps: {
                    slug: 'runner-one',
                },
            },
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        rerender({
            slug: 'runner-two',
        });

        await waitFor(() => {
            expect(getReviewsMock).toHaveBeenLastCalledWith(
                'runner-two',
                1,
            );
        });
    });
});
