
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
    getProfileMock,
    isAxiosErrorMock,
} = vi.hoisted(() => ({
    getProfileMock: vi.fn(),
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
        isAxiosError: isAxiosErrorMock,
    },
}));

vi.mock('./runnerPublicApi', () => ({
    runnerPublicApi: {
        getProfile: getProfileMock,
    },
}));

import { useRunnerPublicProfile } from './useRunnerPublicProfile';

const profile = {
    public_slug: 'test-runner',
    profile_name: 'Runner Tuaran',
    bio: 'Servis penghantaran sekitar Tuaran.',
    base_location: 'Tuaran',
    service_area: 'Tuaran dan kawasan sekitar',
    services: [
        'Penghantaran parcel',
        'Penghantaran dokumen',
    ],
    rating_average: 4.8,
    rating_count: 12,
    views_count: 35,
};

describe('useRunnerPublicProfile', () => {
    beforeEach(() => {
        getProfileMock.mockReset();
        isAxiosErrorMock.mockClear();
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('loads the public runner profile when initialized', async () => {
        getProfileMock.mockResolvedValue(profile);

        const { result } = renderHook(() =>
            useRunnerPublicProfile('test-runner'),
        );

        expect(result.current.loading).toBe(true);

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(getProfileMock).toHaveBeenCalledWith(
            'test-runner',
        );

        expect(result.current.profile).toEqual(profile);
        expect(result.current.notFound).toBe(false);
        expect(result.current.error).toBe('');
    });

    it('sets notFound when the runner profile returns 404', async () => {
        getProfileMock.mockRejectedValue({
            isAxiosError: true,
            response: {
                status: 404,
            },
        });

        const { result } = renderHook(() =>
            useRunnerPublicProfile('missing-runner'),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.profile).toBeNull();
        expect(result.current.notFound).toBe(true);
        expect(result.current.error).toBe('');
    });

    it('sets an error when the profile request fails', async () => {
        getProfileMock.mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() =>
            useRunnerPublicProfile('test-runner'),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.profile).toBeNull();
        expect(result.current.notFound).toBe(false);

        expect(result.current.error).toBe(
            'Tidak berjaya memuatkan profil Runner. Sila cuba lagi.',
        );
    });

    it('reloads the profile when reload is called', async () => {
        const updatedProfile = {
            ...profile,
            profile_name: 'Runner Tuaran Baharu',
        };

        getProfileMock
            .mockResolvedValueOnce(profile)
            .mockResolvedValueOnce(updatedProfile);

        const { result } = renderHook(() =>
            useRunnerPublicProfile('test-runner'),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.profile).toEqual(profile);

        act(() => {
            result.current.reload();
        });

        await waitFor(() => {
            expect(getProfileMock).toHaveBeenCalledTimes(2);
        });

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(getProfileMock).toHaveBeenLastCalledWith(
            'test-runner',
        );

        expect(result.current.profile).toEqual(updatedProfile);
    });

    it('loads another profile when the slug changes', async () => {
        getProfileMock.mockResolvedValue(profile);

        const { result, rerender } = renderHook(
            ({ slug }) => useRunnerPublicProfile(slug),
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
            expect(getProfileMock).toHaveBeenLastCalledWith(
                'runner-two',
            );
        });

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(getProfileMock).toHaveBeenCalledTimes(2);
    });

    it('clears the previous profile when the slug changes', async () => {
        getProfileMock.mockResolvedValue(profile);

        const { result, rerender } = renderHook(
            ({ slug }) => useRunnerPublicProfile(slug),
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

        expect(result.current.profile).toBeNull();
        expect(result.current.loading).toBe(true);
        expect(result.current.notFound).toBe(false);
        expect(result.current.error).toBe('');
    });
});
