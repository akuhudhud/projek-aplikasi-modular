
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import RunnerApp from './app/RunnerApp';
import PwaInstallPrompt from './app/PwaInstallPrompt';

import '../styles/runner.css';

const rootElement = document.getElementById('runner-app');

if (rootElement) {
    createRoot(rootElement).render(
        <StrictMode>
            <RunnerApp />
            <PwaInstallPrompt />
        </StrictMode>,
    );
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
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
    });
}
