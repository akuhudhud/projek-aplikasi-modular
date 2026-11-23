
import { useCallback, useEffect, useRef, useState } from 'react';

import {
  getAdminDashboard,
  getApiError
} from '../../services/adminApi';

import DashboardStatCard from './DashboardStatCard';
import DashboardTaskChart from './DashboardTaskChart';
import DashboardRecentApplications from './DashboardRecentApplications';

import type { AdminDashboard } from './dashboardTypes';

import './dashboard.css';

export default function DashboardPage() {
  const [dashboard, setDashboard] = useState<AdminDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const mountedRef = useRef(false);
  const requestIdRef = useRef(0);

  const loadDashboard = useCallback(async () => {
    const requestId = ++requestIdRef.current;

    if (mountedRef.current) {
      setLoading(true);
      setError('');
    }

    try {
      const result = await getAdminDashboard();

      if (
        !mountedRef.current ||
        requestId !== requestIdRef.current
      ) {
        return;
      }

      setDashboard(result);
    } catch (requestError) {
      if (
        mountedRef.current &&
        requestId === requestIdRef.current
      ) {
        setError(getApiError(requestError));
      }
    } finally {
      if (
        mountedRef.current &&
        requestId === requestIdRef.current
      ) {
        setLoading(false);
      }
    }
  }, []);

  useEffect(() => {
    mountedRef.current = true;
    void loadDashboard();

    return () => {
      mountedRef.current = false;
      requestIdRef.current += 1;
    };
  }, [loadDashboard]);

  function formatStatus(status: string): string {
    return status.replace(/_/g, ' ');
  }

  return (
    <section className="dashboard-page">
      <div className="dashboard-page__welcome">
        <div>
          <span className="dashboard-page__eyebrow">
            SUPER RUNNER MANAGEMENT
          </span>

          <h2>Selamat datang</h2>

          <p>
            Pusat pengurusan dan pemantauan operasi SUPER RUNNER.
          </p>
        </div>

        <div className="dashboard-page__version">
          <span>VERSI</span>
          <strong>0.4.7</strong>
        </div>
      </div>

      <div className="dashboard-section-heading">
        <div>
          <h3>Ringkasan Operasi</h3>
          <p>Statistik berdasarkan rekod sebenar sistem.</p>
        </div>
      </div>

      {loading && (
        <div className="dashboard-loading" role="status">
          Memuatkan data dashboard...
        </div>
      )}

      {!loading && error && (
        <div className="admin-alert admin-alert--error" role="alert">
          <p>{error}</p>

          <button
            type="button"
            className="admin-button admin-button--primary"
            onClick={() => void loadDashboard()}
          >
            Cuba Lagi
          </button>
        </div>
      )}

      {!loading && !error && dashboard && (
        <>
          <div className="dashboard-stats-grid">
            <DashboardStatCard
              label="Jumlah Pengguna"
              value={dashboard.accounts.total_users}
              description="Semua akaun pengguna"
            />

            <DashboardStatCard
              label="Pengguna Aktif"
              value={dashboard.accounts.active_users}
              description="Akaun berstatus aktif"
            />

            <DashboardStatCard
              label="Jumlah Runner"
              value={dashboard.runners.total}
              description="Semua status permohonan Runner"
            />

            <DashboardStatCard
              label="Permohonan Menunggu"
              value={dashboard.runner_applications.pending}
              description="Menunggu tindakan pentadbir"
            />
          </div>

          <div className="dashboard-main-grid">
            <DashboardTaskChart
              data={dashboard.tasks.daily_activity}
            />

            <section className="dashboard-panel">
              <div className="dashboard-panel__heading">
                <div>
                  <h3>Status Tugasan</h3>
                  <p>
                    Jumlah: {dashboard.tasks.total.toLocaleString('ms-MY')}
                  </p>
                </div>
              </div>

              <div className="dashboard-status-list">
                {Object.entries(dashboard.tasks.statuses).map(
                  ([status, total]) => (
                    <div className="dashboard-status-row" key={status}>
                      <span>{formatStatus(status)}</span>
                      <strong>{total.toLocaleString('ms-MY')}</strong>
                    </div>
                  )
                )}
              </div>
            </section>
          </div>

          <div className="dashboard-main-grid">
            {dashboard.runner_applications.can_view_recent && (
              <DashboardRecentApplications
                applications={dashboard.runner_applications.recent}
              />
            )}

            <section className="dashboard-panel">
              <div className="dashboard-panel__heading">
                <div>
                  <h3>Status Runner</h3>
                  <p>
                    Jumlah: {dashboard.runners.total.toLocaleString('ms-MY')}
                  </p>
                </div>
              </div>

              <div className="dashboard-status-list">
                {Object.entries(dashboard.runners.statuses).map(
                  ([status, total]) => (
                    <div className="dashboard-status-row" key={status}>
                      <span>{formatStatus(status)}</span>
                      <strong>{total.toLocaleString('ms-MY')}</strong>
                    </div>
                  )
                )}
              </div>
            </section>
          </div>
        </>
      )}
    </section>
  );
}
