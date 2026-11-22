
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
  isUnauthorizedError,
  login,
  logout
} from '../services/adminApi';

import {
  canAccessPage,
  getDefaultAdminPage,
  isAdminAccount
} from './adminAccess';

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

        if (!isAdminAccount(current)) {
          clearSession();

          if (mounted) {
            setLoginError(
              'Akaun ini tidak mempunyai akses pentadbir.'
            );
          }

          return;
        }

        if (mounted) {
          setAccount(current);
          setActivePage(getDefaultAdminPage(current));
        }
      } catch (error) {
        if (isUnauthorizedError(error)) {
          clearSession();

          if (mounted) {
            setLoginError(
              'Sesi telah tamat. Sila log masuk semula.'
            );
          }
        } else if (mounted) {
          setLoginError(getApiError(error));
        }
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

      if (!isAdminAccount(current)) {
        clearSession();
        throw new Error('Akaun ini bukan akaun pentadbir.');
      }

      setAccount(current);
      setActivePage(getDefaultAdminPage(current));
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

  function handleNavigate(page: AdminPage) {
    if (!account || !canAccessPage(account, page)) {
      return;
    }

    setActivePage(page);
  }

  if (checkingSession) {
    return (
      <div className="admin-state">
        Memeriksa sesi pentadbir...
      </div>
    );
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

  const authenticatedAccount = account;

  const safePage = canAccessPage(authenticatedAccount, activePage)
    ? activePage
    : getDefaultAdminPage(authenticatedAccount);

  function renderPage() {
    switch (safePage) {
      case 'dashboard':
        return <DashboardPage />;

      case 'accounts':
        return <AccountsPage account={authenticatedAccount} />;

      case 'runner-applications':
        return (
          <RunnerApplicationsPage account={authenticatedAccount} />
        );

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
      account={authenticatedAccount}
      activePage={safePage}
      onNavigate={handleNavigate}
      onLogout={() => void handleLogout()}
    >
      {renderPage()}
    </AdminLayout>
  );
}
