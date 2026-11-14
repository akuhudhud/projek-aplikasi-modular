
import type { CustomerPage } from '../app/navigation';
import { CUSTOMER_PAGE_LABELS } from '../app/navigation';

type TopAppBarProps = {
    activePage: CustomerPage;
    accountName: string;
    onLogout: () => void;
    loading?: boolean;
    unreadNotificationTotal: number;
    onOpenNotifications: () => void;
};

export default function TopAppBar({
    activePage,
    accountName,
    onLogout,
    loading = false,
    unreadNotificationTotal,
    onOpenNotifications,
}: TopAppBarProps) {
    return (
        <header className="sr-top-app-bar">
            <div className="sr-top-app-bar__brand">
                <div className="sr-top-app-bar__logo">
                    SR
                </div>

                <div className="sr-top-app-bar__brand-text">
                    <strong>Super Runner</strong>
                    <span>Pelanggan</span>
                </div>
            </div>

            <div className="sr-top-app-bar__page">
                {CUSTOMER_PAGE_LABELS[activePage]}
            </div>

            <div className="sr-top-app-bar__account">
                <button
                    type="button"
                    className="sr-top-app-bar__notifications"
                    onClick={onOpenNotifications}
                    aria-label={
                        unreadNotificationTotal > 0
                            ? `Notifikasi, ${unreadNotificationTotal} belum dibaca`
                            : 'Notifikasi'
                    }
                >
                    <span aria-hidden="true">♢</span>

                    {unreadNotificationTotal > 0 && (
                        <span className="sr-top-app-bar__notification-count">
                            {unreadNotificationTotal > 99
                                ? '99+'
                                : unreadNotificationTotal}
                        </span>
                    )}
                </button>

                <div className="sr-top-app-bar__account-info">
                    <span className="sr-top-app-bar__greeting">
                        Akaun
                    </span>

                    <strong title={accountName}>
                        {accountName}
                    </strong>
                </div>

                <button
                    type="button"
                    className="sr-top-app-bar__logout"
                    aria-label="Keluar"
                    onClick={onLogout}
                    disabled={loading}
                >
                    {loading ? '...' : 'Keluar'}
                </button>
            </div>
        </header>
    );
}
