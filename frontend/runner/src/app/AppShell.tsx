
import type { ReactNode } from 'react';

export type RunnerDashboardTab =
    | 'TASKS'
    | 'HISTORY'
    | 'REQUESTS'
    | 'NOTIFICATIONS'
    | 'PROFILE';

interface AppShellProps {
    profileName: string;
    activeTab: RunnerDashboardTab;
    unreadTotal: number;
    activeTaskCount: number | null;
    completedTaskCount: number | null;
    onTabChange: (tab: RunnerDashboardTab) => void;
    onLogout: () => void;
    logoutLoading: boolean;
    children: ReactNode;
}

export default function AppShell({
    profileName,
    activeTab,
    unreadTotal,
    activeTaskCount,
    completedTaskCount,
    onTabChange,
    onLogout,
    logoutLoading,
    children,
}: AppShellProps) {
    return (
        <main className="runner-dashboard">
            <header className="runner-dashboard__header">
                <div className="runner-dashboard__brand">
                    <span className="runner-dashboard__brand-mark">
                        SR
                    </span>
                    <span>SUPER RUNNER</span>
                </div>

                <button
                    type="button"
                    onClick={onLogout}
                    disabled={logoutLoading}
                >
                    {logoutLoading
                        ? 'SEDANG LOG KELUAR...'
                        : 'LOG KELUAR'}
                </button>
            </header>

            <section className="runner-dashboard__welcome">
                <p>SELAMAT DATANG</p>
                <h1>{profileName}</h1>
                <p>Akaun Runner anda telah diluluskan.</p>
            </section>

            <section className="runner-dashboard__content">
                <div className="runner-dashboard__summary">
                    <article className="runner-dashboard__summary-card">
                        <span>TUGASAN AKTIF</span>
                        <strong>
                            {activeTaskCount ?? '—'}
                        </strong>
                    </article>

                    <article className="runner-dashboard__summary-card">
                        <span>TUGASAN SELESAI</span>
                        <strong>
                            {completedTaskCount ?? '—'}
                        </strong>
                    </article>

                    <article className="runner-dashboard__summary-card">
                        <span>NOTIFIKASI BELUM DIBACA</span>
                        <strong>{unreadTotal}</strong>
                    </article>
                </div>

                <nav
                    className="runner-dashboard__tabs"
                    aria-label="Menu operasi Runner"
                >
                    <button
                        type="button"
                        className={activeTab === 'TASKS' ? 'is-active' : ''}
                        onClick={() => onTabChange('TASKS')}
                    >
                        TUGASAN SAYA
                    </button>

                    <button
                        type="button"
                        className={activeTab === 'HISTORY' ? 'is-active' : ''}
                        onClick={() => onTabChange('HISTORY')}
                    >
                        SEJARAH
                    </button>

                    <button
                        type="button"
                        className={activeTab === 'REQUESTS' ? 'is-active' : ''}
                        onClick={() => onTabChange('REQUESTS')}
                    >
                        OPEN REQUEST
                    </button>

                    <button
                        type="button"
                        className={activeTab === 'NOTIFICATIONS' ? 'is-active' : ''}
                        onClick={() => onTabChange('NOTIFICATIONS')}
                    >
                        NOTIFIKASI
                        {unreadTotal > 0 ? ` (${unreadTotal})` : ''}
                    </button>

                    <button
                        type="button"
                        className={activeTab === 'PROFILE' ? 'is-active' : ''}
                        onClick={() => onTabChange('PROFILE')}
                    >
                        PROFIL & TETAPAN
                    </button>
                </nav>

                {children}
            </section>
        </main>
    );
}
