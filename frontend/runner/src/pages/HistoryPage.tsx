
import type { RunnerPagination, RunnerTask } from '../api';
import RunnerTaskCard from '../components/tasks/RunnerTaskCard';
import PaginationControls from '../components/shared/PaginationControls';

interface HistoryPageProps {
    history: RunnerTask[];
    pagination: RunnerPagination;
    loading: boolean;
    error: string;
    actionLoading: string;
    onRefresh: () => void;
    onPageChange: (page: number) => void;
}

export default function HistoryPage({
    history,
    pagination,
    loading,
    error,
    actionLoading,
    onRefresh,
    onPageChange,
}: HistoryPageProps) {
    return (
        <section className="runner-dashboard__section">
            <div className="runner-dashboard__section-heading">
                <div>
                    <h2>Sejarah Tugasan</h2>
                    <p>
                        Rekod tugasan yang telah selesai atau dibatalkan.
                    </p>
                </div>

                <button
                    type="button"
                    onClick={onRefresh}
                    disabled={loading}
                >
                    {loading ? 'MEMUAT...' : 'SEGARKAN'}
                </button>
            </div>

            {loading && (
                <p className="runner-dashboard__notice">
                    Memuatkan sejarah...
                </p>
            )}

            {error && (
                <p className="runner-dashboard__alert" role="alert">
                    {error}
                </p>
            )}

            {!loading && !error && history.length === 0 && (
                <div className="runner-dashboard__empty">
                    <h3>Belum Ada Sejarah</h3>
                    <p>
                        Tugasan yang selesai atau dibatalkan
                        akan dipaparkan di sini.
                    </p>
                </div>
            )}

            <div className="runner-dashboard__list">
                {history.map((task) => (
                    <RunnerTaskCard
                        key={task.task_id}
                        task={task}
                        actionLoading={actionLoading}
                        onStart={() => undefined}
                        onArrive={() => undefined}
                        onComplete={() => undefined}
                    />
                ))}
            </div>

            <PaginationControls
                currentPage={pagination.current_page}
                lastPage={pagination.last_page}
                total={pagination.total}
                loading={loading}
                onPageChange={onPageChange}
            />
        </section>
    );
}
