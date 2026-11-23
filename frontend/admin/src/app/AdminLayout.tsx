
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import AdminSidebar from '../components/navigation/AdminSidebar';
import AdminHeader from '../components/navigation/AdminHeader';
import type { AdminPage, AdminProfile } from '../types/admin';

interface AdminLayoutProps {
  children: ReactNode;
  account: AdminProfile;
  activePage: AdminPage;
  onNavigate: (page: AdminPage) => void;
  onLogout: () => void;
}

export default function AdminLayout({
  children,
  account,
  activePage,
  onNavigate,
  onLogout
}: AdminLayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  function closeSidebar() {
    setSidebarOpen(false);
  }

  function toggleSidebar() {
    setSidebarOpen((current) => !current);
  }

  useEffect(() => {
    if (!sidebarOpen) return;

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        closeSidebar();
      }
    }

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [sidebarOpen]);

  function handleNavigate(page: AdminPage, section?: string) {
    onNavigate(page);
    closeSidebar();

    if (page === 'runner-applications' && section) {
      window.setTimeout(() => {
        const tabId =
          section === 'directory'
            ? 'runner-tab-directory'
            : 'runner-tab-applications';

        document.getElementById(tabId)?.click();
      }, 0);
    }

    if (page === 'accounts' && section === 'super-admin') {
      window.setTimeout(() => {
        document
          .getElementById('super-admin-management')
          ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 0);
    }

    if (page === 'accounts' && section === 'accounts') {
      window.setTimeout(() => {
        document
          .getElementById('account-directory')
          ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 0);
    }
  }

  return (
    <div className="admin-layout">
      {sidebarOpen && (
        <button
          type="button"
          className="admin-sidebar-backdrop"
          aria-label="Tutup menu navigasi"
          onClick={closeSidebar}
        />
      )}

      <AdminSidebar
        account={account}
        activePage={activePage}
        isOpen={sidebarOpen}
        onNavigate={handleNavigate}
      />

      <div className="admin-layout__main">
        <AdminHeader
          account={account}
          activePage={activePage}
          onLogout={onLogout}
          sidebarOpen={sidebarOpen}
          onToggleSidebar={toggleSidebar}
        />

        <main className="admin-content">
          {children}
        </main>
      </div>
    </div>
  );
}
