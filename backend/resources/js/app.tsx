import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

function CustomerApp() {
    return (
        <main>
            <h1>Super Runner</h1>
        </main>
    );
}

const rootElement = document.getElementById('customer-app');

if (rootElement) {
    createRoot(rootElement).render(
        <StrictMode>
            <CustomerApp />
        </StrictMode>,
    );
}
