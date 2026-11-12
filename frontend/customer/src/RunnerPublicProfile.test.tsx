
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
    waitFor,
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
    profile_name: 'Runner Tuaran',
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

function mockSuccessfulProfile() {
    getMock.mockResolvedValue(successfulProfileResponse());
}

describe('RunnerPublicProfile', () => {
    beforeEach(() => {
        getMock.mockReset();
        isAxiosErrorMock.mockClear();

        window.history.replaceState({}, '', '/me/test-runner');

        Object.defineProperty(window.navigator, 'share', {
            configurable: true,
            value: undefined,
        });

        Object.defineProperty(window.navigator, 'clipboard', {
            configurable: true,
            value: {
                writeText: vi.fn().mockResolvedValue(undefined),
            },
        });
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('loads the public profile using the normalized slug', async () => {
        mockSuccessfulProfile();

        render(<RunnerPublicProfile slug="TEST-RUNNER" />);

        expect(
            await screen.findByRole('heading', {
                name: 'Runner Tuaran',
            }),
        ).toBeTruthy();

        expect(getMock).toHaveBeenCalledTimes(1);

        expect(getMock).toHaveBeenCalledWith(
            '/api/runners/test-runner',
            expect.objectContaining({
                baseURL: '/',
                withCredentials: true,
            }),
        );
    });

    it('displays public profile information and location fields', async () => {
        mockSuccessfulProfile();

        render(<RunnerPublicProfile slug="test-runner" />);

        await screen.findByRole('heading', {
            name: 'Runner Tuaran',
        });

        expect(
            screen.getByText('Servis penghantaran sekitar Tuaran.'),
        ).toBeTruthy();

        expect(
            screen.getByText('Penghantaran parcel'),
        ).toBeTruthy();

        expect(
            screen.getByText('Penghantaran dokumen'),
        ).toBeTruthy();

        expect(screen.getByText('Lokasi Asas:')).toBeTruthy();
        expect(screen.getByText('Tuaran')).toBeTruthy();

        expect(screen.getByText('Kawasan Liputan:')).toBeTruthy();

        expect(
            screen.getByText('Tuaran - Kota Kinabalu sahaja'),
        ).toBeTruthy();

        expect(screen.getByText('★ 4.8 / 5')).toBeTruthy();
        expect(screen.getByText('(12 penilaian)')).toBeTruthy();
    });

    it('displays the runner avatar using the first letter of the profile name', async () => {
        mockSuccessfulProfile();

        render(<RunnerPublicProfile slug="test-runner" />);

        await screen.findByRole('heading', {
            name: 'Runner Tuaran',
        });

        expect(screen.getByText('R')).toBeTruthy();
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

        render(<RunnerPublicProfile slug="test-runner" />);

        expect(
            await screen.findByText('Belum dinilai'),
        ).toBeTruthy();

        expect(screen.getByText('(0 penilaian)')).toBeTruthy();
    });

    it('does not display written reviews inside the public profile', async () => {
        mockSuccessfulProfile();

        render(<RunnerPublicProfile slug="test-runner" />);

        await screen.findByRole('heading', {
            name: 'Runner Tuaran',
        });

        expect(
            screen.queryByText('Runner sangat baik dan penghantaran cepat.'),
        ).toBeNull();

        expect(
            screen.queryByRole('button', {
                name: 'Lihat Lebih Banyak Ulasan',
            }),
        ).toBeNull();

        expect(
            screen.queryByText('Belum ada ulasan bertulis daripada pelanggan.'),
        ).toBeNull();
    });

    it('provides a separate link to the runner reviews page', async () => {
        mockSuccessfulProfile();

        render(<RunnerPublicProfile slug="TEST-RUNNER" />);

        await screen.findByRole('heading', {
            name: 'Runner Tuaran',
        });

        const reviewsLink = screen.getByRole('link', {
            name: 'Lihat Ulasan',
        });

        expect(reviewsLink.getAttribute('href')).toBe(
            '/me/test-runner/ulasan',
        );
    });

    it('opens the request form with the runner slug', async () => {
        mockSuccessfulProfile();

        render(<RunnerPublicProfile slug="TEST-RUNNER" />);

        await screen.findByRole('heading', {
            name: 'Runner Tuaran',
        });

        const locationSetter = vi.fn();

        Object.defineProperty(window, 'location', {
            configurable: true,
            value: {
                ...window.location,
                set href(value: string) {
                    locationSetter(value);
                },
                get href() {
                    return '/me/test-runner';
                },
                origin: 'http://localhost',
            },
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Buat Permintaan',
            }),
        );

        expect(locationSetter).toHaveBeenCalledWith(
            '/?runner=test-runner',
        );
    });

    it('copies the public profile link when native sharing is unavailable', async () => {
        mockSuccessfulProfile();

        const writeText = vi.fn().mockResolvedValue(undefined);

        Object.defineProperty(window.navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });

        render(<RunnerPublicProfile slug="test-runner" />);

        await screen.findByRole('heading', {
            name: 'Runner Tuaran',
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Kongsi Profil',
            }),
        );

        await waitFor(() => {
            expect(writeText).toHaveBeenCalledWith(
                'http://localhost/me/test-runner',
            );
        });
    });

    it('displays not found when the public profile does not exist', async () => {
        getMock.mockRejectedValue({
            isAxiosError: true,
            response: {
                status: 404,
            },
        });

        render(<RunnerPublicProfile slug="missing-runner" />);

        expect(
            await screen.findByRole('heading', {
                name: 'Profil Tidak Ditemui',
            }),
        ).toBeTruthy();
    });

    it('displays an error when the profile request fails', async () => {
        getMock.mockRejectedValue(new Error('Network error'));

        render(<RunnerPublicProfile slug="test-runner" />);

        expect(
            await screen.findByRole('heading', {
                name: 'Ralat',
            }),
        ).toBeTruthy();

        expect(screen.getByRole('alert')).toBeTruthy();
    });

    it('retries loading the profile after clicking Cuba Semula', async () => {
        getMock
            .mockRejectedValueOnce(new Error('Network error'))
            .mockResolvedValueOnce(successfulProfileResponse());

        render(<RunnerPublicProfile slug="test-runner" />);

        expect(
            await screen.findByRole('heading', {
                name: 'Ralat',
            }),
        ).toBeTruthy();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Cuba Semula',
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
