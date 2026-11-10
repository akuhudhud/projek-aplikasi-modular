
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
    service_area: 'Tuaran',
    base_location: 'Tuaran, Sabah',
    coverage_area: 'Tuaran dan kawasan berhampiran',
    services: [
        'Penghantaran parcel',
        'Penghantaran dokumen',
    ],
    rating_average: 4.8,
    rating_count: 12,
    views_count: 35,
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

describe('RunnerPublicProfile', () => {
    beforeEach(() => {
        getMock.mockReset();
        isAxiosErrorMock.mockClear();
    });

    afterEach(() => {
        cleanup();
    });

    it('loads the public profile using the normalized slug', async () => {
        getMock.mockResolvedValue(successfulProfileResponse());

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

    it('displays public profile information', async () => {
        getMock.mockResolvedValue(successfulProfileResponse());

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
            screen.getByText('Tuaran, Sabah'),
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
        getMock.mockResolvedValue({
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

    it('displays not found when the public profile does not exist', async () => {
        getMock.mockRejectedValue({
            isAxiosError: true,
            response: {
                status: 404,
            },
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
        getMock.mockRejectedValue(
            new Error('Network error'),
        );

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
        getMock
            .mockRejectedValueOnce(
                new Error('Network error'),
            )
            .mockResolvedValueOnce(
                successfulProfileResponse(),
            );

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

        expect(getMock).toHaveBeenCalledTimes(2);
    });
});
