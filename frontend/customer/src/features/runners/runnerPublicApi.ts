
import axios from 'axios';

export interface RunnerPublicProfileData {
    public_slug: string;
    display_name: string;
    bio: string | null;

    // Base lokasi Runner.
    base_location: string | null;

    // Kawasan operasi Runner.
    service_area: string | null;

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
};
