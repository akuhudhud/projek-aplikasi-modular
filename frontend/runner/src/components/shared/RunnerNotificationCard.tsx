
import type { RunnerNotification } from '../../api';
import { formatDate } from '../../utils/runnerFormatters';

interface RunnerNotificationCardProps {
    notification: RunnerNotification;
    onMarkRead: (notification: RunnerNotification) => void;
}

export default function RunnerNotificationCard({
    notification,
    onMarkRead,
}: RunnerNotificationCardProps) {
    return (
        <article
            className="runner-dashboard__item"
            key={notification.id}
        >
            <div className="runner-dashboard__item-top">
                <strong>
                    {notification.data.title
                        ?? 'Notifikasi Super Runner'}
                </strong>

                {!notification.read_at && (
                    <span className="runner-dashboard__status runner-dashboard__status--open">
                        BELUM DIBACA
                    </span>
                )}
            </div>

            <p>
                {notification.data.message
                    ?? 'Anda mempunyai notifikasi baharu.'}
            </p>

            <small>
                {formatDate(notification.created_at)}
            </small>

            {!notification.read_at && (
                <button
                    type="button"
                    onClick={() => onMarkRead(notification)}
                >
                    TANDA SUDAH DIBACA
                </button>
            )}
        </article>
    );
}
