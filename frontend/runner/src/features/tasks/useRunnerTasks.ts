
import { useCallback, useEffect, useMemo, useState } from 'react';

import {
    runnerApi,
    type RunnerTask,
} from '../../api';

import { getCurrentCoordinates } from '../../utils/geolocation';

interface UseRunnerTasksResult {
    tasks: RunnerTask[];
    history: RunnerTask[];
    tasksLoading: boolean;
    historyLoading: boolean;
    tasksError: string;
    historyError: string;
    actionLoading: string;
    actionError: string;
    actionSuccess: string;
    activeTasks: RunnerTask[];
    completedTasks: RunnerTask[];
    loadTasks: () => Promise<void>;
    loadHistory: () => Promise<void>;
    startTask: (task: RunnerTask) => Promise<void>;
    arriveTask: (task: RunnerTask) => Promise<void>;
    completeTask: (task: RunnerTask, file?: File) => Promise<void>;
    clearActionMessages: () => void;
}

export function useRunnerTasks(): UseRunnerTasksResult {
    const [tasks, setTasks] = useState<RunnerTask[]>([]);
    const [history, setHistory] = useState<RunnerTask[]>([]);

    const [tasksLoading, setTasksLoading] = useState(true);
    const [historyLoading, setHistoryLoading] = useState(true);

    const [tasksError, setTasksError] = useState('');
    const [historyError, setHistoryError] = useState('');

    const [actionLoading, setActionLoading] = useState('');
    const [actionError, setActionError] = useState('');
    const [actionSuccess, setActionSuccess] = useState('');

    const clearActionMessages = useCallback(() => {
        setActionError('');
        setActionSuccess('');
    }, []);

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

    useEffect(() => {
        void loadTasks();
        void loadHistory();
    }, [loadTasks, loadHistory]);

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
                typeof GeolocationPositionError !== 'undefined'
                && error instanceof GeolocationPositionError;

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
