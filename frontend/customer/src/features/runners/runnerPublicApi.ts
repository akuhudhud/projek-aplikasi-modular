
import axios from 'axios';

export interface RunnerPublicProfileData {
    public_slug: string;
    display_name: string;
    bio: string | null;
    base_location: string | null;
    service_area: string | null;
    services: string[];
    rating_average: number | null;
    rating_count: number;
    views_count: number;
}

export interface RunnerPublicReview {
    rating: number;
    review: string;
    created_at: string;
}

export interface RunnerPublicReviewPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface PublicProfileResponse {
    success: boolean;
    data: {
        profile: RunnerPublicProfileData;
    };
}

interface PublicReviewsResponse {
    success: boolean;
    data: {
        reviews: RunnerPublicReview[];
        pagination: RunnerPublicReviewPagination;
    };
}

export const runnerPublicApi = {
    async getProfile(
        slug: string,
    ): Promise<RunnerPublicProfileData> {
        const response = await axios.get<PublicProfileResponse>(
            `/api/runners/${encodeURIComponent(slug.toLowerCase())}`,
            {
                baseURL: import.meta.env.VITE_API_BASE_URL ?? '/',
                withCredentials: true,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        return response.data.data.profile;
    },

    async getReviews(
        slug: string,
        page = 1,
    ): Promise<PublicReviewsResponse['data']> {
        const response = await axios.get<PublicReviewsResponse>(
            `/api/runners/${encodeURIComponent(slug.toLowerCase())}/reviews`,
            {
                params: { page },
                baseURL: import.meta.env.VITE_API_BASE_URL ?? '/',
                withCredentials: true,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        return response.data.data;
    },
};
