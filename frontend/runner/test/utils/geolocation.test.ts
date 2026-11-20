
import { afterEach, describe, expect, it, vi } from 'vitest';

import {
    getCurrentCoordinates,
    RunnerGeolocationError,
} from '../../src/utils/geolocation';

function createPositionError(code: number): GeolocationPositionError {
    return {
        code,
        message: 'Geolocation error',
        PERMISSION_DENIED: 1,
        POSITION_UNAVAILABLE: 2,
        TIMEOUT: 3,
    } as GeolocationPositionError;
}

function mockGeolocation(
    implementation: (
        success: PositionCallback,
        failure: PositionErrorCallback,
        options?: PositionOptions,
    ) => void,
) {
    const getCurrentPosition = vi.fn(implementation);

    vi.stubGlobal('navigator', {
        geolocation: {
            getCurrentPosition,
        },
    });

    return getCurrentPosition;
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('getCurrentCoordinates', () => {
    it('returns coordinates when location is available', async () => {
        mockGeolocation((success) => {
            success({
                coords: {
                    latitude: 5.978,
                    longitude: 116.075,
                },
            } as GeolocationPosition);
        });

        await expect(getCurrentCoordinates()).resolves.toEqual({
            latitude: 5.978,
            longitude: 116.075,
        });
    });

    it('returns UNSUPPORTED when browser has no geolocation support', async () => {
        vi.stubGlobal('navigator', {});

        await expect(getCurrentCoordinates()).rejects.toMatchObject({
            name: 'RunnerGeolocationError',
            code: 'UNSUPPORTED',
        });
    });

    it('returns PERMISSION_DENIED when location access is rejected', async () => {
        mockGeolocation((_success, failure) => {
            failure(createPositionError(1));
        });

        await expect(getCurrentCoordinates()).rejects.toMatchObject({
            name: 'RunnerGeolocationError',
            code: 'PERMISSION_DENIED',
        });
    });

    it('returns POSITION_UNAVAILABLE when location cannot be obtained', async () => {
        mockGeolocation((_success, failure) => {
            failure(createPositionError(2));
        });

        await expect(getCurrentCoordinates()).rejects.toMatchObject({
            name: 'RunnerGeolocationError',
            code: 'POSITION_UNAVAILABLE',
        });
    });

    it('returns TIMEOUT when location request expires', async () => {
        mockGeolocation((_success, failure) => {
            failure(createPositionError(3));
        });

        await expect(getCurrentCoordinates()).rejects.toMatchObject({
            name: 'RunnerGeolocationError',
            code: 'TIMEOUT',
        });
    });

    it('uses high accuracy and a 20-second timeout', async () => {
        const getCurrentPosition = mockGeolocation((success) => {
            success({
                coords: {
                    latitude: 5.978,
                    longitude: 116.075,
                },
            } as GeolocationPosition);
        });

        await getCurrentCoordinates();

        expect(getCurrentPosition).toHaveBeenCalledWith(
            expect.any(Function),
            expect.any(Function),
            {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0,
            },
        );
    });

    it('creates a typed geolocation error', () => {
        const error = new RunnerGeolocationError(
            'UNKNOWN',
            'Location error',
        );

        expect(error).toBeInstanceOf(Error);
        expect(error.name).toBe('RunnerGeolocationError');
        expect(error.code).toBe('UNKNOWN');
    });
});
