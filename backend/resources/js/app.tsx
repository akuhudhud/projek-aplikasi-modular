import { useEffect, useState, type FormEvent } from 'react';
import axios from 'axios';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { customerApi, type CustomerAccount } from './customer/api';
import '../css/customer.css';

function CustomerApp() {
    const [account, setAccount] = useState<CustomerAccount | null>(null);
    const [identifier, setIdentifier] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [checkingSession, setCheckingSession] = useState(true);

    useEffect(() => {
        let mounted = true;

        customerApi.me()
            .then((currentAccount) => {
                if (mounted) {
                    setAccount(currentAccount);
                }
            })
            .catch(() => {
                if (mounted) {
                    setAccount(null);
                }
            })
            .finally(() => {
                if (mounted) {
                    setCheckingSession(false);
                }
            });

        return () => {
            mounted = false;
        };
    }, []);

    async function handleLogin(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        setError('');
        setLoading(true);

        try {
            const value = identifier.trim();

            const currentAccount = await customerApi.login({
                ...(value.includes('@')
                    ? { email: value }
                    : { phone: value }),
                password,
            });

            setAccount(currentAccount);
            setPassword('');
        } catch (err) {
            if (axios.isAxiosError(err)) {
                setError(
                    err.response?.data?.message ??
                    'Login gagal. Sila semak maklumat anda.',
                );
            } else {
                setError('Ralat berlaku. Sila cuba semula.');
            }
        } finally {
            setLoading(false);
        }
    }

    async function handleLogout() {
        setError('');
        setLoading(true);

        try {
            await customerApi.logout();
            setAccount(null);
            setIdentifier('');
            setPassword('');
        } catch {
            setError('Tidak berjaya logout. Sila cuba semula.');
        } finally {
            setLoading(false);
        }
    }

    if (checkingSession) {
        return (
            <main>
                <p>Memeriksa sesi...</p>
            </main>
        );
    }

    if (account) {
        return (
            <main>
                <h1>Super Runner</h1>
                <h2>Selamat datang, {account.name}</h2>
                <p>Akaun anda: {account.status}</p>

                <button
                    type="button"
                    onClick={handleLogout}
                    disabled={loading}
                >
                    {loading ? 'Sedang logout...' : 'Logout'}
                </button>

                {error && <p role="alert">{error}</p>}
            </main>
        );
    }

    return (
        <main>
            <h1>Super Runner</h1>
            <h2>Log Masuk Pelanggan</h2>

            <form onSubmit={handleLogin}>
                <div>
                    <label htmlFor="identifier">
                        Nombor Telefon atau E-mel
                    </label>
                    <input
                        id="identifier"
                        type="text"
                        autoComplete="username"
                        value={identifier}
                        onChange={(event) => setIdentifier(event.target.value)}
                        required
                        disabled={loading}
                    />
                </div>

                <div>
                    <label htmlFor="password">Kata Laluan</label>
                    <input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        required
                        disabled={loading}
                    />
                </div>

                {error && <p role="alert">{error}</p>}

                <button type="submit" disabled={loading}>
                    {loading ? 'Sedang log masuk...' : 'Log Masuk'}
                </button>
            </form>
        </main>
    );
}

const rootElement = document.getElementById('customer-app');

if (rootElement) {
    createRoot(rootElement).render(
        <StrictMode>
            <CustomerApp />
        </StrictMode>,
    );
}
