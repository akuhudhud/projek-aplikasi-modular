
import type { CustomerPage } from '../app/navigation';
import { CUSTOMER_PAGE_LABELS } from '../app/navigation';

type TopAppBarProps = {
    activePage: CustomerPage;
    accountName: string;
    onLogout: () => void;
    loading?: boolean;
};

export default function TopAppBar({
    activePage,
    accountName,
    onLogout,
    loading = false,
}: TopAppBarProps) {
    return (
        <header className="sr-top-app-bar">
            <div className="sr-top-app-bar__brand">
                <div className="sr-top-app-bar__logo">
                    SR
                </div>

                <div className="sr-top-app-bar__brand-text">
                    <strong>Super Runner</strong>
                    <span>Customer</span>
                </div>
            </div>

            <div className="sr-top-app-bar__page">
                {CUSTOMER_PAGE_LABELS[activePage]}
            </div>

            <div className="sr-top-app-bar__account">
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
                    aria-label="Logout"
                    onClick={onLogout}
                    disabled={loading}
                >
                    {loading ? '...' : 'Keluar'}
                </button>
            </div>
        </header>
    );
}
