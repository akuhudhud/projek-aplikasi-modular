
import {
    useCallback,
    useEffect,
    useState,
    type FormEvent,
} from 'react';

import {
    runnerApi,
    type RunnerAccount,
    type RunnerTask,
    type RunnerMarketplaceRequest,
    type RunnerQuotation,
} from '../api';

import {
    useRunnerSession,
} from '../features/auth/useRunnerSession';

import RunnerAuthPage from '../features/auth/RunnerAuthPage';

import RunnerAccessPage, {
    type RunnerAccessReason,
} from '../features/auth/RunnerAccessPage';

type DashboardTab = 'TASKS' | 'REQUESTS';

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

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function formatAmount(
    amount: number,
    currency: string,
): string {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency,
    }).format(amount);
}

function taskStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        ASSIGNED: 'DITUGASKAN',
        IN_PROGRESS: 'SEDANG BERJALAN',
        COMPLETED: 'SELESAI',
        CANCELLED: 'DIBATALKAN',
        OPEN: 'TERBUKA',
    };

    return labels[status] ?? status;
}

function requestStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        OPEN: 'TERBUKA',
        AWARDED: 'DITERIMA',
        CANCELLED: 'DIBATALKAN',
        EXPIRED: 'TAMAT TEMPOH',
    };

    return labels[status] ?? status;
}

function quotationStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        SUBMITTED: 'SEBUT HARGA DIHANTAR',
        ACCEPTED: 'SEBUT HARGA DITERIMA',
        NOT_SELECTED: 'TIDAK DIPILIH',
        WITHDRAWN: 'DITARIK BALIK',
        EXPIRED: 'TAMAT TEMPOH',
    };

    return labels[status] ?? status;
}

function getLatestQuotation(
    quotations: RunnerQuotation[],
): RunnerQuotation | null {
    if (quotations.length === 0) {
        return null;
    }

    return [...quotations].sort(
        (a, b) =>
            new Date(b.created_at).getTime()
            - new Date(a.created_at).getTime(),
    )[0];
}

function RunnerDashboard({
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
    const [activeTab, setActiveTab] =
        useState<DashboardTab>('TASKS');

    const [tasks, setTasks] = useState<RunnerTask[]>([]);
    const [requests, setRequests] =
        useState<RunnerMarketplaceRequest[]>([]);

    const [tasksLoading, setTasksLoading] = useState(true);
    const [requestsLoading, setRequestsLoading] = useState(false);

    const [tasksError, setTasksError] = useState('');
    const [requestsError, setRequestsError] = useState('');

    const [actionLoading, setActionLoading] = useState('');
    const [actionError, setActionError] = useState('');
    const [actionSuccess, setActionSuccess] = useState('');

    const [quotationRequestId, setQuotationRequestId] =
        useState<string | null>(null);

    const [quotationAmount, setQuotationAmount] = useState('');
    const [quotationMessage, setQuotationMessage] = useState('');

    const [quotationLoading, setQuotationLoading] =
        useState(false);

    const [quotationDetailLoading, setQuotationDetailLoading] =
        useState('');

    const [existingQuotation, setExistingQuotation] =
        useState<RunnerQuotation | null>(null);

    const loadTasks = useCallback(async () => {
        setTasksLoading(true);
        setTasksError('');

        try {
            const result = await runnerApi.getTasks();
            setTasks(result.tasks);
        } catch {
            setTasksError(
                'Tidak berjaya mendapatkan senarai tugasan. Sila cuba semula.',
            );
        } finally {
            setTasksLoading(false);
        }
    }, []);

    const loadRequests = useCallback(async () => {
        setRequestsLoading(true);
        setRequestsError('');

        try {
            const result = await runnerApi.getAvailableRequests();
            setRequests(result.requests);
        } catch {
            setRequestsError(
                'Tidak berjaya mendapatkan Open Request. Sila cuba semula.',
            );
        } finally {
            setRequestsLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadTasks();
    }, [loadTasks]);

    useEffect(() => {
        if (activeTab === 'REQUESTS') {
            void loadRequests();
        }
    }, [activeTab, loadRequests]);

    async function handleTaskAction(
        task: RunnerTask,
        action: 'START' | 'COMPLETE',
    ) {
        if (actionLoading) {
            return;
        }

        setActionLoading(task.task_id);
        setActionError('');
        setActionSuccess('');

        try {
            if (action === 'START') {
                await runnerApi.startTask(task.task_id);
                setActionSuccess('Tugasan berjaya dimulakan.');
            } else {
                await runnerApi.completeTask(task.task_id);
                setActionSuccess('Tugasan berjaya diselesaikan.');
            }

            await loadTasks();
        } catch {
            setActionError(
                'Tindakan tidak berjaya. Sila semak status tugasan dan cuba semula.',
            );
        } finally {
            setActionLoading('');
        }
    }

    async function handleOpenQuotation(requestId: string) {
        if (quotationDetailLoading || quotationLoading) {
            return;
        }

        if (quotationRequestId === requestId) {
            setQuotationRequestId(null);
            setExistingQuotation(null);
            setQuotationAmount('');
            setQuotationMessage('');
            return;
        }

        setQuotationDetailLoading(requestId);
        setActionError('');
        setActionSuccess('');
        setExistingQuotation(null);
        setQuotationAmount('');
        setQuotationMessage('');

        try {
            const detail = await runnerApi.getRequest(requestId);

            const latest = getLatestQuotation(
                detail.my_quotations ?? [],
            );

            setExistingQuotation(latest);

            // Paparkan status sebut harga sama ada masih aktif
            // atau sudah ditarik balik / tidak dipilih.
            // Borang hanya akan disembunyikan jika status SUBMITTED.
            setQuotationRequestId(requestId);
        } catch {
            setActionError(
                'Tidak berjaya mendapatkan status sebut harga. Sila cuba semula.',
            );
            setQuotationRequestId(null);
            setExistingQuotation(null);
        } finally {
            setQuotationDetailLoading('');
        }
    }

    async function handleSubmitQuotation(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        if (!quotationRequestId || quotationLoading) {
            return;
        }

        const amount = Number(quotationAmount);

        if (!Number.isFinite(amount) || amount <= 0) {
            setActionError('Masukkan jumlah sebut harga yang sah.');
            return;
        }

        const submittedRequestId = quotationRequestId;

        setQuotationLoading(true);
        setActionError('');
        setActionSuccess('');

        try {
            await runnerApi.submitQuotation(
                submittedRequestId,
                {
                    amount,
                    currency: 'MYR',
                    message: quotationMessage.trim() || null,
                },
            );

            setQuotationAmount('');
            setQuotationMessage('');

            setActionSuccess(
                'Sebut harga berjaya dihantar kepada pelanggan.',
            );

            await loadRequests();

            // Muat semula butiran supaya Runner dapat melihat
            // sebut harga yang baru dihantar dan statusnya.
            const detail = await runnerApi.getRequest(
                submittedRequestId,
            );

            const latest = getLatestQuotation(
                detail.my_quotations ?? [],
            );

            setExistingQuotation(latest);
            setQuotationRequestId(submittedRequestId);
        } catch {
            setActionError(
                'Sebut harga tidak berjaya dihantar. Permintaan mungkin telah tamat tempoh atau anda sudah mempunyai sebut harga aktif.',
            );
        } finally {
            setQuotationLoading(false);
        }
    }

    const activeTasks = tasks.filter(
        (task) =>
            task.generic_status === 'ASSIGNED' ||
            task.generic_status === 'IN_PROGRESS',
    );

    const completedTasks = tasks.filter(
        (task) => task.generic_status === 'COMPLETED',
    );

    function getRequestLocation(
        request: RunnerMarketplaceRequest,
    ): string {
        if (request.task_class === 'PICKUP_DELIVERY') {
            return [
                request.pickup_location_snapshot?.address,
                request.delivery_location_snapshot?.address,
            ]
                .filter(Boolean)
                .join(' → ');
        }

        return request.task_location_snapshot?.address
            ?? 'Lokasi tidak dinyatakan';
    }

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
                <p>Akaun Runner anda telah diluluskan.</p>
            </section>

            <section className="runner-dashboard__content">
                <div className="runner-dashboard__summary">
                    <article className="runner-dashboard__summary-card">
                        <span>TUGASAN AKTIF</span>
                        <strong>{activeTasks.length}</strong>
                    </article>

                    <article className="runner-dashboard__summary-card">
                        <span>TUGASAN SELESAI</span>
                        <strong>{completedTasks.length}</strong>
                    </article>

                    <article className="runner-dashboard__summary-card">
                        <span>OPEN REQUEST</span>
                        <strong>
                            {requestsLoading ? '…' : requests.length}
                        </strong>
                    </article>
                </div>

                <nav
                    className="runner-dashboard__tabs"
                    aria-label="Menu operasi Runner"
                >
                    <button
                        type="button"
                        className={activeTab === 'TASKS' ? 'is-active' : ''}
                        onClick={() => setActiveTab('TASKS')}
                    >
                        TUGASAN SAYA
                    </button>

                    <button
                        type="button"
                        className={activeTab === 'REQUESTS' ? 'is-active' : ''}
                        onClick={() => setActiveTab('REQUESTS')}
                    >
                        OPEN REQUEST
                    </button>
                </nav>

                {logoutError && (
                    <p className="runner-dashboard__alert" role="alert">
                        {logoutError}
                    </p>
                )}

                {actionError && (
                    <p className="runner-dashboard__alert" role="alert">
                        {actionError}
                    </p>
                )}

                {actionSuccess && (
                    <p className="runner-dashboard__success" role="status">
                        {actionSuccess}
                    </p>
                )}

                {activeTab === 'TASKS' && (
                    <section className="runner-dashboard__section">
                        <div className="runner-dashboard__section-heading">
                            <div>
                                <h2>Tugasan Saya</h2>
                                <p>
                                    Tugasan yang telah diberikan kepada anda.
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() => void loadTasks()}
                                disabled={tasksLoading}
                            >
                                {tasksLoading ? 'MEMUAT...' : 'SEGARKAN'}
                            </button>
                        </div>

                        {tasksLoading && (
                            <p className="runner-dashboard__notice">
                                Memuatkan tugasan...
                            </p>
                        )}

                        {tasksError && (
                            <p className="runner-dashboard__alert" role="alert">
                                {tasksError}
                            </p>
                        )}

                        {!tasksLoading && !tasksError && tasks.length === 0 && (
                            <div className="runner-dashboard__empty">
                                <h3>Belum Ada Tugasan</h3>
                                <p>
                                    Tugasan yang diberikan kepada anda akan
                                    dipaparkan di sini.
                                </p>
                            </div>
                        )}

                        <div className="runner-dashboard__list">
                            {tasks.map((task) => (
                                <article
                                    className="runner-dashboard__item"
                                    key={task.task_id}
                                >
                                    <div className="runner-dashboard__item-top">
                                        <strong>
                                            Tugasan #{task.task_id.slice(0, 8)}
                                        </strong>

                                        <span className={`runner-dashboard__status runner-dashboard__status--${task.generic_status.toLowerCase()}`}>
                                            {taskStatusLabel(task.generic_status)}
                                        </span>
                                    </div>

                                    <p>Rujukan: {task.source_id}</p>

                                    <small>
                                        Dicipta: {formatDate(task.created_at)}
                                    </small>

                                    {task.generic_status === 'ASSIGNED' && (
                                        <button
                                            type="button"
                                            className="runner-dashboard__primary-action"
                                            onClick={() =>
                                                void handleTaskAction(task, 'START')
                                            }
                                            disabled={actionLoading === task.task_id}
                                        >
                                            {actionLoading === task.task_id
                                                ? 'SEDANG DIPROSES...'
                                                : 'MULA TUGASAN'}
                                        </button>
                                    )}

                                    {task.generic_status === 'IN_PROGRESS' && (
                                        <button
                                            type="button"
                                            className="runner-dashboard__primary-action"
                                            onClick={() =>
                                                void handleTaskAction(task, 'COMPLETE')
                                            }
                                            disabled={actionLoading === task.task_id}
                                        >
                                            {actionLoading === task.task_id
                                                ? 'SEDANG DIPROSES...'
                                                : 'TANDA SELESAI'}
                                        </button>
                                    )}
                                </article>
                            ))}
                        </div>
                    </section>
                )}

                {activeTab === 'REQUESTS' && (
                    <section className="runner-dashboard__section">
                        <div className="runner-dashboard__section-heading">
                            <div>
                                <h2>Open Request</h2>
                                <p>
                                    Permintaan pelanggan yang tersedia untuk
                                    sebut harga anda.
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() => void loadRequests()}
                                disabled={requestsLoading}
                            >
                                {requestsLoading ? 'MEMUAT...' : 'SEGARKAN'}
                            </button>
                        </div>

                        {requestsLoading && (
                            <p className="runner-dashboard__notice">
                                Memuatkan Open Request...
                            </p>
                        )}

                        {requestsError && (
                            <p className="runner-dashboard__alert" role="alert">
                                {requestsError}
                            </p>
                        )}

                        {!requestsLoading &&
                            !requestsError &&
                            requests.length === 0 && (
                                <div className="runner-dashboard__empty">
                                    <h3>Tiada Open Request Buat Masa Ini</h3>
                                    <p>
                                        Permintaan pelanggan yang tersedia
                                        akan dipaparkan di sini.
                                    </p>
                                </div>
                            )}

                        <div className="runner-dashboard__list">
                            {requests.map((request) => {
                                const isQuotationOpen =
                                    quotationRequestId === request.request_id;

                                const isCheckingQuotation =
                                    quotationDetailLoading === request.request_id;

                                const hasActiveQuotation =
                                    isQuotationOpen &&
                                    existingQuotation?.status === 'SUBMITTED';

                                return (
                                    <article
                                        className="runner-dashboard__item"
                                        key={request.request_id}
                                    >
                                        <div className="runner-dashboard__item-top">
                                            <strong>
                                                {request.task_class === 'PICKUP_DELIVERY'
                                                    ? 'PICKUP & DELIVERY'
                                                    : 'NON-DELIVERY'}
                                            </strong>

                                            <span className="runner-dashboard__status runner-dashboard__status--open">
                                                {requestStatusLabel(request.status)}
                                            </span>
                                        </div>

                                        <p>{request.description}</p>

                                        <div className="runner-dashboard__request-location">
                                            <strong>Lokasi</strong>
                                            <span>{getRequestLocation(request)}</span>
                                        </div>

                                        <small>
                                            Tamat: {formatDate(request.expires_at)}
                                        </small>

                                        <button
                                            type="button"
                                            className="runner-dashboard__primary-action"
                                            onClick={() =>
                                                void handleOpenQuotation(request.request_id)
                                            }
                                            disabled={
                                                Boolean(quotationDetailLoading) ||
                                                quotationLoading
                                            }
                                        >
                                            {isCheckingQuotation
                                                ? 'MENYEMAK SEBUT HARGA...'
                                                : isQuotationOpen
                                                    ? 'TUTUP'
                                                    : 'SEMAK / HANTAR SEBUT HARGA'}
                                        </button>

                                        {isQuotationOpen && existingQuotation && (
                                            <div
                                                className="runner-dashboard__notice"
                                                role="status"
                                            >
                                                <strong>
                                                    Sebut harga terdahulu:
                                                </strong>

                                                <p>
                                                    Status: {
                                                        quotationStatusLabel(
                                                            existingQuotation.status,
                                                        )
                                                    }
                                                </p>

                                                <p>
                                                    Nilai: {
                                                        formatAmount(
                                                            Number(existingQuotation.amount),
                                                            existingQuotation.currency,
                                                        )
                                                    }
                                                </p>

                                                <small>
                                                    Dihantar: {
                                                        formatDate(
                                                            existingQuotation.created_at,
                                                        )
                                                    }
                                                </small>
                                            </div>
                                        )}

                                        {isQuotationOpen && hasActiveQuotation && (
                                            <p
                                                className="runner-dashboard__notice"
                                                role="status"
                                            >
                                                Sebut harga anda masih aktif dan
                                                sedang menunggu keputusan pelanggan.
                                                Anda tidak perlu menghantar sebut
                                                harga sekali lagi.
                                            </p>
                                        )}

                                        {isQuotationOpen && !hasActiveQuotation && (
                                            <form
                                                className="runner-dashboard__quotation-form"
                                                onSubmit={handleSubmitQuotation}
                                            >
                                                {existingQuotation && (
                                                    <p className="runner-dashboard__notice">
                                                        Sebut harga terdahulu: {
                                                            quotationStatusLabel(
                                                                existingQuotation.status,
                                                            )
                                                        }. Anda boleh menghantar
                                                        sebut harga baharu.
                                                    </p>
                                                )}

                                                <label>
                                                    Jumlah sebut harga (RM)
                                                    <input
                                                        type="number"
                                                        min="0.01"
                                                        step="0.01"
                                                        required
                                                        value={quotationAmount}
                                                        onChange={(event) =>
                                                            setQuotationAmount(
                                                                event.target.value,
                                                            )
                                                        }
                                                        placeholder="Contoh: 15.00"
                                                    />
                                                </label>

                                                <label>
                                                    Mesej kepada pelanggan (pilihan)
                                                    <textarea
                                                        rows={3}
                                                        value={quotationMessage}
                                                        onChange={(event) =>
                                                            setQuotationMessage(
                                                                event.target.value,
                                                            )
                                                        }
                                                        placeholder="Maklumat tambahan..."
                                                    />
                                                </label>

                                                <div className="runner-dashboard__quotation-actions">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setQuotationRequestId(null);
                                                            setExistingQuotation(null);
                                                            setQuotationAmount('');
                                                            setQuotationMessage('');
                                                        }}
                                                        disabled={quotationLoading}
                                                    >
                                                        BATAL
                                                    </button>

                                                    <button
                                                        type="submit"
                                                        disabled={quotationLoading}
                                                    >
                                                        {quotationLoading
                                                            ? 'MENGHANTAR...'
                                                            : 'HANTAR SEBUT HARGA'}
                                                    </button>
                                                </div>
                                            </form>
                                        )}
                                    </article>
                                );
                            })}
                        </div>
                    </section>
                )}
            </section>
        </main>
    );
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
        <RunnerDashboard
            account={session.account}
            onLogout={() => {
                void session.logout();
            }}
            logoutLoading={session.logoutLoading}
            logoutError={session.logoutError}
        />
    );
}
