
export interface RunnerCoordinates {
    latitude: number;
    longitude: number;
}

export type RunnerGeolocationErrorCode =
    | 'UNSUPPORTED'
    | 'PERMISSION_DENIED'
    | 'POSITION_UNAVAILABLE'
    | 'TIMEOUT'
    | 'UNKNOWN';

export class RunnerGeolocationError extends Error {
    constructor(
        public readonly code: RunnerGeolocationErrorCode,
        message: string,
    ) {
        super(message);
        this.name = 'RunnerGeolocationError';
    }
}

function createGeolocationError(
    error: GeolocationPositionError,
): RunnerGeolocationError {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            return new RunnerGeolocationError(
                'PERMISSION_DENIED',
                'Akses lokasi telah ditolak.',
            );

        case error.POSITION_UNAVAILABLE:
            return new RunnerGeolocationError(
                'POSITION_UNAVAILABLE',
                'Lokasi semasa tidak dapat diperoleh.',
            );

        case error.TIMEOUT:
            return new RunnerGeolocationError(
                'TIMEOUT',
                'Permintaan lokasi telah tamat masa.',
            );

        default:
            return new RunnerGeolocationError(
                'UNKNOWN',
                'Berlaku ralat semasa mendapatkan lokasi.',
            );
    }
}

export function getCurrentCoordinates(): Promise<RunnerCoordinates> {
    return new Promise((resolve, reject) => {
        if (
            typeof navigator === 'undefined'
            || !navigator.geolocation
        ) {
            reject(
                new RunnerGeolocationError(
                    'UNSUPPORTED',
                    'Browser tidak menyokong Geolocation.',
                ),
            );
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                });
            },
            (error) => {
                reject(createGeolocationError(error));
            },
            {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0,
            },
        );
    });
}
