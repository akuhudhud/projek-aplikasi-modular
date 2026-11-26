
import type { AdminProfile, AdminPage } from '../../types/admin';
import AdminNotificationBell from '../../features/notifications/AdminNotificationBell';

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
  'audit-logs': 'Log Audit',
  'driving-license-catalog': 'Katalog Lesen Memandu'
};

export default function AdminHeader({
  account,
  activePage,
  onLogout,
  sidebarOpen,
  onToggleSidebar
}: AdminHeaderProps) {
  const hamburgerStyle = {
    position: 'relative' as const,
    zIndex: 30,
    display: 'flex',
    width: '40px',
    height: '40px',
    flexShrink: 0,
    flexDirection: 'column' as const,
    alignItems: 'center',
    justifyContent: 'center',
    gap: '5px',
    padding: 0,
    border: '1px solid var(--admin-border)',
    borderRadius: '6px',
    background: 'var(--admin-panel-soft)',
    cursor: 'pointer'
  };

  const hamburgerLineStyle = {
    display: 'block',
    width: '18px',
    height: '2px',
    borderRadius: '2px',
    background: 'var(--admin-yellow)'
  };

  return (
    <header className="admin-header">
      <div
        className="admin-header__title"
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: '14px'
        }}
      >
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
          style={hamburgerStyle}
        >
          <span style={hamburgerLineStyle} />
          <span style={hamburgerLineStyle} />
          <span style={hamburgerLineStyle} />
        </button>

        <div>
          <span className="admin-header__eyebrow">
            SISTEM PENTADBIRAN
          </span>

          <h1>{pageTitles[activePage]}</h1>
        </div>
      </div>

      <div
        className="admin-header__account"
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 12
        }}
      >
        <AdminNotificationBell />

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
