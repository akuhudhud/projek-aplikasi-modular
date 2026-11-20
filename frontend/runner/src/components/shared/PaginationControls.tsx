interface PaginationControlsProps {
    currentPage: number;
    lastPage: number;
    total: number;
    loading: boolean;
    onPageChange: (page: number) => void;
}

export default function PaginationControls({
    currentPage,
    lastPage,
    total,
    loading,
    onPageChange,
}: PaginationControlsProps) {
    if (lastPage <= 1) {
        return null;
    }

    return (
        <nav
            className="runner-pagination"
            aria-label="Navigasi halaman"
        >
            <p className="runner-pagination__summary">
                Halaman {currentPage} daripada {lastPage}
                {' · '}
                {total} rekod
            </p>

            <div className="runner-pagination__actions">
                <button
                    type="button"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={loading || currentPage <= 1}
                >
                    SEBELUMNYA
                </button>

                <button
                    type="button"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={loading || currentPage >= lastPage}
                >
                    SETERUSNYA
                </button>
            </div>
        </nav>
    );
}
