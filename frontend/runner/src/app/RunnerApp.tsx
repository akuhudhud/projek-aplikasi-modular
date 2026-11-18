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
    type RunnerNotification,
} from '../api';

import {
    useRunnerSession,
} from '../features/auth/useRunnerSession';

import RunnerAuthPage from '../features/auth/RunnerAuthPage';

import RunnerAccessPage, {
    type RunnerAccessReason,
} from '../features/auth/RunnerAccessPage';

type DashboardTab =
    | 'TASKS'
    | 'HISTORY'
    | 'REQUESTS'
    | 'NOTIFICATIONS';

function getRunnerAccessReason(account: RunnerAccount): RunnerAccessReason | null {
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

function getCurrentCoordinates(): Promise<{
    latitude: number;
    longitude: number;
}> {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(
                new Error('Peranti tidak menyokong GPS browser.'),
            );
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                });
            },
            (error) => {
                reject(error);
            },
            {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0,
            },
        );
    });
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
    const [history, setHistory] = useState<RunnerTask[]>([]);
    const [requests, setRequests] =
        useState<RunnerMarketplaceRequest[]>([]);
    const [notifications, setNotifications] =
        useState<RunnerNotification[]>([]);

    const [unreadTotal, setUnreadTotal] = useState(0);

    const [tasksLoading, setTasksLoading] = useState(true);
    const [historyLoading, setHistoryLoading] = useState(false);
    const [requestsLoading, setRequestsLoading] = useState(false);
    const [notificationsLoading, setNotificationsLoading] =
        useState(false);

    const [tasksError, setTasksError] = useState('');
    const [historyError, setHistoryError] = useState('');
    const [requestsError, setRequestsError] = useState('');
    const [notificationsError, setNotificationsError] =
        useState('');

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

    const loadHistory = useCallback(async () => {
        setHistoryLoading(true);
        setHistoryError('');

        try {
            const result = await runnerApi.getTaskHistory();
            setHistory(result.tasks);
        } catch {
            setHistoryError(
                'Tidak berjaya mendapatkan sejarah tugasan.',
            );
        } finally {
            setHistoryLoading(false);
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

    const loadNotifications = useCallback(async () => {
        setNotificationsLoading(true);
        setNotificationsError('');

        try {
            const result = await runnerApi.getNotifications();
            setNotifications(result.notifications);
            setUnreadTotal(result.summary.unread_total);
        } catch {
            setNotificationsError(
                'Tidak berjaya mendapatkan notifikasi.',
            );
        } finally {
            setNotificationsLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadTasks();
    }, [loadTasks]);

    useEffect(() => {
        if (activeTab === 'HISTORY') {
            void loadHistory();
        }

        if (activeTab === 'REQUESTS') {
            void loadRequests();
        }

        if (activeTab === 'NOTIFICATIONS') {
            void loadNotifications();
        }
    }, [
        activeTab,
        loadHistory,
        loadRequests,
        loadNotifications,
    ]);

    async function handleStartTask(task: RunnerTask) {
        if (actionLoading) {
            return;
        }

        setActionLoading(task.task_id);
        setActionError('');
        setActionSuccess('');

        try {
            await runnerApi.startTask(task.task_id);
            setActionSuccess('Tugasan berjaya dimulakan.');
            await loadTasks();
        } catch {
            setActionError(
                'Tidak berjaya memulakan tugasan. Sila cuba semula.',
            );
        } finally {
            setActionLoading('');
        }
    }

    async function handleArriveTask(task: RunnerTask) {
        if (actionLoading) {
            return;
        }

        setActionLoading(task.task_id);
        setActionError('');
        setActionSuccess('');

        try {
            const coordinates = await getCurrentCoordinates();

            await runnerApi.arriveTask(
                task.task_id,
                coordinates,
            );

            setActionSuccess(
                'Lokasi ketibaan berjaya direkodkan.',
            );

            await loadTasks();
        } catch (error) {
            const message = error instanceof GeolocationPositionError
                ? 'Tidak berjaya mendapatkan GPS. Benarkan akses lokasi dan cuba semula.'
                : 'Tidak berjaya merekodkan ketibaan. Sila cuba semula.';

            setActionError(message);
        } finally {
            setActionLoading('');
        }
    }

    async function handleCompleteTask(
        task: RunnerTask,
        file?: File,
    ) {
        if (actionLoading) {
            return;
        }

        if (!file) {
            setActionError('Pilih gambar bukti tugasan dahulu.');
            return;
        }

        if (!file.type.startsWith('image/')) {
            setActionError('Fail bukti mestilah gambar.');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            setActionError('Saiz gambar tidak boleh melebihi 5 MB.');
            return;
        }

        setActionLoading(task.task_id);
        setActionError('');
        setActionSuccess('');

        try {
            await runnerApi.completeTask(task.task_id, file);

            setActionSuccess(
                'Tugasan berjaya diselesaikan. Bukti gambar telah dihantar.',
            );

            await Promise.all([
                loadTasks(),
                loadHistory(),
            ]);
        } catch {
            setActionError(
                'Tidak berjaya menyelesaikan tugasan. Semak sambungan dan cuba semula.',
            );
        } finally {
            setActionLoading('');
        }
    }
