
import { useCustomerSession } from '../features/auth/useCustomerSession';
import CustomerAuthPage from '../features/auth/CustomerAuthPage';

import CustomerWorkspace from './CustomerWorkspace';

export default function CustomerApp() {
    const session = useCustomerSession();

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
