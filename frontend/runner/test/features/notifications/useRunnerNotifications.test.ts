
import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { runnerApi } from '../../../src/api';
import { useRunnerNotifications } from '../../../src/features/notifications/useRunnerNotifications';

vi.mock('../../../src/api', () => ({
    runnerApi: {
        getNotifications: vi.fn(),
        markNotificationAsRead: vi.fn(),
    },
}));

const pagination = {
    current_page: 1,
    per_page: 25,
    total: 2,
    last_page: 1,
};

const notifications = [
    {
        id: 'notification-001',
        type: 'MarketplaceEventNotification',
        data: {
            title: 'Permintaan baharu',
            message: 'Pelanggan membuat permintaan.',
            request_id: 'request-001',
        },
        read_at: null,
        created_at: '2026-11-20T10:00:00Z',
    },
    {
        id: 'notification-002',
        type: 'AccountNotification',
        data: {
            title: 'Akaun dikemas kini',
            message: 'Maklumat akaun dikemas kini.',
        },
        read_at: '2026-11-20T09:00:00Z',
        created_at: '2026-11-20T09:00:00Z',
    },
];

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;

    const promise = new Promise<T>((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

describe('useRunnerNotifications', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        vi.mocked(runnerApi.getNotifications).mockResolvedValue({
            notifications,
            pagination,
            summary: {
                unread_total: 1,
            },
        });
    });

    it('loads notifications when the hook mounts', async () => {
        const { result } = renderHook(() => useRunnerNotifications());

        await waitFor(() => {
            expect(result.current.notifications).toHaveLength(2);
        });

        expect(runnerApi.getNotifications).toHaveBeenCalledWith(1);
        expect(result.current.notificationsLoading).toBe(false);
        expect(result.current.notificationsError).toBe('');
    });

    it('updates the unread notification total', async () => {
        const { result } = renderHook(() => useRunnerNotifications());

        await waitFor(() => {
            expect(result.current.unreadTotal).toBe(1);
        });
    });

    it('loads the requested notification page', async () => {
        const { result } = renderHook(() => useRunnerNotifications());

        await waitFor(() => {
            expect(result.current.notificationsLoading).toBe(false);
        });

        await act(async () => {
            await result.current.loadNotifications(2);
        });

        expect(runnerApi.getNotifications).toHaveBeenLastCalledWith(2);
    });

    it('marks an unread notification as read', async () => {
        const { result } = renderHook(() => useRunnerNotifications());

        await waitFor(() => {
            expect(result.current.notifications).toHaveLength(2);
        });

        await act(async () => {
            await result.current.markNotificationRead(notifications[0]);
        });

        expect(runnerApi.markNotificationAsRead).toHaveBeenCalledWith(
            'notification-001',
        );

        expect(
            result.current.notifications.find(
                (item) => item.id === 'notification-001',
            )?.read_at,
        ).not.toBeNull();

        expect(result.current.unreadTotal).toBe(0);
    });

    it('does not send another request for an already-read notification', async () => {
        const { result } = renderHook(() => useRunnerNotifications());

        await waitFor(() => {
            expect(result.current.notifications).toHaveLength(2);
        });

        await act(async () => {
            await result.current.markNotificationRead(notifications[1]);
        });

        expect(runnerApi.markNotificationAsRead).not.toHaveBeenCalled();
    });

    it('prevents duplicate requests while marking the same notification as read', async () => {
        const { result } = renderHook(() => useRunnerNotifications());

        await waitFor(() => {
            expect(result.current.notifications).toHaveLength(2);
        });

        let resolveRequest!: () => void;

        vi.mocked(runnerApi.markNotificationAsRead).mockImplementation(
            () => new Promise<void>((resolve) => {
                resolveRequest = resolve;
            }),
        );

        let firstRequest!: Promise<void>;

        act(() => {
            firstRequest = result.current.markNotificationRead(
                notifications[0],
            );
        });

        await act(async () => {
            await result.current.markNotificationRead(notifications[0]);
        });

        expect(runnerApi.markNotificationAsRead).toHaveBeenCalledTimes(1);

        await act(async () => {
            resolveRequest();
            await firstRequest;
        });

        expect(result.current.unreadTotal).toBe(0);
    });

    it('keeps the latest notification page when requests finish out of order', async () => {
        const firstRequest = deferred<
            Awaited<ReturnType<typeof runnerApi.getNotifications>>
        >();

        const secondRequest = deferred<
            Awaited<ReturnType<typeof runnerApi.getNotifications>>
        >();

        vi.mocked(runnerApi.getNotifications)
            .mockReturnValueOnce(firstRequest.promise)
            .mockReturnValueOnce(secondRequest.promise);

        const { result } = renderHook(() => useRunnerNotifications());

        await waitFor(() => {
            expect(runnerApi.getNotifications).toHaveBeenCalledTimes(1);
        });

        act(() => {
            void result.current.loadNotifications(2);
        });

        expect(runnerApi.getNotifications).toHaveBeenCalledTimes(2);

        await act(async () => {
            secondRequest.resolve({
                notifications: [notifications[1]],
                pagination: {
                    ...pagination,
                    current_page: 2,
                },
                summary: {
                    unread_total: 0,
                },
            });
        });

        await waitFor(() => {
            expect(result.current.notifications).toEqual([notifications[1]]);
            expect(result.current.notificationsPagination.current_page).toBe(2);
        });

        await act(async () => {
            firstRequest.resolve({
                notifications: [notifications[0]],
                pagination,
                summary: {
                    unread_total: 1,
                },
            });
        });

        expect(result.current.notifications).toEqual([notifications[1]]);
        expect(result.current.notificationsPagination.current_page).toBe(2);
        expect(result.current.unreadTotal).toBe(0);
    });

    it('ignores notification responses after the hook unmounts', async () => {
        const pendingRequest = deferred<
            Awaited<ReturnType<typeof runnerApi.getNotifications>>
        >();

        vi.mocked(runnerApi.getNotifications).mockReturnValueOnce(
            pendingRequest.promise,
        );

        const { unmount } = renderHook(() => useRunnerNotifications());

        await waitFor(() => {
            expect(runnerApi.getNotifications).toHaveBeenCalledOnce();
        });

        unmount();

        pendingRequest.resolve({
            notifications,
            pagination,
            summary: {
                unread_total: 1,
            },
        });

        await pendingRequest.promise;

        expect(runnerApi.getNotifications).toHaveBeenCalledOnce();
    });

    it('shows an error when notifications cannot be loaded', async () => {
        vi.mocked(runnerApi.getNotifications).mockRejectedValue(
            new Error('Network error'),
        );

        const { result } = renderHook(() => useRunnerNotifications());

        await waitFor(() => {
            expect(result.current.notificationsError).toBe(
                'Tidak berjaya mendapatkan notifikasi.',
            );
        });

        expect(result.current.notificationsLoading).toBe(false);
    });

    it('shows an error when marking a notification as read fails', async () => {
        const { result } = renderHook(() => useRunnerNotifications());

        await waitFor(() => {
            expect(result.current.notifications).toHaveLength(2);
        });

        vi.mocked(runnerApi.markNotificationAsRead).mockRejectedValue(
            new Error('Network error'),
        );

        await act(async () => {
            await result.current.markNotificationRead(notifications[0]);
        });

        expect(result.current.notificationsError).toBe(
            'Tidak berjaya mengemas kini notifikasi.',
        );

        expect(result.current.unreadTotal).toBe(1);
    });
});
