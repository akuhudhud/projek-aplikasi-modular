
export default function registerRunnerServiceWorker(): void {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener(
        'load',
        () => {
            const hadController = Boolean(
                navigator.serviceWorker.controller,
            );

            let refreshing = false;

            if (hadController) {
                navigator.serviceWorker.addEventListener(
                    'controllerchange',
                    () => {
                        if (refreshing) {
                            return;
                        }

                        refreshing = true;
                        window.location.reload();
                    },
                );
            }

            navigator.serviceWorker
                .register('/sw.js', {
                    updateViaCache: 'none',
                })
                .then((registration) => {
                    registration.update().catch((error) => {
                        console.warn(
                            'Runner Service Worker update check failed:',
                            error,
                        );
                    });
                })
                .catch((error) => {
                    console.error(
                        'Runner Service Worker registration failed:',
                        error,
                    );
                });
        },
        { once: true },
    );
}
