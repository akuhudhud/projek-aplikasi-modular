
import {
    afterEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import {
    cleanup,
    fireEvent,
    render,
    screen,
} from '@testing-library/react';

import type { CustomerNotification } from '../../api';
import type { CustomerNotifications as CustomerNotificationsState } from './useCustomerNotifications';

import CustomerNotifications from './CustomerNotifications';

const notification: CustomerNotification = {
    id: 'notification-001',
    type: 'request',
    data: {
        title: 'Permintaan dikemaskini',
        message: 'Permintaan anda telah dikemaskini.',
    },
    read_at: null,
    created_at: '2026-11-14T08:00:00.000Z',
};

function createState(
    overrides: Partial<CustomerNotificationsState> = {},
): CustomerNotificationsState {
    return {
        notifications: [notification],
        unreadTotal: 1,
        currentPage: 1,
        lastPage: 1,
        total: 1,
        loading: false,
        error: '',
        actionLoadingId: null,
        deletingAll: false,
        actionMessage: '',
        actionError: '',
        setCurrentPage: vi.fn(),
        refresh: vi.fn(),
        markAsRead: vi.fn().mockResolvedValue(true),
        deleteNotification: vi.fn().mockResolvedValue(true),
        deleteAll: vi.fn().mockResolvedValue(true),
        clearActionMessages: vi.fn(),
        ...overrides,
    };
}

describe('CustomerNotifications', () => {
    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('renders the notification heading', () => {
        render(
            <CustomerNotifications
                state={createState()}
            />,
        );

        expect(
            screen.getByRole('heading', {
                name: 'Notifikasi',
            }),
        ).toBeInTheDocument();
    });

    it('displays notification title and message', () => {
        render(
            <CustomerNotifications
                state={createState()}
            />,
        );

        expect(
            screen.getByText('Permintaan dikemaskini'),
        ).toBeInTheDocument();

        expect(
            screen.getByText(
                'Permintaan anda telah dikemaskini.',
            ),
        ).toBeInTheDocument();
    });

    it('displays unread notification count', () => {
        render(
            <CustomerNotifications
                state={createState({
                    unreadTotal: 3,
                })}
            />,
        );

        expect(
            screen.getByText(
                '3 notifikasi belum dibaca',
            ),
        ).toBeInTheDocument();
    });

    it('displays read status when there are no unread notifications', () => {
        render(
            <CustomerNotifications
                state={createState({
                    unreadTotal: 0,
                })}
            />,
        );

        expect(
            screen.getByText(
                'Semua notifikasi telah dibaca',
            ),
        ).toBeInTheDocument();
    });

    it('displays empty state when there are no notifications', () => {
        render(
            <CustomerNotifications
                state={createState({
                    notifications: [],
                    unreadTotal: 0,
                    total: 0,
                })}
            />,
        );

        expect(
            screen.getByText('Tiada notifikasi'),
        ).toBeInTheDocument();

        expect(
            screen.getByText(
                'Notifikasi baharu akan dipaparkan di sini.',
            ),
        ).toBeInTheDocument();
    });

    it('displays loading state', () => {
        render(
            <CustomerNotifications
                state={createState({
                    loading: true,
                })}
            />,
        );

        expect(
            screen.getByText(
                'Memuatkan notifikasi...',
            ),
        ).toBeInTheDocument();
    });

    it('calls refresh when reload button is clicked', () => {
        const refresh = vi.fn();

        render(
            <CustomerNotifications
                state={createState({
                    refresh,
                })}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Muat semula',
            }),
        );

        expect(refresh).toHaveBeenCalledTimes(1);
    });

    it('disables reload button while loading', () => {
        render(
            <CustomerNotifications
                state={createState({
                    loading: true,
                })}
            />,
        );

        expect(
            screen.getByRole('button', {
                name: 'Muat semula',
            }),
        ).toBeDisabled();
    });

    it('displays loading error and retry action', () => {
        const refresh = vi.fn();

        render(
            <CustomerNotifications
                state={createState({
                    error: 'Tidak berjaya memuatkan notifikasi.',
                    refresh,
                })}
            />,
        );

        expect(
            screen.getByRole('alert'),
        ).toHaveTextContent(
            'Tidak berjaya memuatkan notifikasi.',
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Cuba lagi',
            }),
        );

        expect(refresh).toHaveBeenCalledTimes(1);
    });

    it('displays action success message', () => {
        render(
            <CustomerNotifications
                state={createState({
                    actionMessage: 'Notifikasi berjaya dikemaskini.',
                })}
            />,
        );

        expect(
            screen.getByRole('status'),
        ).toHaveTextContent(
            'Notifikasi berjaya dikemaskini.',
        );
    });

    it('displays action error message', () => {
        render(
            <CustomerNotifications
                state={createState({
                    actionError: 'Tindakan tidak berjaya.',
                })}
            />,
        );

        expect(
            screen.getByRole('alert'),
        ).toHaveTextContent(
            'Tindakan tidak berjaya.',
        );
    });

    it('calls markAsRead when mark as read button is clicked', () => {
        const markAsRead = vi.fn().mockResolvedValue(true);

        render(
            <CustomerNotifications
                state={createState({
                    markAsRead,
                })}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Tandakan dibaca',
            }),
        );

        expect(markAsRead).toHaveBeenCalledWith(
            'notification-001',
        );
    });

    it('does not display mark as read action for read notifications', () => {
        render(
            <CustomerNotifications
                state={createState({
                    notifications: [
                        {
                            ...notification,
                            read_at: '2026-11-14T09:00:00.000Z',
                        },
                    ],
                    unreadTotal: 0,
                })}
            />,
        );

        expect(
            screen.queryByRole('button', {
                name: 'Tandakan dibaca',
            }),
        ).not.toBeInTheDocument();
    });

    it('calls onOpenNotification when task notification is opened', () => {
        const taskNotification: CustomerNotification = {
            ...notification,
            data: {
                ...notification.data,
                task_id: 'task-001',
            },
        };

        const onOpenNotification = vi.fn();

        render(
            <CustomerNotifications
                state={createState({
                    notifications: [taskNotification],
                })}
                onOpenNotification={onOpenNotification}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat',
            }),
        );

        expect(onOpenNotification).toHaveBeenCalledTimes(1);
        expect(onOpenNotification).toHaveBeenCalledWith(
            taskNotification,
        );
    });

    it('calls onOpenNotification when request notification is opened', () => {
        const requestNotification: CustomerNotification = {
            ...notification,
            data: {
                ...notification.data,
                request_id: 'request-001',
            },
        };

        const onOpenNotification = vi.fn();

        render(
            <CustomerNotifications
                state={createState({
                    notifications: [requestNotification],
                })}
                onOpenNotification={onOpenNotification}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lihat',
            }),
        );

        expect(onOpenNotification).toHaveBeenCalledWith(
            requestNotification,
        );
    });

    it('does not display open action when notification has no destination', () => {
        const onOpenNotification = vi.fn();

        render(
            <CustomerNotifications
                state={createState()}
                onOpenNotification={onOpenNotification}
            />,
        );

        expect(
            screen.queryByRole('button', {
                name: 'Lihat',
            }),
        ).not.toBeInTheDocument();

        expect(onOpenNotification).not.toHaveBeenCalled();
    });

    it('does not display open action when callback is not provided', () => {
        const taskNotification: CustomerNotification = {
            ...notification,
            data: {
                ...notification.data,
                task_id: 'task-001',
            },
        };

        render(
            <CustomerNotifications
                state={createState({
                    notifications: [taskNotification],
                })}
            />,
        );

        expect(
            screen.queryByRole('button', {
                name: 'Lihat',
            }),
        ).not.toBeInTheDocument();
    });

    it('calls deleteNotification when delete button is clicked', () => {
        const deleteNotification = vi.fn().mockResolvedValue(true);

        render(
            <CustomerNotifications
                state={createState({
                    deleteNotification,
                })}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Padam',
            }),
        );

        expect(
            deleteNotification,
        ).toHaveBeenCalledWith('notification-001');
    });

    it('asks for confirmation before deleting all notifications', () => {
        const deleteAll = vi.fn().mockResolvedValue(true);
        const confirmSpy = vi
            .spyOn(window, 'confirm')
            .mockReturnValue(false);

        render(
            <CustomerNotifications
                state={createState({
                    deleteAll,
                })}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Padam semua',
            }),
        );

        expect(confirmSpy).toHaveBeenCalledWith(
            'Padam semua notifikasi?',
        );

        expect(deleteAll).not.toHaveBeenCalled();
    });

    it('deletes all notifications after confirmation', () => {
        const deleteAll = vi.fn().mockResolvedValue(true);

        vi.spyOn(window, 'confirm').mockReturnValue(true);

        render(
            <CustomerNotifications
                state={createState({
                    deleteAll,
                })}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Padam semua',
            }),
        );

        expect(deleteAll).toHaveBeenCalledTimes(1);
    });

    it('displays pagination when there are multiple pages', () => {
        render(
            <CustomerNotifications
                state={createState({
                    currentPage: 2,
                    lastPage: 4,
                    total: 40,
                })}
            />,
        );

        expect(
            screen.getByRole('navigation', {
                name: 'Halaman notifikasi',
            }),
        ).toBeInTheDocument();

        expect(
            screen.getByText('Halaman 2 daripada 4'),
        ).toBeInTheDocument();
    });

    it('calls setCurrentPage when next page is clicked', () => {
        const setCurrentPage = vi.fn();

        render(
            <CustomerNotifications
                state={createState({
                    currentPage: 1,
                    lastPage: 3,
                    setCurrentPage,
                })}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Seterusnya',
            }),
        );

        expect(setCurrentPage).toHaveBeenCalledWith(2);
    });

    it('calls setCurrentPage when previous page is clicked', () => {
        const setCurrentPage = vi.fn();

        render(
            <CustomerNotifications
                state={createState({
                    currentPage: 2,
                    lastPage: 3,
                    setCurrentPage,
                })}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Sebelum',
            }),
        );

        expect(setCurrentPage).toHaveBeenCalledWith(1);
    });

    it('disables previous page button on first page', () => {
        render(
            <CustomerNotifications
                state={createState({
                    currentPage: 1,
                    lastPage: 3,
                })}
            />,
        );

        expect(
            screen.getByRole('button', {
                name: 'Sebelum',
            }),
        ).toBeDisabled();
    });

    it('disables next page button on last page', () => {
        render(
            <CustomerNotifications
                state={createState({
                    currentPage: 3,
                    lastPage: 3,
                })}
            />,
        );

        expect(
            screen.getByRole('button', {
                name: 'Seterusnya',
            }),
        ).toBeDisabled();
    });
});
