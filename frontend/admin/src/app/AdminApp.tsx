
import { useEffect, useState } from 'react';

import AdminLayout from './AdminLayout';
import DashboardPage from '../features/dashboard/DashboardPage';
import LoginPage from '../features/auth/LoginPage';
import AccountsPage from '../features/accounts/AccountsPage';
import RunnerApplicationsPage from '../features/runner-applications/RunnerApplicationsPage';
import AuditLogsPage from '../features/audit-logs/AuditLogsPage';
import VerificationUnavailablePage from '../features/verification/VerificationUnavailablePage';

import {
  clearSession,
  getApiError,
  getCurrentAdmin,
  hasSession,
  login,
  logout
} from '../services/adminApi';

import type { AdminPage, AdminProfile } from '../types/admin';

export default function AdminApp() {
  const [account, setAccount] = useState<AdminProfile | null>(null);
  const [activePage, setActivePage] = useState<AdminPage>('dashboard');
  const [checkingSession, setCheckingSession] = useState(true);
  const [loggingIn, setLoggingIn] = useState(false);
  const [loginError, setLoginError] = useState('');

  useEffect(() => {
    let mounted = true;

    async function checkSession() {
      if (!hasSession()) {
        setCheckingSession(false);
        return;
      }

      try {
        const current = await getCurrentAdmin();

        if (mounted) {
          setAccount(current);
        }
      } catch {
        clearSession();
      } finally {
        if (mounted) {
          setCheckingSession(false);
        }
      }
    }

    void checkSession();

    return () => {
      mounted = false;
    };
  }, []);

  async function handleLogin(identifier: string, password: string) {
    setLoggingIn(true);
    setLoginError('');

    try {
      const current = await login(identifier, password);

      if (
        current.role !== 'ROOT_SUPER_ADMIN' &&
        current.role !== 'SUPER_ADMIN'
      ) {
        clearSession();
        throw new Error('Akaun ini bukan akaun pentadbir.');
      }

      setAccount(current);
      setActivePage('dashboard');
    } catch (error) {
      setLoginError(getApiError(error));
    } finally {
      setLoggingIn(false);
    }
  }

  async function handleLogout() {
    try {
      await logout();
    } finally {
      setAccount(null);
      setActivePage('dashboard');
    }
  }

  if (checkingSession) {
    return <div className="admin-state">Memeriksa sesi pentadbir...</div>;
  }

  if (!account) {
    return (
      <LoginPage
        onLogin={handleLogin}
        error={loginError}
        loading={loggingIn}
      />
    );
  }

  function renderPage() {
    switch (activePage) {
      case 'dashboard':
        return <DashboardPage />;

      case 'accounts':
        return <AccountsPage />;

      case 'runner-applications':
        return <RunnerApplicationsPage />;

      case 'identity-verifications':
        return <VerificationUnavailablePage type="identity" />;

      case 'license-verifications':
        return <VerificationUnavailablePage type="license" />;

      case 'audit-logs':
        return <AuditLogsPage />;

      default:
        return <DashboardPage />;
    }
  }

  return (
    <AdminLayout
      account={account}
      activePage={activePage}
      onNavigate={setActivePage}
      onLogout={() => void handleLogout()}
    >
      {renderPage()}
    </AdminLayout>
  );
}
