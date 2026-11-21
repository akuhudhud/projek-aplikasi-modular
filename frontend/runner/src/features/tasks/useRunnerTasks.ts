
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';

import {
    runnerApi,
    type RunnerPagination,
    type RunnerTask,
} from '../../api';

import { getCurrentCoordinates } from '../../utils/geolocation';

const EMPTY_PAGINATION: RunnerPagination = {
    current_page: 1,
    per_page: 50,
    total: 0,
    last_page: 1,
};

interface UseRunnerTasksResult {
    tasks: RunnerTask[];
    history: RunnerTask[];
    tasksPagination: RunnerPagination;
    historyPagination: RunnerPagination;
    completedTaskCount: number;
    tasksLoading: boolean;
    historyLoading: boolean;
    tasksError: string;
    historyError: string;
    actionLoading: string;
    actionError: string;
    actionSuccess: string;
    activeTasks: RunnerTask[];
    completedTasks: RunnerTask[];
    loadTasks: (page?: number) => Promise<void>;
    loadHistory: (page?: number) => Promise<void>;
    startTask: (task: RunnerTask) => Promise<void>;
    arriveTask: (task: RunnerTask) => Promise<void>;
    completeTask: (task: RunnerTask, file?: File) => Promise<void>;
    clearActionMessages: () => void;
}

export function useRunnerTasks(): UseRunnerTasksResult {
    const [tasks, setTasks] = useState<RunnerTask[]>([]);
    const [history, setHistory] = useState<RunnerTask[]>([]);

    const [tasksPagination, setTasksPagination] =
        useState<RunnerPagination>(EMPTY_PAGINATION);

    const [historyPagination, setHistoryPagination] =
        useState<RunnerPagination>(EMPTY_PAGINATION);

    const [completedTaskCount, setCompletedTaskCount] = useState(0);

    const [tasksLoading, setTasksLoading] = useState(true);
    const [historyLoading, setHistoryLoading] = useState(true);

    const [tasksError, setTasksError] = useState('');
    const [historyError, setHistoryError] = useState('');

    const [actionLoading, setActionLoading] = useState('');
    const [actionError, setActionError] = useState('');
    const [actionSuccess, setActionSuccess] = useState('');

    // Each list has its own request sequence.
    // Only the latest request may update its state.
    const tasksRequestId = useRef(0);
    const historyRequestId = useRef(0);

    const clearActionMessages = useCallback(() => {
        setActionError('');
        setActionSuccess('');
    }, []);

    const loadTasks = useCallback(async (
        page = tasksPagination.current_page,
    ): Promise<void> => {
        const requestId = ++tasksRequestId.current;

        setTasksLoading(true);
        setTasksError('');

        try {
            const result = await runnerApi.getTasks(page);

            if (requestId !== tasksRequestId.current) {
                return;
            }

            setTasks(result.tasks);
            setTasksPagination(result.pagination);
        } catch {
            if (requestId !== tasksRequestId.current) {
                return;
            }

            setTasksError(
                'Tidak berjaya mendapatkan senarai tugasan. Sila cuba semula.',
            );
        } finally {
            if (requestId === tasksRequestId.current) {
                setTasksLoading(false);
            }
        }
    }, [tasksPagination.current_page]);

    const loadHistory = useCallback(async (
        page = historyPagination.current_page,
    ): Promise<void> => {
        const requestId = ++historyRequestId.current;

        setHistoryLoading(true);
        setHistoryError('');

        try {
            const result = await runnerApi.getTaskHistory(page);

            if (requestId !== historyRequestId.current) {
                return;
            }

            setHistory(result.tasks);
            setHistoryPagination(result.pagination);
            setCompletedTaskCount(
                result.summary?.completed_total ?? result.pagination.total,
            );
        } catch {
            if (requestId !== historyRequestId.current) {
                return;
            }

            setHistoryError(
                'Tidak berjaya mendapatkan sejarah tugasan.',
            );
        } finally {
            if (requestId === historyRequestId.current) {
                setHistoryLoading(false);
            }
        }
    }, [historyPagination.current_page]);

    useEffect(() => {
        void loadTasks(1);
        void loadHistory(1);
    }, []);

    // Invalidate pending requests when the hook unmounts.
    useEffect(() => {
        return () => {
            tasksRequestId.current += 1;
            historyRequestId.current += 1;
        };
    }, []);

    const startTask = useCallback(async (task: RunnerTask) => {
        if (actionLoading) return;

        setActionLoading(task.task_id);
        clearActionMessages();

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
    }, [actionLoading, clearActionMessages, loadTasks]);

    const arriveTask = useCallback(async (task: RunnerTask) => {
        if (actionLoading) return;

        setActionLoading(task.task_id);
        clearActionMessages();

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
            const isGeolocationError =
                error instanceof Error
                && error.name === 'RunnerGeolocationError';

            setActionError(
                isGeolocationError
                    ? 'Tidak berjaya mendapatkan GPS. Benarkan akses lokasi dan cuba semula.'
                    : 'Tidak berjaya merekodkan ketibaan. Sila cuba semula.',
            );
        } finally {
            setActionLoading('');
        }
    }, [actionLoading, clearActionMessages, loadTasks]);

    const completeTask = useCallback(async (
        task: RunnerTask,
        file?: File,
    ) => {
        if (actionLoading) return;

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
        clearActionMessages();

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
    }, [
        actionLoading,
        clearActionMessages,
        loadTasks,
        loadHistory,
    ]);

    const activeTasks = useMemo(
        () => tasks.filter(
            (task) =>
                task.generic_status === 'ASSIGNED'
                || task.generic_status === 'IN_PROGRESS',
        ),
        [tasks],
    );

    const completedTasks = useMemo(
        () => history.filter(
            (task) => task.generic_status === 'COMPLETED',
        ),
        [history],
    );

    return {
        tasks,
        history,
        tasksPagination,
        historyPagination,
        completedTaskCount,
        tasksLoading,
        historyLoading,
        tasksError,
        historyError,
        actionLoading,
        actionError,
        actionSuccess,
        activeTasks,
        completedTasks,
        loadTasks,
        loadHistory,
        startTask,
        arriveTask,
        completeTask,
        clearActionMessages,
    };
}
