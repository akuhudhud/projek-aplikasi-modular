
// @vitest-environment jsdom

import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import {
    act,
    cleanup,
    renderHook,
    waitFor,
} from '@testing-library/react';

const {
    getNotificationsMock,
    markNotificationAsReadMock,
    deleteNotificationMock,
    deleteAllNotificationsMock,
} = vi.hoisted(() => ({
    getNotificationsMock: vi.fn(),
    markNotificationAsReadMock: vi.fn(),
    deleteNotificationMock: vi.fn(),
    deleteAllNotificationsMock: vi.fn(),
}));

vi.mock('../../api', () => ({
    customerApi: {
        getNotifications: getNotificationsMock,
        markNotificationAsRead: markNotificationAsReadMock,
        deleteNotification: deleteNotificationMock,
        deleteAllNotifications: deleteAllNotificationsMock,
    },
}));

import { useCustomerNotifications } from './useCustomerNotifications';

function makeNotification(
    id = 'notification-123',
    readAt: string | null = null,
) {
    return {
        id,
        type: 'CustomerRequestCreated',
        data: {
            title: 'Permintaan baharu',
            message: 'Permintaan anda telah diterima.',
            request_id: 'request-123',
        },
        read_at: readAt,
        created_at: '2026-11-14T05:00:00.000Z',
    };
}

function makeNotificationsResult(
    notifications = [makeNotification()],
) {
    return {
        notifications,
        summary: {
            unread_total: notifications.filter(
                (item) => item.read_at === null,
            ).length,
        },
        pagination: {
            current_page: 1,
            per_page: 25,
            total: notifications.length,
            last_page: 3,
        },
    };
}

describe('useCustomerNotifications', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        getNotificationsMock.mockResolvedValue(
            makeNotificationsResult(),
        );

        markNotificationAsReadMock.mockResolvedValue({
            id: 'notification-123',
            read_at: '2026-11-14T06:00:00.000Z',
        });

        deleteNotificationMock.mockResolvedValue(undefined);

        deleteAllNotificationsMock.mockResolvedValue({
            deleted_total: 1,
        });
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('does not load notifications when disabled', () => {
        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: false,
            }),
        );

        expect(getNotificationsMock).not.toHaveBeenCalled();
        expect(result.current.notifications).toEqual([]);
        expect(result.current.unreadTotal).toBe(0);
        expect(result.current.loading).toBe(false);
    });

    it('loads notifications and pagination', async () => {
        const notifications = [
            makeNotification('notification-1'),
            makeNotification(
                'notification-2',
                '2026-11-13T10:00:00.000Z',
            ),
        ];

        getNotificationsMock.mockResolvedValue({
            ...makeNotificationsResult(notifications),
            summary: {
                unread_total: 1,
            },
        });

        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        expect(result.current.loading).toBe(true);

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(getNotificationsMock).toHaveBeenCalledWith(1);
        expect(result.current.notifications).toEqual(notifications);
        expect(result.current.unreadTotal).toBe(1);
        expect(result.current.total).toBe(2);
        expect(result.current.lastPage).toBe(3);
        expect(result.current.error).toBe('');
    });

    it('changes page and prevents page number below one', async () => {
        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.setCurrentPage(2);
        });

        await waitFor(() => {
            expect(getNotificationsMock).toHaveBeenLastCalledWith(2);
        });

        expect(result.current.currentPage).toBe(2);

        act(() => {
            result.current.setCurrentPage(0);
        });

        await waitFor(() => {
            expect(getNotificationsMock).toHaveBeenLastCalledWith(1);
        });

        expect(result.current.currentPage).toBe(1);
    });

    it('refreshes the notification list', async () => {
        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(getNotificationsMock).toHaveBeenCalledTimes(1);
        });

        act(() => {
            result.current.refresh();
        });

        await waitFor(() => {
            expect(getNotificationsMock).toHaveBeenCalledTimes(2);
        });
    });

    it('shows an error when loading notifications fails', async () => {
        getNotificationsMock.mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.error).toBe(
            'Tidak berjaya memuatkan notifikasi. Sila cuba semula.',
        );
    });

    it('marks an unread notification as read', async () => {
        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            const success = await result.current.markAsRead(
                'notification-123',
            );

            expect(success).toBe(true);
        });

        expect(markNotificationAsReadMock).toHaveBeenCalledWith(
            'notification-123',
        );

        expect(result.current.notifications[0].read_at).toBe(
            '2026-11-14T06:00:00.000Z',
        );

        expect(result.current.unreadTotal).toBe(0);

        expect(result.current.actionMessage).toBe(
            'Notifikasi ditandakan sebagai dibaca.',
        );

        expect(result.current.actionLoadingId).toBeNull();
    });

    it('does not mark an already read notification again', async () => {
        getNotificationsMock.mockResolvedValue(
            makeNotificationsResult([
                makeNotification(
                    'notification-read',
                    '2026-11-13T10:00:00.000Z',
                ),
            ]),
        );

        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            const success = await result.current.markAsRead(
                'notification-read',
            );

            expect(success).toBe(false);
        });

        expect(markNotificationAsReadMock).not.toHaveBeenCalled();
    });

    it('shows an error when marking notification as read fails', async () => {
        markNotificationAsReadMock.mockRejectedValue(
            new Error('Update failed'),
        );

        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            const success = await result.current.markAsRead(
                'notification-123',
            );

            expect(success).toBe(false);
        });

        expect(result.current.actionError).toBe(
            'Tidak berjaya mengemaskini notifikasi.',
        );

        expect(result.current.unreadTotal).toBe(1);
        expect(result.current.actionLoadingId).toBeNull();
    });

    it('deletes one notification and updates totals', async () => {
        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            const success = await result.current.deleteNotification(
                'notification-123',
            );

            expect(success).toBe(true);
        });

        expect(deleteNotificationMock).toHaveBeenCalledWith(
            'notification-123',
        );

        expect(result.current.notifications).toEqual([]);
        expect(result.current.total).toBe(0);
        expect(result.current.unreadTotal).toBe(0);

        expect(result.current.actionMessage).toBe(
            'Notifikasi berjaya dipadam.',
        );

        expect(result.current.actionLoadingId).toBeNull();
    });

    it('does not reduce unread total when deleting a read notification', async () => {
        getNotificationsMock.mockResolvedValue(
            makeNotificationsResult([
                makeNotification(
                    'notification-read',
                    '2026-11-13T10:00:00.000Z',
                ),
                makeNotification('notification-unread'),
            ]),
        );

        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.unreadTotal).toBe(1);

        await act(async () => {
            await result.current.deleteNotification(
                'notification-read',
            );
        });

        expect(result.current.total).toBe(1);
        expect(result.current.unreadTotal).toBe(1);
        expect(result.current.notifications).toHaveLength(1);
    });

    it('shows an error when deleting one notification fails', async () => {
        deleteNotificationMock.mockRejectedValue(
            new Error('Delete failed'),
        );

        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            const success = await result.current.deleteNotification(
                'notification-123',
            );

            expect(success).toBe(false);
        });

        expect(result.current.notifications).toHaveLength(1);
        expect(result.current.total).toBe(1);

        expect(result.current.actionError).toBe(
            'Tidak berjaya memadam notifikasi.',
        );

        expect(result.current.actionLoadingId).toBeNull();
    });

    it('deletes all notifications and resets pagination', async () => {
        const notifications = [
            makeNotification('notification-1'),
            makeNotification('notification-2'),
        ];

        let resolvePageTwo:
            | ((value: ReturnType<typeof makeNotificationsResult>) => void)
            | undefined;

        getNotificationsMock.mockImplementation((page: number) => {
            if (page === 2) {
                return new Promise((resolve) => {
                    resolvePageTwo = resolve;
                });
            }

            return Promise.resolve(
                makeNotificationsResult(notifications),
            );
        });

        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        act(() => {
            result.current.setCurrentPage(2);
        });

        await waitFor(() => {
            expect(getNotificationsMock).toHaveBeenCalledWith(2);
            expect(result.current.loading).toBe(true);
            expect(resolvePageTwo).toBeDefined();
        });

        await act(async () => {
            resolvePageTwo?.(
                makeNotificationsResult(notifications),
            );
        });

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            const success = await result.current.deleteAll();
            expect(success).toBe(true);
        });

        expect(deleteAllNotificationsMock).toHaveBeenCalledTimes(1);
        expect(result.current.notifications).toEqual([]);
        expect(result.current.unreadTotal).toBe(0);
        expect(result.current.total).toBe(0);
        expect(result.current.currentPage).toBe(1);
        expect(result.current.lastPage).toBe(1);
        expect(result.current.actionMessage).toBe(
            'Semua notifikasi berjaya dipadam.',
        );
        expect(result.current.deletingAll).toBe(false);
    });

    it('does not delete all when there are no notifications', async () => {
        getNotificationsMock.mockResolvedValue(
            makeNotificationsResult([]),
        );

        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            const success = await result.current.deleteAll();

            expect(success).toBe(false);
        });

        expect(deleteAllNotificationsMock).not.toHaveBeenCalled();
    });

    it('shows an error when deleting all notifications fails', async () => {
        deleteAllNotificationsMock.mockRejectedValue(
            new Error('Delete all failed'),
        );

        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            const success = await result.current.deleteAll();

            expect(success).toBe(false);
        });

        expect(result.current.notifications).toHaveLength(1);
        expect(result.current.total).toBe(1);

        expect(result.current.actionError).toBe(
            'Tidak berjaya memadam semua notifikasi.',
        );

        expect(result.current.deletingAll).toBe(false);
    });

    it('clears action messages', async () => {
        markNotificationAsReadMock.mockRejectedValue(
            new Error('Update failed'),
        );

        const { result } = renderHook(() =>
            useCustomerNotifications({
                enabled: true,
            }),
        );

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        await act(async () => {
            await result.current.markAsRead('notification-123');
        });

        expect(result.current.actionError).not.toBe('');

        act(() => {
            result.current.clearActionMessages();
        });

        expect(result.current.actionMessage).toBe('');
        expect(result.current.actionError).toBe('');
    });
});
