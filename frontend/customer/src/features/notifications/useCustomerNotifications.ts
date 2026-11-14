import {
    useCallback,
    useEffect,
    useState,
} from 'react';

import {
    customerApi,
    type CustomerNotification,
} from '../../api';

type CustomerNotificationsState = {
    notifications: CustomerNotification[];
    unreadTotal: number;
    currentPage: number;
    lastPage: number;
    total: number;
    loading: boolean;
    error: string;
    actionLoadingId: string | null;
    deletingAll: boolean;
    actionMessage: string;
    actionError: string;
};

type CustomerNotificationsActions = {
    setCurrentPage: (page: number) => void;
    refresh: () => void;
    markAsRead: (notificationId: string) => Promise<boolean>;
    deleteNotification: (
        notificationId: string,
    ) => Promise<boolean>;
    deleteAll: () => Promise<boolean>;
    clearActionMessages: () => void;
};

export type CustomerNotifications =
    CustomerNotificationsState &
    CustomerNotificationsActions;

type UseCustomerNotificationsOptions = {
    enabled: boolean;
};

export function useCustomerNotifications({
    enabled,
}: UseCustomerNotificationsOptions): CustomerNotifications {
    const [notifications, setNotifications] = useState<
        CustomerNotification[]
    >([]);

    const [unreadTotal, setUnreadTotal] = useState(0);
    const [currentPage, setCurrentPageState] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const [refreshKey, setRefreshKey] = useState(0);

    const [actionLoadingId, setActionLoadingId] =
        useState<string | null>(null);

    const [deletingAll, setDeletingAll] = useState(false);

    const [actionMessage, setActionMessage] = useState('');
    const [actionError, setActionError] = useState('');

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let mounted = true;

        setLoading(true);
        setError('');

        customerApi.getNotifications(currentPage)
            .then((result) => {
                if (!mounted) {
                    return;
                }

                setNotifications(result.notifications);
                setUnreadTotal(result.summary.unread_total);
                setTotal(result.pagination.total);
                setLastPage(result.pagination.last_page);
            })
            .catch(() => {
                if (!mounted) {
                    return;
                }

                setError(
                    'Tidak berjaya memuatkan notifikasi. Sila cuba semula.',
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

    const setCurrentPage = useCallback((page: number) => {
        setCurrentPageState(Math.max(1, page));
    }, []);

    const refresh = useCallback(() => {
        setRefreshKey((current) => current + 1);
    }, []);

    const clearActionMessages = useCallback(() => {
        setActionMessage('');
        setActionError('');
    }, []);

    const markAsRead = useCallback(
        async (notificationId: string): Promise<boolean> => {
            if (
                actionLoadingId !== null ||
                deletingAll
            ) {
                return false;
            }

            const notification = notifications.find(
                (item) => item.id === notificationId,
            );

            if (!notification || notification.read_at !== null) {
                return false;
            }

            setActionLoadingId(notificationId);
            setActionMessage('');
            setActionError('');

            try {
                const result =
                    await customerApi.markNotificationAsRead(
                        notificationId,
                    );

                setNotifications((current) =>
                    current.map((item) =>
                        item.id === notificationId
                            ? {
                                ...item,
                                read_at: result.read_at,
                            }
                            : item,
                    ),
                );

                setUnreadTotal((current) =>
                    Math.max(0, current - 1),
                );

                setActionMessage(
                    'Notifikasi ditandakan sebagai dibaca.',
                );

                return true;
            } catch {
                setActionError(
                    'Tidak berjaya mengemaskini notifikasi.',
                );

                return false;
            } finally {
                setActionLoadingId(null);
            }
        },
        [
            actionLoadingId,
            deletingAll,
            notifications,
        ],
    );

    const deleteNotification = useCallback(
        async (notificationId: string): Promise<boolean> => {
            if (
                actionLoadingId !== null ||
                deletingAll
            ) {
                return false;
            }

            const notification = notifications.find(
                (item) => item.id === notificationId,
            );

            if (!notification) {
                return false;
            }

            setActionLoadingId(notificationId);
            setActionMessage('');
            setActionError('');

            try {
                await customerApi.deleteNotification(
                    notificationId,
                );

                setNotifications((current) =>
                    current.filter(
                        (item) => item.id !== notificationId,
                    ),
                );

                setTotal((current) =>
                    Math.max(0, current - 1),
                );

                if (notification.read_at === null) {
                    setUnreadTotal((current) =>
                        Math.max(0, current - 1),
                    );
                }

                setActionMessage(
                    'Notifikasi berjaya dipadam.',
                );

                return true;
            } catch {
                setActionError(
                    'Tidak berjaya memadam notifikasi.',
                );

                return false;
            } finally {
                setActionLoadingId(null);
            }
        },
        [
            actionLoadingId,
            deletingAll,
            notifications,
        ],
    );

    const deleteAll = useCallback(
        async (): Promise<boolean> => {
            if (
                actionLoadingId !== null ||
                deletingAll ||
                total === 0
            ) {
                return false;
            }

            setDeletingAll(true);
            setActionMessage('');
            setActionError('');

            try {
                await customerApi.deleteAllNotifications();

                setNotifications([]);
                setUnreadTotal(0);
                setTotal(0);
                setLastPage(1);
                setCurrentPageState(1);

                setActionMessage(
                    'Semua notifikasi berjaya dipadam.',
                );

                return true;
            } catch {
                setActionError(
                    'Tidak berjaya memadam semua notifikasi.',
                );

                return false;
            } finally {
                setDeletingAll(false);
            }
        },
        [
            actionLoadingId,
            deletingAll,
            total,
        ],
    );

    return {
        notifications,
        unreadTotal,
        currentPage,
        lastPage,
        total,
        loading,
        error,
        actionLoadingId,
        deletingAll,
        actionMessage,
        actionError,
        setCurrentPage,
        refresh,
        markAsRead,
        deleteNotification,
        deleteAll,
        clearActionMessages,
    };
}
