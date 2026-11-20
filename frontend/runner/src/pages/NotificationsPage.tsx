
import type {
    RunnerPagination,
    RunnerNotification,
} from '../api';

import RunnerNotificationCard from '../components/shared/RunnerNotificationCard';
import PaginationControls from '../components/shared/PaginationControls';

interface NotificationsPageProps {
    notifications: RunnerNotification[];
    pagination: RunnerPagination;
    loading: boolean;
    error: string;
    onRefresh: () => void;
    onPageChange: (page: number) => void;
    onMarkRead: (notification: RunnerNotification) => void;
}

export default function NotificationsPage({
    notifications,
    pagination,
    loading,
    error,
    onRefresh,
    onPageChange,
    onMarkRead,
}: NotificationsPageProps) {
    return (
        <section className="runner-dashboard__section">
            <div className="runner-dashboard__section-heading">
                <div>
                    <h2>Notifikasi</h2>
                    <p>
                        Makluman berkaitan tugasan dan akaun Runner.
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
                    Memuatkan notifikasi...
                </p>
            )}

            {error && (
                <p className="runner-dashboard__alert" role="alert">
                    {error}
                </p>
            )}

            {!loading && !error && notifications.length === 0 && (
                <div className="runner-dashboard__empty">
                    <h3>Tiada Notifikasi</h3>
                    <p>
                        Notifikasi baharu akan dipaparkan di sini.
                    </p>
                </div>
            )}

            <div className="runner-dashboard__list">
                {notifications.map((notification) => (
                    <RunnerNotificationCard
                        key={notification.id}
                        notification={notification}
                        onMarkRead={onMarkRead}
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
