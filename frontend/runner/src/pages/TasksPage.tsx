
import type { RunnerPagination, RunnerTask } from '../api';
import RunnerTaskCard from '../components/tasks/RunnerTaskCard';
import PaginationControls from '../components/shared/PaginationControls';

interface TasksPageProps {
    tasks: RunnerTask[];
    pagination: RunnerPagination;
    loading: boolean;
    error: string;
    actionLoading: string;
    onRefresh: () => void;
    onPageChange: (page: number) => void;
    onStart: (task: RunnerTask) => void;
    onArrive: (task: RunnerTask) => void;
    onComplete: (task: RunnerTask, file?: File) => void;
}

export default function TasksPage({
    tasks,
    pagination,
    loading,
    error,
    actionLoading,
    onRefresh,
    onPageChange,
    onStart,
    onArrive,
    onComplete,
}: TasksPageProps) {
    return (
        <section className="runner-dashboard__section">
            <div className="runner-dashboard__section-heading">
                <div>
                    <h2>Tugasan Saya</h2>
                    <p>
                        Tugasan yang telah diberikan kepada anda.
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
                <p
                    className="runner-dashboard__notice"
                    role="status"
                    aria-live="polite"
                >
                    Memuatkan tugasan...
                </p>
            )}

            {error && (
                <p className="runner-dashboard__alert" role="alert">
                    {error}
                </p>
            )}

            {!loading && !error && tasks.length === 0 && (
                <div className="runner-dashboard__empty">
                    <h3>Belum Ada Tugasan</h3>
                    <p>
                        Tugasan yang diberikan kepada anda akan
                        dipaparkan di sini.
                    </p>
                </div>
            )}

            <div className="runner-dashboard__list">
                {tasks.map((task) => (
                    <RunnerTaskCard
                        key={task.task_id}
                        task={task}
                        actionLoading={actionLoading}
                        onStart={onStart}
                        onArrive={onArrive}
                        onComplete={onComplete}
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
