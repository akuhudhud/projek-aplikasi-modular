
import type { RunnerTask } from '../api';
import RunnerTaskCard from '../components/tasks/RunnerTaskCard';

interface HistoryPageProps {
    history: RunnerTask[];
    loading: boolean;
    error: string;
    actionLoading: string;
    onRefresh: () => void;
}

export default function HistoryPage({
    history,
    loading,
    error,
    actionLoading,
    onRefresh,
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
        </section>
    );
}
