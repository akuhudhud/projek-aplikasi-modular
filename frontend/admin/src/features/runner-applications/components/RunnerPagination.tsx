interface RunnerPaginationProps {
  currentPage: number;
  lastPage: number;
  loading: boolean;
  onPageChange: (page: number) => void;
}

export default function RunnerPagination({
  currentPage,
  lastPage,
  loading,
  onPageChange
}: RunnerPaginationProps) {
  return (
    <nav
      className="admin-record__actions"
      aria-label="Navigasi halaman Runner"
    >
      <button
        type="button"
        className="admin-button"
        disabled={loading || currentPage <= 1}
        onClick={() => onPageChange(currentPage - 1)}
      >
        Sebelum
      </button>

      <span aria-live="polite">
        Halaman {currentPage} daripada {lastPage}
      </span>

      <button
        type="button"
        className="admin-button"
        disabled={loading || currentPage >= lastPage}
        onClick={() => onPageChange(currentPage + 1)}
      >
        Seterusnya
      </button>
    </nav>
  );
}
