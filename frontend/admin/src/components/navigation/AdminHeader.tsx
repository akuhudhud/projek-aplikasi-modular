
export default function AdminHeader() {
  return (
    <header className="admin-header">
      <div>
        <span className="admin-header__eyebrow">
          SISTEM PENTADBIRAN
        </span>

        <h1>Dashboard</h1>
      </div>

      <div className="admin-header__account">
        <div className="admin-header__avatar">A</div>

        <div>
          <strong>Admin</strong>
          <span>Sesi belum disambungkan</span>
        </div>
      </div>
    </header>
  );
}
