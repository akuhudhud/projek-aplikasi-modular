
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
    RunnerPagination,
} from '../api';

import { useRunnerTasks } from '../features/tasks/useRunnerTasks';
import { useRunnerMarketplace } from '../features/marketplace/useRunnerMarketplace';
import { useRunnerNotifications } from '../features/notifications/useRunnerNotifications';

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
    default: () => null,
}));

vi.mock('../pages/NotificationsPage', () => ({
    default: () => null,
}));

vi.mock('./RunnerProfileSettings', () => ({
    default: () => null,
}));

const account: RunnerAccount = {
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

const pagination: RunnerPagination = {
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
};

const loadTasks = vi.fn().mockResolvedValue(undefined);
const loadHistory = vi.fn().mockResolvedValue(undefined);
const loadRequests = vi.fn().mockResolvedValue(undefined);
const loadNotifications = vi.fn().mockResolvedValue(undefined);

const tasksModule = {
    tasks: [],
    history: [],
    tasksPagination: pagination,
    historyPagination: pagination,
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
    loadTasks,
    loadHistory,
    startTask: vi.fn().mockResolvedValue(undefined),
    arriveTask: vi.fn().mockResolvedValue(undefined),
    completeTask: vi.fn().mockResolvedValue(undefined),
    clearActionMessages: vi.fn(),
} as unknown as ReturnType<typeof useRunnerTasks>;

const marketplaceModule = {
    requests: [],
    requestsPagination: pagination,
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
    openNotificationRequest: vi.fn().mockResolvedValue(undefined),
    closeNotificationRequest: vi.fn(),
    setQuotationAmount: vi.fn(),
    setQuotationMessage: vi.fn(),
    submitQuotation: vi.fn().mockResolvedValue(false),
} as unknown as ReturnType<typeof useRunnerMarketplace>;

const notificationsModule = {
    notifications: [],
    notificationsPagination: pagination,
    unreadTotal: 0,
    notificationsLoading: false,
    notificationsError: '',
    loadNotifications,
    markNotificationRead: vi.fn().mockResolvedValue(undefined),
} as unknown as ReturnType<typeof useRunnerNotifications>;

function renderWorkspace() {
    vi.mocked(useRunnerTasks).mockReturnValue(tasksModule);
    vi.mocked(useRunnerMarketplace).mockReturnValue(marketplaceModule);
    vi.mocked(useRunnerNotifications).mockReturnValue(notificationsModule);

    return render(
        <RunnerWorkspace
            account={account}
            onLogout={vi.fn()}
            logoutLoading={false}
            logoutError=""
        />,
    );
}

describe('Runner workspace tab loading', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('loads History once when the Runner opens the History tab', () => {
        renderWorkspace();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'SEJARAH',
            }),
        );

        expect(loadHistory).toHaveBeenCalledTimes(1);
    });

    it('does not reload the active tab when its callback reference changes', () => {
        const firstLoadHistory = vi.fn().mockResolvedValue(undefined);
        const updatedLoadHistory = vi.fn().mockResolvedValue(undefined);

        vi.mocked(useRunnerTasks).mockReturnValue({
            ...tasksModule,
            loadHistory: firstLoadHistory,
        });

        vi.mocked(useRunnerMarketplace).mockReturnValue(marketplaceModule);
        vi.mocked(useRunnerNotifications).mockReturnValue(notificationsModule);

        const view = render(
            <RunnerWorkspace
                account={account}
                onLogout={vi.fn()}
                logoutLoading={false}
                logoutError=""
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'SEJARAH',
            }),
        );

        expect(firstLoadHistory).toHaveBeenCalledTimes(1);

        vi.mocked(useRunnerTasks).mockReturnValue({
            ...tasksModule,
            loadHistory: updatedLoadHistory,
        });

        view.rerender(
            <RunnerWorkspace
                account={account}
                onLogout={vi.fn()}
                logoutLoading={false}
                logoutError=""
            />,
        );

        expect(firstLoadHistory).toHaveBeenCalledTimes(1);
        expect(updatedLoadHistory).not.toHaveBeenCalled();
    });

    it('loads Open Request when the Runner opens that tab', () => {
        renderWorkspace();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'OPEN REQUEST',
            }),
        );

        expect(loadRequests).toHaveBeenCalledTimes(1);
    });

    it('loads notifications when the Runner opens the notification tab', () => {
        renderWorkspace();

        fireEvent.click(
            screen.getByRole('button', {
                name: 'NOTIFIKASI',
            }),
        );

        expect(loadNotifications).toHaveBeenCalledTimes(1);
    });
});
