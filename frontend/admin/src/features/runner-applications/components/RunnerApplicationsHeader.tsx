
interface RunnerApplicationsHeaderProps {
  loading: boolean;
  onRefresh: () => void;
}

export default function RunnerApplicationsHeader({
  loading,
  onRefresh,
}: RunnerApplicationsHeaderProps) {
  return (
    <div className="admin-page__heading">
      <div>
        <span className="dashboard-page__eyebrow">
          PENGURUSAN RUNNER
        </span>
        <h2>Pengurusan Runner</h2>
        <p>
          Semakan permohonan dan pengurusan kelayakan Runner.
        </p>
      </div>

      <button
        type="button"
        className="admin-button"
        onClick={onRefresh}
        disabled={loading}
      >
        Muat Semula
      </button>
    </div>
  );
}
