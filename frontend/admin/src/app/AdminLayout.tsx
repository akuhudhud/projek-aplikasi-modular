
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
  return (
    <div className="admin-layout">
      <AdminSidebar
        activePage={activePage}
        onNavigate={onNavigate}
      />

      <div className="admin-layout__main">
        <AdminHeader
          account={account}
          activePage={activePage}
          onLogout={onLogout}
        />

        <main className="admin-content">{children}</main>
      </div>
    </div>
  );
}
