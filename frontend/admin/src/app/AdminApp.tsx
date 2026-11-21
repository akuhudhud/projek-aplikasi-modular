
import AdminLayout from './AdminLayout';
import DashboardPage from '../features/dashboard/DashboardPage';

export default function AdminApp() {
  return (
    <AdminLayout>
      <DashboardPage />
    </AdminLayout>
  );
}
