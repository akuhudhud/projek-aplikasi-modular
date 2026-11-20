
import { useCallback, useEffect, useState } from 'react';

import {
    runnerApi,
    type RunnerNotification,
} from '../../api';

interface UseRunnerNotificationsResult {
    notifications: RunnerNotification[];
    unreadTotal: number;
    notificationsLoading: boolean;
    notificationsError: string;
    loadNotifications: () => Promise<void>;
    markNotificationRead: (
        notification: RunnerNotification,
    ) => Promise<void>;
}

export function useRunnerNotifications(): UseRunnerNotificationsResult {
    const [notifications, setNotifications] =
        useState<RunnerNotification[]>([]);

    const [unreadTotal, setUnreadTotal] = useState(0);
    const [notificationsLoading, setNotificationsLoading] =
        useState(true);
    const [notificationsError, setNotificationsError] = useState('');

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
        void loadNotifications();
    }, [loadNotifications]);

    const markNotificationRead = useCallback(async (
        notification: RunnerNotification,
    ) => {
        if (notification.read_at) {
            return;
        }

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
        }
    }, []);

    return {
        notifications,
        unreadTotal,
        notificationsLoading,
        notificationsError,
        loadNotifications,
        markNotificationRead,
    };
}
