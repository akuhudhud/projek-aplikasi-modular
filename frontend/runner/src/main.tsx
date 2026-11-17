
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import RunnerApp from './app/RunnerApp';

import '../styles/runner.css';

const rootElement = document.getElementById('runner-app');

if (rootElement) {
    createRoot(rootElement).render(
        <StrictMode>
            <RunnerApp />
        </StrictMode>,
    );
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js')
            .catch((error) => {
                console.error(
                    'Runner Service Worker registration failed:',
                    error,
                );
            });
    });
}
