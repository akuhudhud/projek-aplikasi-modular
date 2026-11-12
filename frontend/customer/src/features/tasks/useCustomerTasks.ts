
import {
    useCallback,
    useEffect,
    useState,
} from 'react';

import {
    customerApi,
    type CustomerTaskItem,
    type CustomerTaskRating,
} from '../../api';

export type CustomerTaskDetailItem = CustomerTaskItem & {
    rating: CustomerTaskRating | null;
};

type CustomerTasksState = {
    tasks: CustomerTaskItem[];
    total: number;
    activeTotal: number;
    currentPage: number;
    lastPage: number;
    loading: boolean;
    error: string;
    selectedTask: CustomerTaskDetailItem | null;
    selectedTaskId: string | null;
    detailLoading: boolean;
    detailError: string;
};

type CustomerTasksActions = {
    setCurrentPage: (page: number) => void;
    refresh: () => void;
    refreshSelectedTask: () => void;
    openTask: (item: CustomerTaskItem) => void;
    closeTask: () => void;
};

export type CustomerTasks = CustomerTasksState &
    CustomerTasksActions;

type UseCustomerTasksOptions = {
    enabled: boolean;
};

export function useCustomerTasks({
    enabled,
}: UseCustomerTasksOptions): CustomerTasks {
    const [tasks, setTasks] = useState<CustomerTaskItem[]>([]);
    const [total, setTotal] = useState(0);
    const [activeTotal, setActiveTotal] = useState(0);

    const [currentPage, setCurrentPageState] = useState(1);
    const [lastPage, setLastPage] = useState(1);

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const [refreshKey, setRefreshKey] = useState(0);
    const [detailRefreshKey, setDetailRefreshKey] = useState(0);

    const [selectedTask, setSelectedTask] =
        useState<CustomerTaskDetailItem | null>(null);

    const [selectedTaskId, setSelectedTaskId] =
        useState<string | null>(null);

    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState('');

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let mounted = true;

        setLoading(true);
        setError('');

        customerApi.getTasks(currentPage)
            .then((result) => {
                if (!mounted) {
                    return;
                }

                setTasks(result.tasks);
                setTotal(result.pagination.total);
                setActiveTotal(result.summary.active_total);
                setLastPage(result.pagination.last_page);
            })
            .catch(() => {
                if (!mounted) {
                    return;
                }

                setTasks([]);
                setError(
                    'Tidak berjaya memuatkan senarai tugasan.',
                );
            })
            .finally(() => {
                if (mounted) {
                    setLoading(false);
                }
            });

        return () => {
            mounted = false;
        };
    }, [enabled, currentPage, refreshKey]);

    useEffect(() => {
        if (!selectedTaskId) {
            return;
        }

        let mounted = true;

        setSelectedTask(null);
        setDetailError('');
        setDetailLoading(true);

        customerApi.getTask(selectedTaskId)
            .then((result) => {
                if (!mounted) {
                    return;
                }

                setSelectedTask({
                    task: result.task,
                    request: result.request,
                    rating: result.rating,
                });
            })
            .catch(() => {
                if (mounted) {
                    setDetailError(
                        'Tidak berjaya memuatkan butiran tugasan. Sila cuba semula.',
                    );
                }
            })
            .finally(() => {
                if (mounted) {
                    setDetailLoading(false);
                }
            });

        return () => {
            mounted = false;
        };
    }, [selectedTaskId, detailRefreshKey]);

    const setCurrentPage = useCallback((page: number) => {
        setCurrentPageState(Math.max(1, page));
    }, []);

    const refresh = useCallback(() => {
        setRefreshKey((current) => current + 1);
    }, []);

    const refreshSelectedTask = useCallback(() => {
        if (selectedTaskId) {
            setDetailRefreshKey((current) => current + 1);
        }
    }, [selectedTaskId]);

    const openTask = useCallback((item: CustomerTaskItem) => {
        setSelectedTask(null);
        setDetailError('');
        setSelectedTaskId(item.task.task_id);
    }, []);

    const closeTask = useCallback(() => {
        setSelectedTaskId(null);
        setSelectedTask(null);
        setDetailError('');
        setDetailLoading(false);
    }, []);

    return {
        tasks,
        total,
        activeTotal,
        currentPage,
        lastPage,
        loading,
        error,
        selectedTask,
        selectedTaskId,
        detailLoading,
        detailError,
        setCurrentPage,
        refresh,
        refreshSelectedTask,
        openTask,
        closeTask,
    };
}
