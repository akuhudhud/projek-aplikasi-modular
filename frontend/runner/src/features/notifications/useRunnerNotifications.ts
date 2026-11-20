
import {
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';

import {
    runnerApi,
    type RunnerPagination,
    type RunnerNotification,
} from '../../api';

const EMPTY_PAGINATION: RunnerPagination = {
    current_page: 1,
    per_page: 25,
    total: 0,
    last_page: 1,
};

interface UseRunnerNotificationsResult {
    notifications: RunnerNotification[];
    notificationsPagination: RunnerPagination;
    unreadTotal: number;
    notificationsLoading: boolean;
    notificationsError: string;
    loadNotifications: (page?: number) => Promise<void>;
    markNotificationRead: (
        notification: RunnerNotification,
    ) => Promise<void>;
}

export function useRunnerNotifications(): UseRunnerNotificationsResult {
    const [notifications, setNotifications] =
        useState<RunnerNotification[]>([]);

    const [notificationsPagination, setNotificationsPagination] =
        useState<RunnerPagination>(EMPTY_PAGINATION);

    const [unreadTotal, setUnreadTotal] = useState(0);
    const [notificationsLoading, setNotificationsLoading] =
        useState(true);
    const [notificationsError, setNotificationsError] = useState('');

    const markingNotificationIds = useRef(new Set<string>());

    const loadNotifications = useCallback(async (
        page = notificationsPagination.current_page,
    ) => {
        setNotificationsLoading(true);
        setNotificationsError('');

        try {
            const result = await runnerApi.getNotifications(page);

            setNotifications(result.notifications);
            setNotificationsPagination(result.pagination);
            setUnreadTotal(result.summary.unread_total);
        } catch {
            setNotificationsError(
                'Tidak berjaya mendapatkan notifikasi.',
            );
        } finally {
            setNotificationsLoading(false);
        }
    }, [notificationsPagination.current_page]);

    useEffect(() => {
        void loadNotifications(1);
    }, []);

    const markNotificationRead = useCallback(async (
        notification: RunnerNotification,
    ) => {
        if (
            notification.read_at ||
            markingNotificationIds.current.has(notification.id)
        ) {
            return;
        }

        markingNotificationIds.current.add(notification.id);

        try {
            await runnerApi.markNotificationAsRead(notification.id);

            setNotifications((current) =>
                current.map((item) =>
                    item.id === notification.id
                        ? {
                            ...item,
                            read_at: new Date().toISOString(),
                        }
                        : item,
                ),
            );

            setUnreadTotal((current) => Math.max(0, current - 1));
        } catch {
            setNotificationsError(
                'Tidak berjaya mengemas kini notifikasi.',
            );
        } finally {
            markingNotificationIds.current.delete(notification.id);
        }
    }, []);

    return {
        notifications,
        notificationsPagination,
        unreadTotal,
        notificationsLoading,
        notificationsError,
        loadNotifications,
        markNotificationRead,
    };
}
