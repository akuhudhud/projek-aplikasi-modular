
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import RunnerApp from './app/RunnerApp';
import PwaInstallPrompt from './app/PwaInstallPrompt';
import registerRunnerServiceWorker from './pwa/registerRunnerServiceWorker';

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

registerRunnerServiceWorker();
