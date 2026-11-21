
const navigationItems = [
  {
    label: 'Dashboard',
    active: true
  },
  {
    label: 'Pengurusan Pengguna',
    active: false
  },
  {
    label: 'Permohonan Runner',
    active: false
  },
  {
    label: 'Pengesahan Identiti',
    active: false
  },
  {
    label: 'Pengesahan Lesen',
    active: false
  },
  {
    label: 'Log Audit',
    active: false
  }
];

export default function AdminSidebar() {
  return (
    <aside className="admin-sidebar">
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

      <nav aria-label="Navigasi utama">
        {navigationItems.map((item) => (
          <div
            key={item.label}
            className={[
              'admin-sidebar__item',
              item.active
                ? 'admin-sidebar__item--active'
                : ''
            ].join(' ')}
            aria-current={item.active ? 'page' : undefined}
          >
            <span>{item.label}</span>
          </div>
        ))}
      </nav>

      <div className="admin-sidebar__footer">
        <span>SUPER RUNNER</span>
        <small>Admin Web v0.4.7</small>
      </div>
    </aside>
  );
}
