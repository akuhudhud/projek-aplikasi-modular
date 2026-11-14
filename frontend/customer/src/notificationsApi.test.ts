import { beforeEach, describe, expect, it, vi } from 'vitest';

const {
    getMock,
    patchMock,
    deleteMock,
    responseInterceptorUseMock,
} = vi.hoisted(() => ({
    getMock: vi.fn(),
    patchMock: vi.fn(),
    deleteMock: vi.fn(),
    responseInterceptorUseMock: vi.fn(),
}));

vi.mock('axios', () => ({
    default: {
        create: vi.fn(() => ({
            get: getMock,
            patch: patchMock,
            delete: deleteMock,
            post: vi.fn(),
            interceptors: {
                response: {
                    use: responseInterceptorUseMock,
                },
            },
        })),
        isAxiosError: vi.fn(() => false),
    },
}));

import { customerApi } from './api';

const notification = {
    id: 'notification-123',
    type: 'App\\Notifications\\CustomerNotification',
    data: {
        title: 'Tempahan dikemas kini',
        message: 'Status tempahan anda telah dikemas kini.',
        reference_id: 'request-123',
    },
    read_at: null,
    created_at: '2026-11-14T10:00:00.000000Z',
};

describe('customerApi.getNotifications', () => {
    beforeEach(() => {
        getMock.mockReset();
    });

    it('loads notifications from the correct endpoint', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    notifications: [notification],
                    summary: {
                        unread_total: 1,
                    },
                    pagination: {
                        current_page: 1,
                        per_page: 25,
                        total: 1,
                        last_page: 1,
                    },
                },
            },
        });

        await customerApi.getNotifications();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock).toHaveBeenCalledWith(
            '/web/customer/notifications',
            {
                params: {
                    page: 1,
                },
            },
        );
    });

    it('supports pagination', async () => {
        getMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    notifications: [],
                    summary: {
                        unread_total: 0,
                    },
                    pagination: {
                        current_page: 2,
                        per_page: 25,
                        total: 30,
                        last_page: 2,
                    },
                },
            },
        });

        await customerApi.getNotifications(2);

        expect(getMock).toHaveBeenCalledWith(
            '/web/customer/notifications',
            {
                params: {
                    page: 2,
                },
            },
        );
    });

    it('returns notifications and unread summary', async () => {
        const resultData = {
            notifications: [notification],
            summary: {
                unread_total: 1,
            },
            pagination: {
                current_page: 1,
                per_page: 25,
                total: 1,
                last_page: 1,
            },
        };

        getMock.mockResolvedValue({
            data: {
                success: true,
                data: resultData,
            },
        });

        const result = await customerApi.getNotifications();

        expect(result).toEqual(resultData);
        expect(result.notifications[0].id).toBe(
            'notification-123',
        );
        expect(result.summary.unread_total).toBe(1);
    });

    it('propagates API errors to the caller', async () => {
        const apiError = new Error('Notifications failed.');

        getMock.mockRejectedValue(apiError);

        await expect(
            customerApi.getNotifications(),
        ).rejects.toBe(apiError);
    });
});

describe('customerApi.markNotificationAsRead', () => {
    beforeEach(() => {
        patchMock.mockReset();
    });

    it('sends the read request to the correct endpoint', async () => {
        patchMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    id: 'notification-123',
                    read_at: '2026-11-14T10:30:00.000000Z',
                },
            },
        });

        await customerApi.markNotificationAsRead(
            'notification-123',
        );

        expect(patchMock).toHaveBeenCalledTimes(1);
        expect(patchMock).toHaveBeenCalledWith(
            '/web/customer/notifications/notification-123/read',
        );
    });

    it('returns the updated notification read timestamp', async () => {
        const resultData = {
            id: 'notification-123',
            read_at: '2026-11-14T10:30:00.000000Z',
        };

        patchMock.mockResolvedValue({
            data: {
                success: true,
                data: resultData,
            },
        });

        const result = await customerApi.markNotificationAsRead(
            'notification-123',
        );

        expect(result).toEqual(resultData);
    });

    it('encodes the notification ID in the endpoint', async () => {
        patchMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    id: 'notification/123',
                    read_at: '2026-11-14T10:30:00.000000Z',
                },
            },
        });

        await customerApi.markNotificationAsRead(
            'notification/123',
        );

        expect(patchMock).toHaveBeenCalledWith(
            '/web/customer/notifications/notification%2F123/read',
        );
    });

    it('propagates API errors to the caller', async () => {
        const apiError = new Error('Mark as read failed.');

        patchMock.mockRejectedValue(apiError);

        await expect(
            customerApi.markNotificationAsRead(
                'notification-123',
            ),
        ).rejects.toBe(apiError);
    });
});

describe('customerApi.deleteNotification', () => {
    beforeEach(() => {
        deleteMock.mockReset();
    });

    it('deletes one notification using the correct endpoint', async () => {
        deleteMock.mockResolvedValue({
            data: {
                success: true,
                message: 'Notification deleted.',
            },
        });

        await customerApi.deleteNotification(
            'notification-123',
        );

        expect(deleteMock).toHaveBeenCalledTimes(1);
        expect(deleteMock).toHaveBeenCalledWith(
            '/web/customer/notifications/notification-123',
        );
    });

    it('encodes the notification ID in the endpoint', async () => {
        deleteMock.mockResolvedValue({
            data: {
                success: true,
            },
        });

        await customerApi.deleteNotification(
            'notification/123',
        );

        expect(deleteMock).toHaveBeenCalledWith(
            '/web/customer/notifications/notification%2F123',
        );
    });

    it('propagates API errors to the caller', async () => {
        const apiError = new Error('Delete notification failed.');

        deleteMock.mockRejectedValue(apiError);

        await expect(
            customerApi.deleteNotification(
                'notification-123',
            ),
        ).rejects.toBe(apiError);
    });
});

describe('customerApi.deleteAllNotifications', () => {
    beforeEach(() => {
        deleteMock.mockReset();
    });

    it('deletes all notifications using the collection endpoint', async () => {
        deleteMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    deleted_total: 5,
                },
            },
        });

        await customerApi.deleteAllNotifications();

        expect(deleteMock).toHaveBeenCalledTimes(1);
        expect(deleteMock).toHaveBeenCalledWith(
            '/web/customer/notifications',
        );
    });

    it('returns the number of deleted notifications', async () => {
        deleteMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    deleted_total: 5,
                },
            },
        });

        const result = await customerApi.deleteAllNotifications();

        expect(result).toBe(5);
    });

    it('returns zero when there are no notifications to delete', async () => {
        deleteMock.mockResolvedValue({
            data: {
                success: true,
                data: {
                    deleted_total: 0,
                },
            },
        });

        const result = await customerApi.deleteAllNotifications();

        expect(result).toBe(0);
    });

    it('propagates API errors to the caller', async () => {
        const apiError = new Error('Delete all failed.');

        deleteMock.mockRejectedValue(apiError);

        await expect(
            customerApi.deleteAllNotifications(),
        ).rejects.toBe(apiError);
    });
});
