
import type { ReactNode } from 'react';

import AdminSidebar from '../components/navigation/AdminSidebar';
import AdminHeader from '../components/navigation/AdminHeader';

interface AdminLayoutProps {
  children: ReactNode;
}

export default function AdminLayout({
  children
}: AdminLayoutProps) {
  return (
    <div className="admin-layout">
      <AdminSidebar />

      <div className="admin-layout__main">
        <AdminHeader />

        <main className="admin-content">
          {children}
        </main>
      </div>
    </div>
  );
}
