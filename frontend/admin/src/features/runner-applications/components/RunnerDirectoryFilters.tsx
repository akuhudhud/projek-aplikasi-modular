interface RunnerDirectoryFiltersProps {
  search: string;
  status: string;
  loading: boolean;
  onSearchChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  onSearch: () => void;
}

export default function RunnerDirectoryFilters({
  search,
  status,
  loading,
  onSearchChange,
  onStatusChange,
  onSearch
}: RunnerDirectoryFiltersProps) {
  return (
    <div className="admin-filters">
      <div>
        <label htmlFor="runner-directory-search">
          Cari Runner
        </label>

        <input
          id="runner-directory-search"
          type="search"
          value={search}
          placeholder="Cari nama, telefon atau e-mel Runner"
          onChange={(event) => onSearchChange(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter') onSearch();
          }}
        />
      </div>

      <div>
        <label htmlFor="runner-directory-status">
          Tapis status Runner
        </label>

        <select
          id="runner-directory-status"
          value={status}
          onChange={(event) => onStatusChange(event.target.value)}
        >
          <option value="">Semua status</option>
          <option value="APPROVED">APPROVED</option>
          <option value="SUSPENDED">SUSPENDED</option>
        </select>
      </div>

      <button
        type="button"
        className="admin-button"
        onClick={onSearch}
        disabled={loading}
      >
        Cari
      </button>
    </div>
  );
}
