
import type { CustomerTaskItem } from '../api';

import { formatCustomerDate } from '../customerPresentation';

type TasksPageProps = {
    tasks: CustomerTaskItem[];
    tasksLoading: boolean;
    tasksError: string;
    taskPage: number;
    taskLastPage: number;
    onOpenTask: (item: CustomerTaskItem) => void;
    onPageChange: (page: number) => void;
};

function taskClassLabel(taskClass: string): string {
    if (taskClass === 'PICKUP_DELIVERY') {
        return 'Ambil dan Hantar';
    }

    if (taskClass === 'NON_DELIVERY') {
        return 'Tugasan Bukan Penghantaran';
    }

    return taskClass;
}

export default function TasksPage({
    tasks,
    tasksLoading,
    tasksError,
    taskPage,
    taskLastPage,
    onOpenTask,
    onPageChange,
}: TasksPageProps) {
    return (
        <div className="sr-tasks-page">
            <section className="customer-panel">
                <div className="customer-section-heading">
                    <div>
                        <h2>Tugasan Saya</h2>
                        <p>
                            Senarai tugasan yang telah berjaya diwujudkan
                            daripada Permintaan Terbuka.
                        </p>
                    </div>

                    <span>{tasks.length} tugasan</span>
                </div>

                {tasksLoading ? (
                    <p>Memuatkan tugasan...</p>
                ) : tasksError ? (
                    <p role="alert">{tasksError}</p>
                ) : tasks.length === 0 ? (
                    <section className="customer-empty">
                        <h3>Belum ada tugasan</h3>
                        <p>
                            Tugasan yang berjaya diwujudkan akan
                            dipaparkan di sini.
                        </p>
                    </section>
                ) : (
                    <>
                        <div className="customer-task-list">
                            {tasks.map((item) => (
                                <button
                                    className="customer-task-card"
                                    type="button"
                                    key={item.task.task_id}
                                    onClick={() => onOpenTask(item)}
                                >
                                    <span className="customer-task-card-top">
                                        {taskClassLabel(
                                            item.request.task_class,
                                        )}
                                    </span>

                                    <strong>
                                        {item.request.description}
                                    </strong>

                                    <span>
                                        Status: {item.task.generic_status}
                                    </span>

                                    <span>
                                        {formatCustomerDate(item.task.created_at)}
                                    </span>
                                </button>
                            ))}
                        </div>

                        <div className="customer-pagination">
                            <button
                                type="button"
                                disabled={
                                    taskPage <= 1 || tasksLoading
                                }
                                onClick={() =>
                                    onPageChange(taskPage - 1)
                                }
                            >
                                Sebelum
                            </button>

                            <span>
                                {taskPage} / {taskLastPage}
                            </span>

                            <button
                                type="button"
                                disabled={
                                    taskPage >= taskLastPage ||
                                    tasksLoading
                                }
                                onClick={() =>
                                    onPageChange(taskPage + 1)
                                }
                            >
                                Seterusnya
                            </button>
                        </div>
                    </>
                )}
            </section>
        </div>
    );
}
