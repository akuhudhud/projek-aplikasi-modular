
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import CustomerApp from './app/CustomerApp';
import RunnerPublicProfile from './RunnerPublicProfile';

import '../styles/customer.css';
import '../styles/app-shell.css';
import '../styles/app-shell-integration.css';

export { default as CustomerApp } from './app/CustomerApp';

const rootElement = document.getElementById('customer-app');

const publicRunnerMatch = window.location.pathname.match(
    /^\/me\/([A-Za-z0-9_-]{3,50})\/?$/,
);

if (rootElement) {
    createRoot(rootElement).render(
        <StrictMode>
            {publicRunnerMatch ? (
                <RunnerPublicProfile slug={publicRunnerMatch[1]} />
            ) : (
                <CustomerApp />
            )}
        </StrictMode>,
    );
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js')
            .catch((error) => {
                console.error(
                    'Customer Service Worker registration failed:',
                    error,
                );
            });
    });
}
