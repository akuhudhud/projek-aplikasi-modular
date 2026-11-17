
import {
    useRunnerSession,
} from '../features/auth/useRunnerSession';

import RunnerAuthPage from '../features/auth/RunnerAuthPage';

import RunnerAccessPage, {
    type RunnerAccessReason,
} from '../features/auth/RunnerAccessPage';

import type {
    RunnerAccount,
} from '../api';

function getRunnerAccessReason(
    account: RunnerAccount,
): RunnerAccessReason | null {
    if (account.status !== 'ACTIVE') {
        return 'ACCOUNT_INACTIVE';
    }

    switch (account.identity_verification_status) {
        case 'NOT_SUBMITTED':
            return 'IDENTITY_NOT_SUBMITTED';

        case 'PENDING':
            return 'IDENTITY_PENDING';

        case 'REJECTED':
            return 'IDENTITY_REJECTED';

        case 'VERIFIED':
            break;

        default:
            return 'IDENTITY_NOT_SUBMITTED';
    }

    switch (account.runner_status) {
        case 'NOT_RUNNER':
            return 'RUNNER_NOT_REGISTERED';

        case 'PENDING':
            return 'RUNNER_PENDING';

        case 'REJECTED':
            return 'RUNNER_REJECTED';

        case 'SUSPENDED':
            return 'RUNNER_SUSPENDED';

        case 'APPROVED':
            return null;

        default:
            return 'RUNNER_NOT_REGISTERED';
    }
}

function RunnerDashboardPlaceholder({
    account,
    onLogout,
    logoutLoading,
    logoutError,
}: {
    account: RunnerAccount;
    onLogout: () => void;
    logoutLoading: boolean;
    logoutError: string;
}) {
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

                <h1>{account.profile_name}</h1>

                <p>
                    Akaun Runner anda telah diluluskan.
                </p>
            </section>

            {logoutError && (
                <p role="alert">{logoutError}</p>
            )}

            <section className="runner-dashboard__content">
                <h2>Dashboard Runner</h2>

                <p>
                    Ruang operasi Runner akan dipaparkan
                    di sini.
                </p>
            </section>
        </main>
    );
}

export default function RunnerApp() {
    const session = useRunnerSession();

    if (session.checkingSession) {
        return (
            <main className="runner-loading">
                <div className="runner-loading__brand">
                    SR
                </div>

                <p>Memeriksa sesi akaun...</p>
            </main>
        );
    }

    if (!session.account) {
        return (
            <RunnerAuthPage
                onAuthenticated={session.setAccount}
            />
        );
    }

    const accessReason = getRunnerAccessReason(
        session.account,
    );

    if (accessReason !== null) {
        return (
            <RunnerAccessPage
                account={session.account}
                reason={accessReason}
                logout={session.logout}
                logoutLoading={session.logoutLoading}
                logoutError={session.logoutError}
            />
        );
    }

    return (
        <RunnerDashboardPlaceholder
            account={session.account}
            onLogout={() => {
                void session.logout();
            }}
            logoutLoading={session.logoutLoading}
            logoutError={session.logoutError}
        />
    );
}
