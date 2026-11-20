
import type { RunnerTask } from '../api';
import RunnerTaskCard from '../components/tasks/RunnerTaskCard';

interface TasksPageProps {
    tasks: RunnerTask[];
    loading: boolean;
    error: string;
    actionLoading: string;
    onRefresh: () => void;
    onStart: (task: RunnerTask) => void;
    onArrive: (task: RunnerTask) => void;
    onComplete: (task: RunnerTask, file?: File) => void;
}

export default function TasksPage({
    tasks,
    loading,
    error,
    actionLoading,
    onRefresh,
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
                <p className="runner-dashboard__notice">
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
        </section>
    );
}
