
import { useState } from 'react';
import type { ReactNode } from 'react';

import type { CustomerPage } from './navigation';

import TopAppBar from '../components/TopAppBar';
import BottomNavigation from '../components/BottomNavigation';
import CustomerNotifications from '../features/notifications/CustomerNotifications';
import type { CustomerNotifications as CustomerNotificationsState } from '../features/notifications/useCustomerNotifications';

type AppShellProps = {
    activePage: CustomerPage;
    accountName: string;
    onNavigate: (page: CustomerPage) => void;
    onLogout: () => void;
    loading?: boolean;
    notifications: CustomerNotificationsState;
    children: ReactNode;
};

export default function AppShell({
    activePage,
    accountName,
    onNavigate,
    onLogout,
    loading = false,
    notifications,
    children,
}: AppShellProps) {
    const [showNotifications, setShowNotifications] = useState(false);

    return (
        <div className="sr-app-shell">
            <TopAppBar
                activePage={activePage}
                accountName={accountName}
                onLogout={onLogout}
                loading={loading}
                unreadNotificationTotal={notifications.unreadTotal}
                onOpenNotifications={() => {
                    setShowNotifications((current) => !current);
                    notifications.refresh();
                }}
            />

            <main className="sr-app-shell__content">
                {showNotifications ? (
                    <CustomerNotifications state={notifications} />
                ) : (
                    children
                )}
            </main>

            <BottomNavigation
                activePage={activePage}
                onNavigate={(page) => {
                    setShowNotifications(false);
                    onNavigate(page);
                }}
            />
        </div>
    );
}
