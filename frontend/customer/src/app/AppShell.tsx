import type { ReactNode } from 'react';

import type { CustomerPage } from './navigation';

import TopAppBar from '../components/TopAppBar';
import BottomNavigation from '../components/BottomNavigation';

type AppShellProps = {
    activePage: CustomerPage;
    accountName: string;
    onNavigate: (page: CustomerPage) => void;
    onLogout: () => void;
    loading?: boolean;
    children: ReactNode;
};

export default function AppShell({
    activePage,
    accountName,
    onNavigate,
    onLogout,
    loading = false,
    children,
}: AppShellProps) {
    return (
        <div className="sr-app-shell">
            <TopAppBar
                activePage={activePage}
                accountName={accountName}
                onLogout={onLogout}
                loading={loading}
            />

            <main className="sr-app-shell__content">
                {children}
            </main>

            <BottomNavigation
                activePage={activePage}
                onNavigate={onNavigate}
            />
        </div>
    );
}
