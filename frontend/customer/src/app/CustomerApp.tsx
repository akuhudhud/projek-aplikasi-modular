import { useCustomerSession } from '../features/auth/useCustomerSession';
import CustomerAuthPage from '../features/auth/CustomerAuthPage';

import CustomerWorkspace from './CustomerWorkspace';
import RunnerPublicProfile from '../RunnerPublicProfile';

import '../../styles/customer.css';
import '../../styles/app-shell.css';

function getPublicRunnerSlug(): string | null {
    const match = window.location.pathname.match(
        /^\/me\/([A-Za-z0-9_-]{3,50})\/?$/,
    );

    return match?.[1] ?? null;
}

export default function CustomerApp() {
    const publicRunnerSlug = getPublicRunnerSlug();

    const session = useCustomerSession();

    if (publicRunnerSlug) {
        return (
            <RunnerPublicProfile slug={publicRunnerSlug} />
        );
    }

    if (session.checkingSession) {
        return (
            <main className="customer-loading">
                <p>Memeriksa sesi...</p>
            </main>
        );
    }

    if (!session.account) {
        return (
            <CustomerAuthPage
                onAuthenticated={session.setAccount}
            />
        );
    }

    return (
        <CustomerWorkspace
            account={session.account}
            onAccountUpdated={session.setAccount}
            onLogout={() => {
                void session.logout();
            }}
            logoutLoading={session.logoutLoading}
            logoutError={session.logoutError}
        />
    );
}
