
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import AdminApp from './app/AdminApp';

import './styles/admin.css';
import './styles/admin-accessibility.css';

const rootElement = document.getElementById('admin-app');

if (!rootElement) {
  throw new Error('Admin application root element was not found.');
}

createRoot(rootElement).render(
  <StrictMode>
    <AdminApp />
  </StrictMode>
);
