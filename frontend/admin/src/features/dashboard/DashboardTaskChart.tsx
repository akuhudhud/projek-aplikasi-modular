
import type { DashboardTaskDailyActivity } from './dashboardTypes';

interface DashboardTaskChartProps {
  data: DashboardTaskDailyActivity[];
}

export default function DashboardTaskChart({
  data
}: DashboardTaskChartProps) {
  const maxValue = Math.max(
    1,
    ...data.map((item) => Math.max(item.created, item.completed))
  );

  function formatDay(date: string): string {
    return new Intl.DateTimeFormat('ms-MY', {
      weekday: 'short',
      day: 'numeric'
    }).format(new Date(`${date}T12:00:00`));
  }

  return (
    <section className="dashboard-panel">
      <div className="dashboard-panel__heading">
        <div>
          <h3>Aktiviti Tugasan</h3>
          <p>Rekod tugasan dalam tempoh tujuh hari</p>
        </div>
      </div>

      <div className="dashboard-chart-legend">
        <span><i className="dashboard-chart-legend__created" /> Dicipta</span>
        <span><i className="dashboard-chart-legend__completed" /> Selesai</span>
      </div>

      <div className="dashboard-chart">
        {data.map((item) => (
          <div className="dashboard-chart__day" key={item.date}>
            <div className="dashboard-chart__bars">
              <div
                className="dashboard-chart__bar dashboard-chart__bar--created"
                style={{
                  height: `${Math.max(3, (item.created / maxValue) * 100)}%`
                }}
                title={`Dicipta: ${item.created}`}
              />

              <div
                className="dashboard-chart__bar dashboard-chart__bar--completed"
                style={{
                  height: `${Math.max(3, (item.completed / maxValue) * 100)}%`
                }}
                title={`Selesai: ${item.completed}`}
              />
            </div>

            <span>{formatDay(item.date)}</span>
          </div>
        ))}
      </div>
    </section>
  );
}
