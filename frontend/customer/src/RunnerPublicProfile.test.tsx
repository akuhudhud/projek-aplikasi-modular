
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

const { getMock, isAxiosErrorMock } = vi.hoisted(() => ({
    getMock: vi.fn(),
    isAxiosErrorMock: vi.fn(
        (error: unknown) =>
            Boolean(
                error &&
                typeof error === 'object' &&
                'isAxiosError' in error &&
                error.isAxiosError,
            ),
    ),
}));

vi.mock('axios', () => ({
    default: {
        get: getMock,
        isAxiosError: isAxiosErrorMock,
    },
}));

import RunnerPublicProfile from './RunnerPublicProfile';

const profile = {
    public_slug: 'test-runner',
    display_name: 'Runner Tuaran',
    bio: 'Servis penghantaran sekitar Tuaran.',
    base_location: 'Tuaran',
    service_area: 'Tuaran - Kota Kinabalu sahaja',
    services: [
        'Penghantaran parcel',
        'Penghantaran dokumen',
    ],
    rating_average: 4.8,
    rating_count: 12,
    views_count: 35,
};

const firstReview = {
    rating: 5,
    review: 'Runner sangat baik dan penghantaran cepat.',
    created_at: '2026-11-10T10:00:00.000000Z',
};

const secondReview = {
    rating: 4,
    review: 'Servis memuaskan.',
    created_at: '2026-11-09T10:00:00.000000Z',
};

function successfulProfileResponse() {
    return {
        data: {
            success: true,
            data: {
                profile,
            },
        },
    };
}

function successfulReviewsResponse(
    reviews: Array<{
        rating: number;
        review: string;
        created_at: string;
    }> = [],
    currentPage = 1,
    lastPage = 1,
    total = reviews.length,
) {
    return {
        data: {
            success: true,
            data: {
                reviews,
                pagination: {
                    current_page: currentPage,
                    last_page: lastPage,
                    per_page: 20,
                    total,
                },
            },
        },
    };
}

function mockSuccessfulRequests() {
    getMock.mockImplementation((url: string) => {
        if (url.includes('/reviews')) {
            return Promise.resolve(
                successfulReviewsResponse(),
            );
        }

        return Promise.resolve(
            successfulProfileResponse(),
        );
    });
}

describe('RunnerPublicProfile', () => {
    beforeEach(() => {
        getMock.mockReset();
        isAxiosErrorMock.mockClear();
    });

    afterEach(() => {
        cleanup();
    });

    it('loads the public profile using the normalized slug', async () => {
        mockSuccessfulRequests();

        render(
            <RunnerPublicProfile slug="TEST-RUNNER" />,
        );

        expect(
            await screen.findByRole('heading', {
                name: 'Runner Tuaran',
            }),
        ).toBeTruthy();

        expect(getMock).toHaveBeenCalledWith(
            '/api/runners/test-runner',
            expect.objectContaining({
                baseURL: '/',
                withCredentials: true,
            }),
        );
    });

    it('displays public profile information and exactly two location fields', async () => {
        mockSuccessfulRequests();

        render(
            <RunnerPublicProfile slug="test-runner" />,
        );

        await screen.findByRole('heading', {
            name: 'Runner Tuaran',
        });

        expect(
            screen.getByText(
                'Servis penghantaran sekitar Tuaran.',
            ),
        ).toBeTruthy();

        expect(
            screen.getByText('Penghantaran parcel'),
        ).toBeTruthy();

        expect(
            screen.getByText('Penghantaran dokumen'),
        ).toBeTruthy();

        expect(
            screen.getByText('Base:'),
        ).toBeTruthy();

        expect(
            screen.getByText('Tuaran'),
        ).toBeTruthy();

        expect(
            screen.getByText('Kawasan Operasi:'),
        ).toBeTruthy();

        expect(
            screen.getByText('Tuaran - Kota Kinabalu sahaja'),
        ).toBeTruthy();

        expect(
            screen.getByText('★ 4.8 / 5'),
        ).toBeTruthy();

        expect(
            screen.getByRole('button', {
                name: 'Minta Servis Ini',
            }),
        ).toBeTruthy();
    });

    it('displays an empty rating state when no ratings exist', async () => {
        mockSuccessfulRequests();

        getMock.mockImplementation((url: string) => {
            if (url.includes('/reviews')) {
                return Promise.resolve(
                    successfulReviewsResponse(),
                );
            }

            return Promise.resolve({
                data: {
                    success: true,
                    data: {
                        profile: {
                            ...profile,
                            rating_average: null,
                            rating_count: 0,
                        },
                    },
                },
            });
        });

        render(
            <RunnerPublicProfile slug="test-runner" />,
        );

        expect(
            await screen.findByText('Belum dinilai'),
        ).toBeTruthy();

        expect(
            screen.getByText('(0 penilaian)'),
        ).toBeTruthy();
    });

    it('displays anonymous written reviews without reviewer identity', async () => {
        getMock.mockImplementation((url: string) => {
            if (url.includes('/reviews')) {
                return Promise.resolve(
                    successfulReviewsResponse(
                        [firstReview],
                    ),
                );
            }

            return Promise.resolve(
                successfulProfileResponse(),
            );
        });

        render(
            <RunnerPublicProfile slug="test-runner" />,
        );

        expect(
            await screen.findByText(
                'Runner sangat baik dan penghantaran cepat.',
            ),
        ).toBeTruthy();

        expect(
            screen.getByText('1 ulasan bertulis'),
        ).toBeTruthy();

        expect(
            screen.getByLabelText('5 daripada 5 bintang'),
        ).toBeTruthy();

        expect(
            screen.queryByText(/nama pelanggan/i),
        ).toBeNull();
    });

    it('displays an empty written reviews state', async () => {
        mockSuccessfulRequests();

        render(
            <RunnerPublicProfile slug="test-runner" />,
        );

        expect(
            await screen.findByText(
                'Belum ada ulasan bertulis daripada pelanggan.',
            ),
        ).toBeTruthy();
    });

    it('loads the next page of written reviews', async () => {
        getMock.mockImplementation(
            (
                url: string,
                config?: {
                    params?: {
                        page?: number;
                    };
                },
            ) => {
                if (url.includes('/reviews')) {
                    if (config?.params?.page === 2) {
                        return Promise.resolve(
                            successfulReviewsResponse(
                                [secondReview],
                                2,
                                2,
                                2,
                            ),
                        );
                    }

                    return Promise.resolve(
                        successfulReviewsResponse(
                            [firstReview],
                            1,
                            2,
                            2,
                        ),
                    );
                }

                return Promise.resolve(
                    successfulProfileResponse(),
                );
            },
        );

        render(
            <RunnerPublicProfile slug="test-runner" />,
        );

        expect(
            await screen.findByText(
                'Runner sangat baik dan penghantaran cepat.',
            ),
        ).toBeTruthy();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat Lagi Ulasan',
            }),
        );

        expect(
            await screen.findByText('Servis memuaskan.'),
        ).toBeTruthy();

        expect(getMock).toHaveBeenCalledWith(
            '/api/runners/test-runner/reviews',
            expect.objectContaining({
                params: {
                    page: 2,
                },
            }),
        );
    });

    it('displays not found when the public profile does not exist', async () => {
        getMock.mockImplementation((url: string) => {
            if (url.includes('/reviews')) {
                return Promise.resolve(
                    successfulReviewsResponse(),
                );
            }

            return Promise.reject({
                isAxiosError: true,
                response: {
                    status: 404,
                },
            });
        });

        render(
            <RunnerPublicProfile slug="missing-runner" />,
        );

        expect(
            await screen.findByRole('heading', {
                name: 'Profil tidak ditemui',
            }),
        ).toBeTruthy();
    });

    it('displays an error when the profile request fails', async () => {
        getMock.mockImplementation((url: string) => {
            if (url.includes('/reviews')) {
                return Promise.resolve(
                    successfulReviewsResponse(),
                );
            }

            return Promise.reject(
                new Error('Network error'),
            );
        });

        render(
            <RunnerPublicProfile slug="test-runner" />,
        );

        expect(
            await screen.findByRole('heading', {
                name: 'Ralat',
            }),
        ).toBeTruthy();

        expect(
            screen.getByRole('alert'),
        ).toBeTruthy();
    });

    it('retries loading the profile after clicking Cuba Lagi', async () => {
        let profileAttempts = 0;

        getMock.mockImplementation((url: string) => {
            if (url.includes('/reviews')) {
                return Promise.resolve(
                    successfulReviewsResponse(),
                );
            }

            profileAttempts += 1;

            if (profileAttempts === 1) {
                return Promise.reject(
                    new Error('Network error'),
                );
            }

            return Promise.resolve(
                successfulProfileResponse(),
            );
        });

        render(
            <RunnerPublicProfile slug="test-runner" />,
        );

        expect(
            await screen.findByRole('heading', {
                name: 'Ralat',
            }),
        ).toBeTruthy();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Cuba Lagi',
            }),
        );

        expect(
            await screen.findByRole('heading', {
                name: 'Runner Tuaran',
            }),
        ).toBeTruthy();

        expect(profileAttempts).toBe(2);
    });
});
