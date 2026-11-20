
export interface RunnerCoordinates {
    latitude: number;
    longitude: number;
}

export function getCurrentCoordinates(): Promise<RunnerCoordinates> {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(
                new Error('Peranti tidak menyokong GPS browser.'),
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
                reject(error);
            },
            {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0,
            },
        );
    });
}
