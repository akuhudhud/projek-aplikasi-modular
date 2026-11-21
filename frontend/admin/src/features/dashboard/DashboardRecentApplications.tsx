
import type { DashboardApplication } from './dashboardTypes';

interface DashboardRecentApplicationsProps {
  applications: DashboardApplication[];
}

export default function DashboardRecentApplications({
  applications
}: DashboardRecentApplicationsProps) {
  return (
    <section className="dashboard-panel">
      <div className="dashboard-panel__heading">
        <div>
          <h3>Permohonan Runner Terkini</h3>
          <p>Lima permohonan yang menunggu tindakan</p>
        </div>
      </div>

      {applications.length === 0 ? (
        <div className="dashboard-list-empty">
          Tiada permohonan Runner yang menunggu tindakan.
        </div>
      ) : (
        <div className="dashboard-application-list">
          {applications.map((application) => (
            <article
              className="dashboard-application"
              key={application.id}
            >
              <div className="dashboard-application__identity">
                <strong>{application.profile_name}</strong>
                <span>
                  {application.phone || application.email || 'Tiada maklumat hubungan'}
                </span>
              </div>

              <span className="admin-status">
                Menunggu
              </span>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
