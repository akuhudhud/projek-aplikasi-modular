import { useEffect, useState, type FormEvent } from 'react';
import axios from 'axios';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import {
    customerApi,
    type CustomerAccount,
    type CustomerTaskItem,
} from './customer/api';
import '../css/customer.css';

type Page = 'dashboard' | 'tasks' | 'profile';

function formatDate(value: string | null): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function taskClassLabel(taskClass: string): string {
    return taskClass === 'PICKUP_DELIVERY'
        ? 'Pickup & Delivery'
        : 'Non-Delivery';
}

function CustomerApp() {
    const [account, setAccount] = useState<CustomerAccount | null>(null);
    const [identifier, setIdentifier] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [checkingSession, setCheckingSession] = useState(true);

    const [page, setPage] = useState<Page>('dashboard');
    const [tasks, setTasks] = useState<CustomerTaskItem[]>([]);
    const [taskTotal, setTaskTotal] = useState(0);
    const [taskPage, setTaskPage] = useState(1);
    const [taskLastPage, setTaskLastPage] = useState(1);
    const [tasksLoading, setTasksLoading] = useState(false);
    const [selectedTask, setSelectedTask] = useState<CustomerTaskItem | null>(null);

    useEffect(() => {
        let mounted = true;

        customerApi.me()
            .then((currentAccount) => {
                if (mounted) setAccount(currentAccount);
            })
            .catch(() => {
                if (mounted) setAccount(null);
            })
            .finally(() => {
                if (mounted) setCheckingSession(false);
            });

        return () => {
            mounted = false;
        };
    }, []);

    useEffect(() => {
        if (!account) return;

        let mounted = true;
        setTasksLoading(true);

        customerApi.getTasks(taskPage)
            .then((result) => {
                if (!mounted) return;

                setTasks(result.tasks);
                setTaskTotal(result.pagination.total);
                setTaskLastPage(result.pagination.last_page);
            })
            .catch(() => {
                if (mounted) {
                    setError('Tidak berjaya memuatkan senarai tugasan.');
                }
            })
            .finally(() => {
                if (mounted) setTasksLoading(false);
            });

        return () => {
            mounted = false;
        };
    }, [account, taskPage]);

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
            setPage('dashboard');
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
            setTasks([]);
            setSelectedTask(null);
        } catch {
            setError('Tidak berjaya logout. Sila cuba semula.');
        } finally {
            setLoading(false);
        }
    }

    function openTask(item: CustomerTaskItem) {
        setSelectedTask(item);
    }

    function renderTaskList() {
        if (tasksLoading) {
            return <p>Memuatkan tugasan...</p>;
        }

        if (tasks.length === 0) {
            return (
                <section className="customer-empty">
                    <h3>Belum ada tugasan</h3>
                    <p>Tugasan yang anda buat akan dipaparkan di sini.</p>
                </section>
            );
        }

        return (
            <>
                <div className="customer-task-list">
                    {tasks.map((item) => (
                        <button
                            className="customer-task-card"
                            type="button"
                            key={item.task.task_id}
                            onClick={() => openTask(item)}
                        >
                            <span className="customer-task-card-top">
                                {taskClassLabel(item.request.task_class)}
                            </span>
                            <strong>{item.request.description}</strong>
                            <span>Status: {item.task.generic_status}</span>
                            <span>{formatDate(item.task.created_at)}</span>
                        </button>
                    ))}
                </div>

                <div className="customer-pagination">
                    <button
                        type="button"
                        disabled={taskPage <= 1 || tasksLoading}
                        onClick={() => setTaskPage((current) => current - 1)}
                    >
                        Sebelum
                    </button>

                    <span>
                        {taskPage} / {taskLastPage}
                    </span>

                    <button
                        type="button"
                        disabled={taskPage >= taskLastPage || tasksLoading}
                        onClick={() => setTaskPage((current) => current + 1)}
                    >
                        Seterusnya
                    </button>
                </div>
            </>
        );
    }

    function renderTaskDetail(item: CustomerTaskItem) {
        const { task, request } = item;

        return (
            <section className="customer-panel">
                <button
                    className="customer-back-button"
                    type="button"
                    onClick={() => setSelectedTask(null)}
                >
                    ← Kembali ke senarai
                </button>

                <h2>Butiran Tugasan</h2>
                <p className="customer-task-class">
                    {taskClassLabel(request.task_class)}
                </p>

                <h3>{request.description}</h3>

                <dl className="customer-detail-list">
                    <div>
                        <dt>ID Tugasan</dt>
                        <dd>{task.task_id}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>{task.generic_status}</dd>
                    </div>
                    <div>
                        <dt>Status Permintaan</dt>
                        <dd>{request.status}</dd>
                    </div>
                    <div>
                        <dt>Dicipta</dt>
                        <dd>{formatDate(task.created_at)}</dd>
                    </div>
                    <div>
                        <dt>Runner ditugaskan</dt>
                        <dd>{formatDate(task.assigned_at)}</dd>
                    </div>
                    <div>
                        <dt>Mula</dt>
                        <dd>{formatDate(task.started_at)}</dd>
                    </div>
                    <div>
                        <dt>Selesai</dt>
                        <dd>{formatDate(task.completed_at)}</dd>
                    </div>
                    <div>
                        <dt>Tarikh luput permintaan</dt>
                        <dd>{formatDate(request.expires_at)}</dd>
                    </div>
                </dl>

                {request.task_class === 'PICKUP_DELIVERY' && (
                    <div className="customer-location-grid">
                        <div>
                            <h4>Lokasi Pickup</h4>
                            <pre>
                                {JSON.stringify(
                                    request.pickup_location_snapshot ?? {},
                                    null,
                                    2,
                                )}
                            </pre>
                        </div>
                        <div>
                            <h4>Lokasi Delivery</h4>
                            <pre>
                                {JSON.stringify(
                                    request.delivery_location_snapshot ?? {},
                                    null,
                                    2,
                                )}
                            </pre>
                        </div>
                    </div>
                )}

                {request.task_class === 'NON_DELIVERY' && (
                    <div>
                        <h4>Lokasi Tugasan</h4>
                        <pre>
                            {JSON.stringify(
                                request.task_location_snapshot ?? {},
                                null,
                                2,
                            )}
                        </pre>
                    </div>
                )}
            </section>
        );
    }

    function renderDashboard() {
        return (
            <>
                <section className="customer-welcome">
                    <p>Dashboard Pelanggan</p>
                    <h2>Hai, {account?.name}</h2>
                    <span>Selamat datang ke Super Runner.</span>
                </section>

                <div className="customer-summary-grid">
                    <article className="customer-summary-card">
                        <span>Jumlah Tugasan</span>
                        <strong>{taskTotal}</strong>
                    </article>
                    <article className="customer-summary-card">
                        <span>Status Akaun</span>
                        <strong>{account?.status}</strong>
                    </article>
                </div>

                <section className="customer-panel">
                    <div className="customer-section-heading">
                        <h2>Tugasan Terkini</h2>
                        <button
                            type="button"
                            onClick={() => {
                                setSelectedTask(null);
                                setPage('tasks');
                            }}
                        >
                            Lihat semua
                        </button>
                    </div>

                    {renderTaskList()}
                </section>
            </>
        );
    }

    function renderProfile() {
        if (!account) return null;

        return (
            <section className="customer-panel">
                <h2>Profil Saya</h2>

                <div className="customer-profile-avatar">
                    {account.name.charAt(0).toUpperCase()}
                </div>

                <dl className="customer-detail-list">
                    <div>
                        <dt>Nama</dt>
                        <dd>{account.name}</dd>
                    </div>
                    <div>
                        <dt>Nombor Telefon</dt>
                        <dd>{account.phone || 'Belum ditetapkan'}</dd>
                    </div>
                    <div>
                        <dt>E-mel</dt>
                        <dd>{account.email || 'Belum ditetapkan'}</dd>
                    </div>
                    <div>
                        <dt>Peranan</dt>
                        <dd>{account.role}</dd>
                    </div>
                    <div>
                        <dt>Status Akaun</dt>
                        <dd>{account.status}</dd>
                    </div>
                </dl>
            </section>
        );
    }

    if (checkingSession) {
        return (
            <main className="customer-loading">
                <p>Memeriksa sesi...</p>
            </main>
        );
    }

    if (!account) {
        return (
            <main className="customer-login">
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

    return (
        <div className="customer-layout">
            <header className="customer-header">
                <a
                    className="customer-brand"
                    href="#dashboard"
                    onClick={(event) => {
                        event.preventDefault();
                        setSelectedTask(null);
                        setPage('dashboard');
                    }}
                >
                    Super Runner
                </a>

                <div className="customer-header-account">
                    <span>{account.name}</span>
                    <button
                        type="button"
                        onClick={handleLogout}
                        disabled={loading}
                    >
                        {loading ? '...' : 'Logout'}
                    </button>
                </div>
            </header>

            <div className="customer-body">
                <nav className="customer-sidebar" aria-label="Menu utama">
                    <button
                        type="button"
                        className={page === 'dashboard' ? 'active' : ''}
                        onClick={() => {
                            setSelectedTask(null);
                            setPage('dashboard');
                        }}
                    >
                        Dashboard
                    </button>
                    <button
                        type="button"
                        className={page === 'tasks' ? 'active' : ''}
                        onClick={() => {
                            setSelectedTask(null);
                            setPage('tasks');
                        }}
                    >
                        Tugasan Saya
                    </button>
                    <button
                        type="button"
                        className={page === 'profile' ? 'active' : ''}
                        onClick={() => {
                            setSelectedTask(null);
                            setPage('profile');
                        }}
                    >
                        Profil
                    </button>
                </nav>

                <main className="customer-content">
                    {error && <p role="alert">{error}</p>}

                    {selectedTask
                        ? renderTaskDetail(selectedTask)
                        : page === 'dashboard'
                            ? renderDashboard()
                            : page === 'tasks'
                                ? (
                                    <section className="customer-panel">
                                        <h2>Tugasan Saya</h2>
                                        {renderTaskList()}
                                    </section>
                                )
                                : renderProfile()}
                </main>
            </div>
        </div>
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
