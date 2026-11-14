
import type { CustomerNotification } from '../../api';
import type { CustomerNotifications as CustomerNotificationsState } from './useCustomerNotifications';

type CustomerNotificationsProps = {
    state: CustomerNotificationsState;
};

function getNotificationTitle(notification: CustomerNotification): string {
    const data = notification.data;

    if (typeof data.title === 'string' && data.title.trim()) {
        return data.title;
    }

    const titles: Record<string, string> = {
        'App\\Notifications\\CustomerRequestNotification': 'Kemaskini tempahan',
        'request': 'Kemaskini tempahan',
        'quotation': 'Sebutharga diterima',
        'task': 'Kemaskini tugasan',
    };

    return titles[notification.type] ?? 'Notifikasi Super Runner';
}

function getNotificationMessage(notification: CustomerNotification): string {
    const data = notification.data;

    for (const key of ['message', 'body', 'description']) {
        const value = data[key];

        if (typeof value === 'string' && value.trim()) {
            return value;
        }
    }

    return 'Anda mempunyai kemaskini baharu.';
}

function formatDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

export default function CustomerNotifications({
    state,
}: CustomerNotificationsProps) {
    return (
        <section className="customer-panel sr-notifications">
            <header className="sr-notifications__header">
                <div>
                    <h1>Notifikasi</h1>
                    <p>
                        {state.unreadTotal > 0
                            ? `${state.unreadTotal} notifikasi belum dibaca`
                            : 'Semua notifikasi telah dibaca'}
                    </p>
                </div>

                <button
                    type="button"
                    onClick={state.refresh}
                    disabled={state.loading}
                >
                    Muat semula
                </button>
            </header>

            {state.error && (
                <div className="sr-notifications__error" role="alert">
                    {state.error}
                    <button type="button" onClick={state.refresh}>
                        Cuba lagi
                    </button>
                </div>
            )}

            {state.actionMessage && (
                <p role="status" className="sr-notifications__success">
                    {state.actionMessage}
                </p>
            )}

            {state.actionError && (
                <p role="alert" className="sr-notifications__error">
                    {state.actionError}
                </p>
            )}

            {state.total > 0 && (
                <div className="sr-notifications__toolbar">
                    <span>{state.total} notifikasi</span>

                    <button
                        type="button"
                        onClick={() => {
                            if (window.confirm('Padam semua notifikasi?')) {
                                void state.deleteAll();
                            }
                        }}
                        disabled={state.deletingAll || state.loading}
                    >
                        {state.deletingAll
                            ? 'Memadam...'
                            : 'Padam semua'}
                    </button>
                </div>
            )}

            {state.loading ? (
                <p className="sr-notifications__empty">
                    Memuatkan notifikasi...
                </p>
            ) : state.notifications.length === 0 && !state.error ? (
                <div className="sr-notifications__empty">
                    <strong>Tiada notifikasi</strong>
                    <p>Notifikasi baharu akan dipaparkan di sini.</p>
                </div>
            ) : (
                <div className="sr-notifications__list">
                    {state.notifications.map((notification) => {
                        const busy =
                            state.actionLoadingId === notification.id ||
                            state.deletingAll;

                        return (
                            <article
                                key={notification.id}
                                className={[
                                    'sr-notifications__item',
                                    notification.read_at === null
                                        ? 'is-unread'
                                        : '',
                                ].filter(Boolean).join(' ')}
                            >
                                <div className="sr-notifications__item-content">
                                    <div className="sr-notifications__item-heading">
                                        <strong>
                                            {getNotificationTitle(notification)}
                                        </strong>

                                        {notification.read_at === null && (
                                            <span>Baharu</span>
                                        )}
                                    </div>

                                    <p>
                                        {getNotificationMessage(notification)}
                                    </p>

                                    <time dateTime={notification.created_at}>
                                        {formatDate(notification.created_at)}
                                    </time>
                                </div>

                                <div className="sr-notifications__actions">
                                    {notification.read_at === null && (
                                        <button
                                            type="button"
                                            onClick={() => void state.markAsRead(notification.id)}
                                            disabled={busy}
                                        >
                                            Tandakan dibaca
                                        </button>
                                    )}

                                    <button
                                        type="button"
                                        onClick={() => void state.deleteNotification(notification.id)}
                                        disabled={busy}
                                    >
                                        Padam
                                    </button>
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}

            {state.lastPage > 1 && (
                <nav
                    className="sr-notifications__pagination"
                    aria-label="Halaman notifikasi"
                >
                    <button
                        type="button"
                        onClick={() => state.setCurrentPage(state.currentPage - 1)}
                        disabled={state.currentPage <= 1 || state.loading}
                    >
                        Sebelum
                    </button>

                    <span>
                        Halaman {state.currentPage} daripada {state.lastPage}
                    </span>

                    <button
                        type="button"
                        onClick={() => state.setCurrentPage(state.currentPage + 1)}
                        disabled={
                            state.currentPage >= state.lastPage ||
                            state.loading
                        }
                    >
                        Seterusnya
                    </button>
                </nav>
            )}
        </section>
    );
}
