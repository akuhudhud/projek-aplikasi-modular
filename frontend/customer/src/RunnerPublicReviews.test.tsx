
 // @vitest-environment jsdom

import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import {
    cleanup,
    fireEvent,
    render,
    screen,
} from '@testing-library/react';

const {
    useRunnerPublicReviewsMock,
    reloadMock,
    loadMoreMock,
} = vi.hoisted(() => ({
    useRunnerPublicReviewsMock: vi.fn(),
    reloadMock: vi.fn(),
    loadMoreMock: vi.fn(),
}));

vi.mock('./features/runners/useRunnerPublicReviews', () => ({
    useRunnerPublicReviews: useRunnerPublicReviewsMock,
}));

import RunnerPublicReviews from './RunnerPublicReviews';

const reviews = [
    {
        rating: 5,
        review: 'Runner sangat baik dan penghantaran cepat.',
        created_at: '2026-11-10T10:00:00.000Z',
    },
    {
        rating: 4,
        review: 'Servis memuaskan dan mudah berurusan.',
        created_at: '2026-11-09T08:30:00.000Z',
    },
];

function mockReviews(overrides = {}) {
    useRunnerPublicReviewsMock.mockReturnValue({
        reviews,
        pagination: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 2,
        },
        loading: false,
        loadingMore: false,
        error: '',
        hasMore: false,
        loadMore: loadMoreMock,
        reload: reloadMock,
        ...overrides,
    });
}

describe('RunnerPublicReviews', () => {
    beforeEach(() => {
        useRunnerPublicReviewsMock.mockReset();
        reloadMock.mockReset();
        loadMoreMock.mockReset();

        mockReviews();
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('loads reviews using the supplied runner slug', () => {
        render(<RunnerPublicReviews slug="test-runner" />);

        expect(useRunnerPublicReviewsMock).toHaveBeenCalledWith(
            'test-runner',
        );
    });

    it('displays the reviews page heading and total count', () => {
        render(<RunnerPublicReviews slug="test-runner" />);

        expect(
            screen.getByRole('heading', {
                name: 'Ulasan Pelanggan',
            }),
        ).toBeTruthy();

        expect(screen.getByText('2 ulasan bertulis')).toBeTruthy();
    });

    it('displays written reviews and anonymous reviewer identity', () => {
        render(<RunnerPublicReviews slug="test-runner" />);

        expect(
            screen.getByText(
                'Runner sangat baik dan penghantaran cepat.',
            ),
        ).toBeTruthy();

        expect(
            screen.getByText(
                'Servis memuaskan dan mudah berurusan.',
            ),
        ).toBeTruthy();

        expect(
            screen.getAllByText('Pelanggan tanpa nama'),
        ).toHaveLength(2);
    });

    it('displays the star rating for each review', () => {
        render(<RunnerPublicReviews slug="test-runner" />);

        expect(
            screen.getByLabelText('5 daripada 5 bintang'),
        ).toBeTruthy();

        expect(
            screen.getByLabelText('4 daripada 5 bintang'),
        ).toBeTruthy();
    });

    it('displays an empty state when there are no written reviews', () => {
        mockReviews({
            reviews: [],
            pagination: {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: 0,
            },
        });

        render(<RunnerPublicReviews slug="test-runner" />);

        expect(
            screen.getByText(
                'Belum ada ulasan bertulis daripada pelanggan.',
            ),
        ).toBeTruthy();
    });

    it('displays a loading state when reviews are being fetched', () => {
        mockReviews({
            reviews: [],
            pagination: null,
            loading: true,
        });

        render(<RunnerPublicReviews slug="test-runner" />);

        expect(
            screen.getByText(
                'Sedang memuatkan ulasan pelanggan...',
            ),
        ).toBeTruthy();
    });

    it('loads more reviews when the button is clicked', () => {
        mockReviews({
            hasMore: true,
        });

        render(<RunnerPublicReviews slug="test-runner" />);

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Lebih Banyak Ulasan',
            }),
        );

        expect(loadMoreMock).toHaveBeenCalledTimes(1);
    });

    it('shows loading text while more reviews are being fetched', () => {
        mockReviews({
            hasMore: true,
            loadingMore: true,
        });

        render(<RunnerPublicReviews slug="test-runner" />);

        expect(
            screen.getByRole('button', {
                name: 'Sedang memuatkan...',
            }),
        ).toBeTruthy();
    });

    it('does not show the load more button when there are no more reviews', () => {
        mockReviews({
            hasMore: false,
        });

        render(<RunnerPublicReviews slug="test-runner" />);

        expect(
            screen.queryByRole('button', {
                name: 'Lihat Lebih Banyak Ulasan',
            }),
        ).toBeNull();
    });

    it('displays an error and allows the user to retry', () => {
        mockReviews({
            error: 'Tidak dapat memuatkan ulasan.',
        });

        render(<RunnerPublicReviews slug="test-runner" />);

        expect(
            screen.getByRole('alert'),
        ).toBeTruthy();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Cuba Semula',
            }),
        );

        expect(reloadMock).toHaveBeenCalledTimes(1);
    });

    it('provides a link back to the runner profile', () => {
        render(<RunnerPublicReviews slug="TEST-RUNNER" />);

        const backLink = screen.getByRole('link', {
            name: 'Kembali ke Profil Runner',
        });

        expect(backLink.getAttribute('href')).toBe(
            '/me/test-runner',
        );
    });
});
