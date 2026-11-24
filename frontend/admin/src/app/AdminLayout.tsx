
import { useEffect, useRef, useState } from 'react';
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

const FOCUSABLE_SELECTOR = [
  'button:not([disabled]):not([tabindex="-1"])',
  'a[href]:not([tabindex="-1"])',
  'input:not([disabled]):not([tabindex="-1"])',
  'select:not([disabled]):not([tabindex="-1"])',
  'textarea:not([disabled]):not([tabindex="-1"])',
  '[tabindex]:not([tabindex="-1"])'
].join(',');

export default function AdminLayout({
  children,
  account,
  activePage,
  onNavigate,
  onLogout
}: AdminLayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const wasSidebarOpen = useRef(false);

  function closeSidebar() {
    setSidebarOpen(false);
  }

  function toggleSidebar() {
    setSidebarOpen((current) => !current);
  }

  useEffect(() => {
    const sidebar = document.getElementById('admin-sidebar');
    const hamburger = document.querySelector<HTMLButtonElement>(
      '.admin-menu-toggle'
    );

    if (sidebarOpen) {
      wasSidebarOpen.current = true;

      const firstFocusable = sidebar?.querySelector<HTMLElement>(
        FOCUSABLE_SELECTOR
      );

      firstFocusable?.focus();
    } else if (wasSidebarOpen.current) {
      wasSidebarOpen.current = false;
      hamburger?.focus();
    }
  }, [sidebarOpen]);

  useEffect(() => {
    if (!sidebarOpen) return;

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeSidebar();
        return;
      }

      if (event.key !== 'Tab') return;

      const sidebar = document.getElementById('admin-sidebar');

      if (!sidebar) return;

      const focusableElements = Array.from(
        sidebar.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)
      );

      if (focusableElements.length === 0) {
        event.preventDefault();
        return;
      }

      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];
      const activeElement = document.activeElement;

      if (event.shiftKey) {
        if (
          activeElement === firstElement ||
          !sidebar.contains(activeElement)
        ) {
          event.preventDefault();
          lastElement.focus();
        }
      } else if (
        activeElement === lastElement ||
        !sidebar.contains(activeElement)
      ) {
        event.preventDefault();
        firstElement.focus();
      }
    }

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [sidebarOpen]);

  function handleNavigate(page: AdminPage, section?: string) {
    onNavigate(page);

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
          style={{
            position: 'fixed',
            inset: 0,
            zIndex: 15,
            border: 0,
            background: 'rgba(0, 0, 0, 0.65)',
            cursor: 'pointer'
          }}
        />
      )}

      <AdminSidebar
        account={account}
        activePage={activePage}
        isOpen={sidebarOpen}
        onNavigate={handleNavigate}
      />

      <div
        className="admin-layout__main"
        style={{
          width: '100%',
          marginLeft: 0
        }}
      >
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
