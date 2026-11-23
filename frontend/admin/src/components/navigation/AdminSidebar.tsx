
import { useState } from 'react';

import type { AdminPage, AdminProfile } from '../../types/admin';
import { canAccessPage } from '../../app/adminAccess';

interface AdminSidebarProps {
  account: AdminProfile;
  activePage: AdminPage;
  isOpen: boolean;
  onNavigate: (page: AdminPage, section?: string) => void;
}

export default function AdminSidebar({
  account,
  activePage,
  isOpen,
  onNavigate
}: AdminSidebarProps) {
  const isRoot = account.role === 'ROOT_SUPER_ADMIN';

  const canViewAccounts = canAccessPage(account, 'accounts');
  const canViewRunners = canAccessPage(account, 'runner-applications');

  const [expanded, setExpanded] = useState<Record<string, boolean>>({
    users: activePage === 'accounts',
    runners: activePage === 'runner-applications'
  });

  function toggleGroup(group: string) {
    setExpanded((current) => ({
      ...current,
      [group]: !current[group]
    }));
  }

  function navigate(page: AdminPage, section?: string) {
    onNavigate(page, section);
  }

  return (
    <aside
      id="admin-sidebar"
      className={[
        'admin-sidebar',
        isOpen ? 'admin-sidebar--open' : ''
      ].join(' ')}
      aria-label="Menu utama"
      aria-hidden={!isOpen}
      inert={!isOpen}
    >
      <div className="admin-sidebar__brand">
        <div className="admin-sidebar__logo">SR</div>
        <div>
          <strong>SUPER RUNNER</strong>
          <span>ADMINISTRATION</span>
        </div>
      </div>

      <div className="admin-sidebar__section-label">
        MENU UTAMA
      </div>

      <nav className="admin-sidebar__nav" aria-label="Navigasi utama">
        <button
          type="button"
          className={[
            'admin-sidebar__item',
            activePage === 'dashboard'
              ? 'admin-sidebar__item--active'
              : ''
          ].join(' ')}
          onClick={() => navigate('dashboard')}
        >
          <span>Dashboard</span>
        </button>

        {canViewAccounts && (
          <div className="admin-sidebar__group">
            <button
              type="button"
              className={[
                'admin-sidebar__item',
                'admin-sidebar__parent',
                activePage === 'accounts'
                  ? 'admin-sidebar__item--active'
                  : ''
              ].join(' ')}
              aria-expanded={Boolean(expanded.users)}
              onClick={() => toggleGroup('users')}
            >
              <span>Pengurusan Pengguna</span>
              <span className="admin-sidebar__chevron">
                {expanded.users ? '−' : '+'}
              </span>
            </button>

            {expanded.users && (
              <div className="admin-sidebar__children">
                <button
                  type="button"
                  className="admin-sidebar__child"
                  onClick={() => navigate('accounts', 'accounts')}
                >
                  Senarai Akaun
                </button>

                {isRoot && (
                  <button
                    type="button"
                    className="admin-sidebar__child"
                    onClick={() => navigate('accounts', 'super-admin')}
                  >
                    Pengurusan Super Admin
                  </button>
                )}
              </div>
            )}
          </div>
        )}

        {canViewRunners && (
          <div className="admin-sidebar__group">
            <button
              type="button"
              className={[
                'admin-sidebar__item',
                'admin-sidebar__parent',
                activePage === 'runner-applications'
                  ? 'admin-sidebar__item--active'
                  : ''
              ].join(' ')}
              aria-expanded={Boolean(expanded.runners)}
              onClick={() => toggleGroup('runners')}
            >
              <span>Pengurusan Runner</span>
              <span className="admin-sidebar__chevron">
                {expanded.runners ? '−' : '+'}
              </span>
            </button>

            {expanded.runners && (
              <div className="admin-sidebar__children">
                <button
                  type="button"
                  className="admin-sidebar__child"
                  onClick={() =>
                    navigate('runner-applications', 'applications')
                  }
                >
                  Permohonan Runner
                </button>

                <button
                  type="button"
                  className="admin-sidebar__child"
                  onClick={() =>
                    navigate('runner-applications', 'directory')
                  }
                >
                  Direktori Runner
                </button>
              </div>
            )}
          </div>
        )}

        {canAccessPage(account, 'identity-verifications') && (
          <button
            type="button"
            className={[
              'admin-sidebar__item',
              activePage === 'identity-verifications'
                ? 'admin-sidebar__item--active'
                : ''
            ].join(' ')}
            onClick={() => navigate('identity-verifications')}
          >
            <span>Pengesahan Identiti</span>
          </button>
        )}

        {canAccessPage(account, 'audit-logs') && (
          <button
            type="button"
            className={[
              'admin-sidebar__item',
              activePage === 'audit-logs'
                ? 'admin-sidebar__item--active'
                : ''
            ].join(' ')}
            onClick={() => navigate('audit-logs')}
          >
            <span>Log Audit</span>
          </button>
        )}
      </nav>

      <div className="admin-sidebar__footer">
        <span>SUPER RUNNER</span>
        <small>Admin Web v0.4.7</small>
      </div>
    </aside>
  );
}
