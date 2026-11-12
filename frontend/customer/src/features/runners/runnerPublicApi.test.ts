
import {
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

const { getMock } = vi.hoisted(() => ({
    getMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        get: getMock,
    },
}));

import { runnerPublicApi } from './runnerPublicApi';

const profile = {
    public_slug: 'runner-tuaran',
    profile_name: 'Runner Tuaran',
    bio: 'Servis penghantaran sekitar Tuaran.',
    base_location: 'Tuaran',
    service_area: 'Tuaran',
    services: ['Penghantaran parcel'],
    rating_average: 4.8,
    rating_count: 12,
    views_count: 35,
};

const reviewsData = {
    reviews: [
        {
            rating: 5,
            review: 'Servis sangat baik.',
            created_at: '2026-11-10T10:00:00.000000Z',
        },
    ],
    pagination: {
        current_page: 1,
        last_page: 2,
        per_page: 20,
        total: 21,
    },
};

describe('runnerPublicApi.getProfile', () => {
    beforeEach(() => {
        getMock.mockReset();
    });

    it('requests the public profile using a lowercase slug', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    profile,
                },
            },
        });

        await runnerPublicApi.getProfile('RUNNER-TUARAN');

        expect(getMock).toHaveBeenCalledWith(
            '/api/runners/runner-tuaran',
            {
                baseURL: '/',
                withCredentials: true,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
    });

    it('returns the profile from the API response', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    profile,
                },
            },
        });

        const result = await runnerPublicApi.getProfile(
            'runner-tuaran',
        );

        expect(result).toEqual(profile);
        expect(result.profile_name).toBe('Runner Tuaran');
        expect(result).not.toHaveProperty('display_name');
        expect(result).not.toHaveProperty('verified_name');
    });

    it('encodes special characters in the slug', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    profile,
                },
            },
        });

        await runnerPublicApi.getProfile('runner/tuaran');

        expect(getMock).toHaveBeenCalledWith(
            '/api/runners/runner%2Ftuaran',
            expect.any(Object),
        );
    });

    it('propagates profile API errors', async () => {
        const apiError = new Error('Profile request failed.');

        getMock.mockRejectedValue(apiError);

        await expect(
            runnerPublicApi.getProfile('runner-tuaran'),
        ).rejects.toBe(apiError);
    });
});

describe('runnerPublicApi.getReviews', () => {
    beforeEach(() => {
        getMock.mockReset();
    });

    it('requests the first review page by default', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: reviewsData,
            },
        });

        await runnerPublicApi.getReviews('RUNNER-TUARAN');

        expect(getMock).toHaveBeenCalledWith(
            '/api/runners/runner-tuaran/reviews',
            {
                params: {
                    page: 1,
                },
                baseURL: '/',
                withCredentials: true,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
    });

    it('requests the selected review page', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: reviewsData,
            },
        });

        await runnerPublicApi.getReviews(
            'runner-tuaran',
            3,
        );

        expect(getMock).toHaveBeenCalledWith(
            '/api/runners/runner-tuaran/reviews',
            expect.objectContaining({
                params: {
                    page: 3,
                },
            }),
        );
    });

    it('returns reviews and pagination from the API response', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: reviewsData,
            },
        });

        const result = await runnerPublicApi.getReviews(
            'runner-tuaran',
            2,
        );

        expect(result).toEqual(reviewsData);
    });

    it('encodes special characters in the slug', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: reviewsData,
            },
        });

        await runnerPublicApi.getReviews(
            'runner/tuaran',
            2,
        );

        expect(getMock).toHaveBeenCalledWith(
            '/api/runners/runner%2Ftuaran/reviews',
            expect.any(Object),
        );
    });

    it('propagates review API errors', async () => {
        const apiError = new Error('Review request failed.');

        getMock.mockRejectedValue(apiError);

        await expect(
            runnerPublicApi.getReviews('runner-tuaran'),
        ).rejects.toBe(apiError);
    });
});
