
import type { RunnerNotification } from '../api';
import RunnerNotificationCard from '../components/shared/RunnerNotificationCard';

interface NotificationsPageProps {
    notifications: RunnerNotification[];
    loading: boolean;
    error: string;
    onRefresh: () => void;
    onMarkRead: (notification: RunnerNotification) => void;
}

export default function NotificationsPage({
    notifications,
    loading,
    error,
    onRefresh,
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
        </section>
    );
}
