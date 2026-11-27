
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import AdminApp from './app/AdminApp';

import './styles/admin.css';
import './styles/admin-accessibility.css';
import './styles/admin-sidebar.css';

function registerAdminServiceWorker(): void {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  if (!window.isSecureContext) {
    return;
  }

  window.addEventListener('load', () => {
    void navigator.serviceWorker
      .register('/sw.js', { scope: '/' })
      .then((registration) => {
        console.info(
          '[Admin PWA] Service Worker registered:',
          registration.scope,
        );
      })
      .catch((error: unknown) => {
        console.error(
          '[Admin PWA] Service Worker registration failed:',
          error,
        );
      });
  });
}

const rootElement = document.getElementById('admin-app');

if (!rootElement) {
  throw new Error('Admin application root element was not found.');
}

createRoot(rootElement).render(
  <StrictMode>
    <AdminApp />
  </StrictMode>,
);

registerAdminServiceWorker();
