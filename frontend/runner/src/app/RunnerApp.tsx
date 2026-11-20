
import type { RunnerAccount } from '../api';

import {
    useRunnerSession,
} from '../features/auth/useRunnerSession';

import RunnerAuthPage from '../features/auth/RunnerAuthPage';

import RunnerAccessPage, {
    type RunnerAccessReason,
} from '../features/auth/RunnerAccessPage';

import RunnerWorkspace from './RunnerWorkspace';

function getRunnerAccessReason(
    account: RunnerAccount,
): RunnerAccessReason | null {
    if (account.status !== 'ACTIVE') {
        return 'ACCOUNT_INACTIVE';
    }

    switch (account.runner_status) {
        case 'APPROVED':
            return null;

        case 'PENDING':
            return 'RUNNER_PENDING';

        case 'REJECTED':
            return 'RUNNER_REJECTED';

        case 'SUSPENDED':
            return 'RUNNER_SUSPENDED';

        default:
            return 'RUNNER_NOT_REGISTERED';
    }
}

export default function RunnerApp() {
    const session = useRunnerSession();

    if (session.checkingSession) {
        return (
            <main className="runner-loading">
                <div className="runner-loading__brand">SR</div>
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

    const accessReason = getRunnerAccessReason(session.account);

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
        <RunnerWorkspace
            account={session.account}
            onLogout={() => {
                void session.logout();
            }}
            logoutLoading={session.logoutLoading}
            logoutError={session.logoutError}
        />
    );
}
