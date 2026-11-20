
import {
    fireEvent,
    render,
    screen,
} from '@testing-library/react';

import {
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

import RunnerWorkspace from './RunnerWorkspace';

import type {
    RunnerAccount,
    RunnerNotification,
    RunnerPagination,
} from '../api';

import {
    useRunnerTasks,
} from '../features/tasks/useRunnerTasks';

import {
    useRunnerMarketplace,
} from '../features/marketplace/useRunnerMarketplace';

import {
    useRunnerNotifications,
} from '../features/notifications/useRunnerNotifications';

vi.mock('../features/tasks/useRunnerTasks', () => ({
    useRunnerTasks: vi.fn(),
}));

vi.mock('../features/marketplace/useRunnerMarketplace', () => ({
    useRunnerMarketplace: vi.fn(),
}));

vi.mock('../features/notifications/useRunnerNotifications', () => ({
    useRunnerNotifications: vi.fn(),
}));

vi.mock('../pages/TasksPage', () => ({
    default: () => null,
}));

vi.mock('../pages/HistoryPage', () => ({
    default: () => null,
}));

vi.mock('../pages/RequestsPage', () => ({
    default: ({
        notificationRequest,
        notificationRequestLoading,
        notificationRequestError,
    }: {
        notificationRequest: {
            description: string;
        } | null;
        notificationRequestLoading: boolean;
        notificationRequestError: string;
    }) => (
        <section data-testid="requests-page">
            {notificationRequestLoading && (
                <p>Membuka permintaan...</p>
            )}

            {notificationRequestError && (
                <p role="alert">{notificationRequestError}</p>
            )}

            {notificationRequest && (
                <p>{notificationRequest.description}</p>
            )}
        </section>
    ),
}));

vi.mock('./RunnerProfileSettings', () => ({
    default: () => null,
}));

const mockAccount: RunnerAccount = {
    id: 1,
    profile_name: 'Runner Test',
    phone: '0123456789',
    email: 'runner@example.com',
    profile_picture: null,
    role: 'USER',
    status: 'ACTIVE',
    runner_status: 'APPROVED',
    identity_verification_status: 'VERIFIED',
};

const mockPagination: RunnerPagination = {
    current_page: 1,
    per_page: 15,
    total: 1,
    last_page: 1,
};

const notificationWithRequest: RunnerNotification = {
    id: 'notification-001',
    type: 'App\\Notifications\\MarketplaceEventNotification',
    data: {
        event: 'marketplace.request_created',
        title: 'Permintaan pelanggan baharu',
        message: 'Pelanggan telah membuat permintaan.',
        request_id: 'request-12345678',
    },
    read_at: null,
    created_at: '2026-11-20T10:00:00Z',
};

const notificationWithoutRequest: RunnerNotification = {
    id: 'notification-002',
    type: 'App\\Notifications\\MarketplaceEventNotification',
    data: {
        event: 'account.updated',
        title: 'Maklumat akaun dikemas kini',
        message: 'Maklumat akaun anda telah dikemas kini.',
    },
    read_at: null,
    created_at: '2026-11-20T10:05:00Z',
};

const loadHistory = vi.fn().mockResolvedValue(undefined);
const loadRequests = vi.fn().mockResolvedValue(undefined);
const loadNotifications = vi.fn().mockResolvedValue(undefined);
const markNotificationRead = vi.fn().mockResolvedValue(undefined);
const openNotificationRequest = vi.fn().mockResolvedValue(undefined);

const tasksModule = {
    tasks: [],
    history: [],
    tasksPagination: mockPagination,
    historyPagination: mockPagination,
    completedTaskCount: 0,
    tasksLoading: false,
    historyLoading: false,
    tasksError: '',
    historyError: '',
    actionLoading: '',
    actionError: '',
    actionSuccess: '',
    activeTasks: [],
    completedTasks: [],
    loadTasks: vi.fn().mockResolvedValue(undefined),
    loadHistory,
    startTask: vi.fn().mockResolvedValue(undefined),
    arriveTask: vi.fn().mockResolvedValue(undefined),
    completeTask: vi.fn().mockResolvedValue(undefined),
    clearActionMessages: vi.fn(),
} as unknown as ReturnType<typeof useRunnerTasks>;

const marketplaceModule = {
    requests: [],
    requestsPagination: mockPagination,
    requestsLoading: false,
    requestsError: '',
    quotationRequestId: null,
    quotationAmount: '',
    quotationMessage: '',
    quotationLoading: false,
    quotationDetailLoading: '',
    existingQuotation: null,
    notificationRequest: null,
    notificationRequestLoading: false,
    notificationRequestError: '',
    loadRequests,
    openQuotation: vi.fn().mockResolvedValue(undefined),
    closeQuotation: vi.fn(),
    openNotificationRequest,
    closeNotificationRequest: vi.fn(),
    setQuotationAmount: vi.fn(),
    setQuotationMessage: vi.fn(),
    submitQuotation: vi.fn().mockResolvedValue(false),
} as unknown as ReturnType<typeof useRunnerMarketplace>;

const notificationsModule = {
    notifications: [notificationWithRequest],
    notificationsPagination: mockPagination,
    unreadTotal: 1,
    notificationsLoading: false,
    notificationsError: '',
    loadNotifications,
    markNotificationRead,
} as unknown as ReturnType<typeof useRunnerNotifications>;

function renderWorkspace(
    notifications: RunnerNotification[] = [
        notificationWithRequest,
    ],
) {
    vi.mocked(useRunnerTasks).mockReturnValue(tasksModule);

    vi.mocked(useRunnerMarketplace).mockReturnValue(
        marketplaceModule,
    );

    vi.mocked(useRunnerNotifications).mockReturnValue({
        ...notificationsModule,
        notifications,
    });

    return render(
        <RunnerWorkspace
            account={mockAccount}
            onLogout={vi.fn()}
            logoutLoading={false}
            logoutError=""
        />,
    );
}

function openNotificationsTab() {
    fireEvent.click(
        screen.getByRole('button', {
            name: /NOTIFIKASI/,
        }),
    );
}

describe('Runner notification navigation', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        marketplaceModule.notificationRequest = null;
        marketplaceModule.notificationRequestLoading = false;
        marketplaceModule.notificationRequestError = '';
    });

    it('opens the Open Request tab and loads the related request', () => {
        renderWorkspace();

        openNotificationsTab();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'LIHAT PERMINTAAN',
            }),
        );

        expect(markNotificationRead).toHaveBeenCalledWith(
            notificationWithRequest,
        );

        expect(openNotificationRequest).toHaveBeenCalledWith(
            'request-12345678',
        );

        expect(
            screen.getByRole('button', {
                name: 'OPEN REQUEST',
            }),
        ).toHaveClass('is-active');

        expect(
            screen.getByTestId('requests-page'),
        ).toBeInTheDocument();
    });

    it('does not mark an already-read notification as read again', () => {
        const readNotification: RunnerNotification = {
            ...notificationWithRequest,
            read_at: '2026-11-20T10:10:00Z',
        };

        renderWorkspace([readNotification]);

        openNotificationsTab();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'LIHAT PERMINTAAN',
            }),
        );

        expect(markNotificationRead).not.toHaveBeenCalled();

        expect(openNotificationRequest).toHaveBeenCalledWith(
            'request-12345678',
        );

        expect(
            screen.getByTestId('requests-page'),
        ).toBeInTheDocument();
    });

    it('does not show the Open Request action when request_id is absent', () => {
        renderWorkspace([notificationWithoutRequest]);

        openNotificationsTab();

        expect(
            screen.getByText('Maklumat akaun dikemas kini'),
        ).toBeInTheDocument();

        expect(
            screen.queryByRole('button', {
                name: 'LIHAT PERMINTAAN',
            }),
        ).not.toBeInTheDocument();

        expect(openNotificationRequest).not.toHaveBeenCalled();
    });
});
