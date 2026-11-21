
interface DashboardStatCardProps {
  label: string;
  value: number;
  description: string;
}

export default function DashboardStatCard({
  label,
  value,
  description
}: DashboardStatCardProps) {
  return (
    <article className="dashboard-stat-card">
      <span className="dashboard-stat-card__label">
        {label}
      </span>

      <strong className="dashboard-stat-card__value">
        {new Intl.NumberFormat('ms-MY').format(value)}
      </strong>

      <span className="dashboard-stat-card__description">
        {description}
      </span>
    </article>
  );
}
