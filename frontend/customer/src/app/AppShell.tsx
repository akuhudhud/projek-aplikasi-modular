import { useState } from 'react';
import type { ReactNode } from 'react';

import type {
    CustomerNotification,
} from '../api';
import type { CustomerPage } from './navigation';

import TopAppBar from '../components/TopAppBar';
import BottomNavigation from '../components/BottomNavigation';
import CustomerNotifications from '../features/notifications/CustomerNotifications';
import ConnectionStatus from '../features/connectivity/ConnectionStatus';

import type { CustomerNotifications as CustomerNotificationsState } from '../features/notifications/useCustomerNotifications';

type AppShellProps = {
    activePage: CustomerPage;
    accountName: string;
    onNavigate: (page: CustomerPage) => void;
    onLogout: () => void;
    onOpenNotification: (notification: CustomerNotification) => void;
    loading?: boolean;
    notifications: CustomerNotificationsState;
    children: ReactNode;
};

export default function AppShell({
    activePage,
    accountName,
    onNavigate,
    onLogout,
    onOpenNotification,
    loading = false,
    notifications,
    children,
}: AppShellProps) {
    const [showNotifications, setShowNotifications] = useState(false);

    return (
        <div className="sr-app-shell">
            <ConnectionStatus />

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
                    <CustomerNotifications
                        state={notifications}
                        onOpenNotification={(notification) => {
                            setShowNotifications(false);
                            onOpenNotification(notification);
                        }}
                    />
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
