
import type { AdminProfile, AdminPage } from '../../types/admin';

interface AdminHeaderProps {
  account: AdminProfile;
  activePage: AdminPage;
  onLogout: () => void;
  sidebarOpen: boolean;
  onToggleSidebar: () => void;
}

const pageTitles: Record<AdminPage, string> = {
  dashboard: 'Dashboard',
  accounts: 'Pengurusan Pengguna',
  'runner-applications': 'Pengurusan Runner',
  'identity-verifications': 'Pengesahan Identiti',
  'audit-logs': 'Log Audit'
};

export default function AdminHeader({
  account,
  activePage,
  onLogout,
  sidebarOpen,
  onToggleSidebar
}: AdminHeaderProps) {
  return (
    <header className="admin-header">
      <div className="admin-header__title">
        <button
          type="button"
          className="admin-menu-toggle"
          onClick={onToggleSidebar}
          aria-label={
            sidebarOpen
              ? 'Tutup menu navigasi'
              : 'Buka menu navigasi'
          }
          aria-expanded={sidebarOpen}
          aria-controls="admin-sidebar"
        >
          <span />
          <span />
          <span />
        </button>

        <div>
          <span className="admin-header__eyebrow">
            SISTEM PENTADBIRAN
          </span>

          <h1>{pageTitles[activePage]}</h1>
        </div>
      </div>

      <div className="admin-header__account">
        <div className="admin-header__avatar">
          {account.profile_name.charAt(0).toUpperCase()}
        </div>

        <div>
          <strong>{account.profile_name}</strong>
          <span>{account.role}</span>
        </div>

        <button
          type="button"
          className="admin-button admin-button--small"
          onClick={onLogout}
        >
          Keluar
        </button>
      </div>
    </header>
  );
}
