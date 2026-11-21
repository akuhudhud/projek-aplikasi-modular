
import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type {
    RunnerAccount,
    RunnerProfileAnalytics,
    RunnerPublicProfile,
} from '../../../src/api';

const {
    mockGetProfile,
    mockSaveProfile,
    mockGetProfileAnalytics,
} = vi.hoisted(() => ({
    mockGetProfile: vi.fn(),
    mockSaveProfile: vi.fn(),
    mockGetProfileAnalytics: vi.fn(),
}));

vi.mock('../../../src/api', () => ({
    runnerApi: {
        getProfile: mockGetProfile,
        saveProfile: mockSaveProfile,
        getProfileAnalytics: mockGetProfileAnalytics,
    },
}));

import { useRunnerProfile } from '../../../src/features/profile/useRunnerProfile';

const account: RunnerAccount = {
    id: 1,
    profile_name: 'Runner Tuaran',
    phone: '0123456789',
    email: 'runner@example.com',
    profile_picture: null,
    role: 'USER',
    status: 'ACTIVE',
    runner_status: 'APPROVED',
    identity_verification_status: 'VERIFIED',
};

const profile: RunnerPublicProfile = {
    profile_id: 'profile-001',
    account_id: 1,
    public_slug: 'runner-tuaran',
    profile_name: 'Runner Tuaran',
    bio: 'Runner kawasan Tuaran.',
    base_location: 'Tuaran',
    service_area: 'Tuaran dan Tamparuli',
    services: ['Penghantaran barang', 'Pembelian barang'],
    is_published: true,
    views_count: 25,
};

const newerProfile: RunnerPublicProfile = {
    ...profile,
    profile_id: 'profile-002',
    public_slug: 'runner-baru',
    profile_name: 'Runner Baru',
    bio: 'Profil terkini.',
    views_count: 40,
};

const analytics: RunnerProfileAnalytics = {
    profile_views: 25,
    requests_from_profile: 8,
    quotations_for_profile_requests: 5,
    completed_tasks_from_profile_requests: 3,
};

const newerAnalytics: RunnerProfileAnalytics = {
    profile_views: 40,
    requests_from_profile: 12,
    quotations_for_profile_requests: 7,
    completed_tasks_from_profile_requests: 4,
};

function submitEvent() {
    return {
        preventDefault: vi.fn(),
    } as unknown as React.FormEvent<HTMLFormElement>;
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;

    const promise = new Promise<T>((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

describe('useRunnerProfile', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        mockGetProfile.mockResolvedValue(profile);
        mockGetProfileAnalytics.mockResolvedValue(analytics);
        mockSaveProfile.mockResolvedValue(profile);
    });

    it('loads the runner profile and analytics', async () => {
        const { result } = renderHook(() => useRunnerProfile(account));

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(mockGetProfile).toHaveBeenCalledOnce();
        expect(mockGetProfileAnalytics).toHaveBeenCalledOnce();
        expect(result.current.profile).toEqual(profile);
        expect(result.current.analytics).toEqual(analytics);
        expect(result.current.publicSlug).toBe('runner-tuaran');
        expect(result.current.bio).toBe('Runner kawasan Tuaran.');
        expect(result.current.baseLocation).toBe('Tuaran');
        expect(result.current.serviceArea).toBe('Tuaran dan Tamparuli');
        expect(result.current.servicesText).toBe(
            'Penghantaran barang, Pembelian barang',
        );
        expect(result.current.isPublished).toBe(true);
        expect(result.current.error).toBe('');
    });

    it('creates an empty profile when no public profile exists', async () => {
        mockGetProfile.mockResolvedValue(null);

        const { result } = renderHook(() => useRunnerProfile(account));

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.profile).toBeNull();
        expect(result.current.publicSlug).toBe('');
        expect(result.current.bio).toBe('');
        expect(result.current.baseLocation).toBe('');
        expect(result.current.serviceArea).toBe('');
        expect(result.current.servicesText).toBe('');
        expect(result.current.isPublished).toBe(false);
        expect(result.current.analytics.profile_views).toBe(0);
        expect(mockGetProfileAnalytics).not.toHaveBeenCalled();
    });

    it('uses profile view count when analytics cannot be loaded', async () => {
        mockGetProfileAnalytics.mockRejectedValue(
            new Error('Analytics unavailable'),
        );

        const { result } = renderHook(() => useRunnerProfile(account));

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.analytics.profile_views).toBe(25);
        expect(result.current.analytics.requests_from_profile).toBe(0);
    });

    it('prevents saving when profile loading fails', async () => {
        mockGetProfile.mockRejectedValue(new Error('Network error'));

        const { result } = renderHook(() => useRunnerProfile(account));

        await waitFor(() => {
            expect(result.current.profileLoadFailed).toBe(true);
        });

        const event = submitEvent();

        await act(async () => {
            await result.current.saveProfile(event);
        });

        expect(event.preventDefault).toHaveBeenCalledOnce();
        expect(mockSaveProfile).not.toHaveBeenCalled();
        expect(result.current.error).toContain(
            'Tidak berjaya mendapatkan profil Runner.',
        );
    });

    it('rejects an invalid public profile slug', async () => {
        mockGetProfile.mockResolvedValue(null);

        const { result } = renderHook(() => useRunnerProfile(account));

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.setPublicSlug('ab');
        });

        await act(async () => {
            await result.current.saveProfile(submitEvent());
        });

        expect(mockSaveProfile).not.toHaveBeenCalled();
        expect(result.current.error).toContain(
            'Pautan profil mesti 3 hingga 50 aksara',
        );
    });

    it('saves the profile with normalized slug and services', async () => {
        mockGetProfile.mockResolvedValue(null);

        const { result } = renderHook(() => useRunnerProfile(account));

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.setPublicSlug('Runner Tuaran');
            result.current.setBio('  Runner kawasan Tuaran.  ');
            result.current.setBaseLocation(' Tuaran ');
            result.current.setServiceArea(' Tuaran dan Tamparuli ');
            result.current.setServicesText(
                'Penghantaran barang, Pembelian barang',
            );
            result.current.setIsPublished(true);
        });

        await act(async () => {
            await result.current.saveProfile(submitEvent());
        });

        expect(mockSaveProfile).toHaveBeenCalledWith({
            public_slug: 'runner-tuaran',
            bio: 'Runner kawasan Tuaran.',
            base_location: 'Tuaran',
            service_area: 'Tuaran dan Tamparuli',
            services: [
                'Penghantaran barang',
                'Pembelian barang',
            ],
            is_published: true,
        });

        expect(result.current.profile).toEqual(profile);
        expect(result.current.success).toBe(
            'Profil Runner berjaya disimpan.',
        );
        expect(result.current.saving).toBe(false);
    });

    it('limits saved services to twenty entries', async () => {
        mockGetProfile.mockResolvedValue(null);

        const { result } = renderHook(() => useRunnerProfile(account));

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        const services = Array.from(
            { length: 25 },
            (_, index) => `Servis ${index + 1}`,
        );

        act(() => {
            result.current.setPublicSlug('runner-tuaran');
            result.current.setServicesText(services.join(','));
        });

        await act(async () => {
            await result.current.saveProfile(submitEvent());
        });

        expect(mockSaveProfile).toHaveBeenCalledWith(
            expect.objectContaining({
                services: services.slice(0, 20),
            }),
        );
    });

    it('shows the API error message when saving fails', async () => {
        mockGetProfile.mockResolvedValue(null);

        mockSaveProfile.mockRejectedValue({
            response: {
                data: {
                    message: 'Nama pautan profil telah digunakan.',
                },
            },
        });

        const { result } = renderHook(() => useRunnerProfile(account));

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.setPublicSlug('runner-tuaran');
        });

        await act(async () => {
            await result.current.saveProfile(submitEvent());
        });

        expect(result.current.error).toBe(
            'Nama pautan profil telah digunakan.',
        );
        expect(result.current.saving).toBe(false);
    });

    it('keeps the newest profile when requests finish out of order', async () => {
        const firstRequest = deferred<RunnerPublicProfile | null>();
        const secondRequest = deferred<RunnerPublicProfile | null>();

        mockGetProfile
            .mockReturnValueOnce(firstRequest.promise)
            .mockReturnValueOnce(secondRequest.promise);

        const { result } = renderHook(() => useRunnerProfile(account));

        await act(async () => {
            await result.current.loadProfile();
        });

        await act(async () => {
            secondRequest.resolve(newerProfile);
        });

        await waitFor(() => {
            expect(result.current.profile).toEqual(newerProfile);
            expect(result.current.publicSlug).toBe('runner-baru');
        });

        await act(async () => {
            firstRequest.resolve(profile);
        });

        expect(result.current.profile).toEqual(newerProfile);
        expect(result.current.publicSlug).toBe('runner-baru');
    });

    it('ignores stale analytics after a newer profile request starts', async () => {
        const firstAnalytics = deferred<RunnerProfileAnalytics>();
        const secondAnalytics = deferred<RunnerProfileAnalytics>();

        mockGetProfile
            .mockResolvedValueOnce(profile)
            .mockResolvedValueOnce(newerProfile);

        mockGetProfileAnalytics
            .mockReturnValueOnce(firstAnalytics.promise)
            .mockReturnValueOnce(secondAnalytics.promise);

        const { result } = renderHook(() => useRunnerProfile(account));

        await waitFor(() => {
            expect(mockGetProfileAnalytics).toHaveBeenCalledTimes(1);
        });

        await act(async () => {
            await result.current.loadProfile();
        });

        await act(async () => {
            secondAnalytics.resolve(newerAnalytics);
        });

        await waitFor(() => {
            expect(result.current.analytics).toEqual(newerAnalytics);
        });

        await act(async () => {
            firstAnalytics.resolve(analytics);
        });

        expect(result.current.analytics).toEqual(newerAnalytics);
        expect(result.current.profile).toEqual(newerProfile);
    });
});
