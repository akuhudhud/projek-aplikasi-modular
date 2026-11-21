
import type { AdminPage, AdminProfile } from '../../types/admin';
import { canAccessPage } from '../../app/adminAccess';

interface AdminSidebarProps {
  account: AdminProfile;
  activePage: AdminPage;
  onNavigate: (page: AdminPage) => void;
}

const navigationItems: { id: AdminPage; label: string }[] = [
  { id: 'dashboard', label: 'Dashboard' },
  { id: 'accounts', label: 'Pengurusan Pengguna' },
  { id: 'runner-applications', label: 'Permohonan Runner' },
  { id: 'identity-verifications', label: 'Pengesahan Identiti' },
  { id: 'license-verifications', label: 'Pengesahan Lesen' },
  { id: 'audit-logs', label: 'Log Audit' }
];

export default function AdminSidebar({
  account,
  activePage,
  onNavigate
}: AdminSidebarProps) {
  const accessibleItems = navigationItems.filter((item) =>
    canAccessPage(account, item.id)
  );

  return (
    <aside className="admin-sidebar">
      <div className="admin-sidebar__brand">
        <div className="admin-sidebar__logo">SR</div>
        <div>
          <strong>SUPER RUNNER</strong>
          <span>ADMINISTRATION</span>
        </div>
      </div>

      <div className="admin-sidebar__section-label">MENU UTAMA</div>

      <nav aria-label="Navigasi utama">
        {accessibleItems.map((item) => (
          <button
            type="button"
            key={item.id}
            className={[
              'admin-sidebar__item',
              activePage === item.id
                ? 'admin-sidebar__item--active'
                : ''
            ].join(' ')}
            aria-current={activePage === item.id ? 'page' : undefined}
            onClick={() => onNavigate(item.id)}
          >
            {item.label}
          </button>
        ))}
      </nav>

      <div className="admin-sidebar__footer">
        <span>SUPER RUNNER</span>
        <small>Admin Web v0.4.7</small>
      </div>
    </aside>
  );
}
