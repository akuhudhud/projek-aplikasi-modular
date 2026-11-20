
import {
    useEffect,
    useState,
    type FormEvent,
} from 'react';

import type {
    RunnerAccount,
    RunnerNotification,
} from '../api';

import AppShell, {
    type RunnerDashboardTab,
} from './AppShell';

import RunnerProfileSettings from './RunnerProfileSettings';

import TasksPage from '../pages/TasksPage';
import HistoryPage from '../pages/HistoryPage';
import RequestsPage from '../pages/RequestsPage';
import NotificationsPage from '../pages/NotificationsPage';

import { useRunnerTasks } from '../features/tasks/useRunnerTasks';
import { useRunnerMarketplace } from '../features/marketplace/useRunnerMarketplace';
import { useRunnerNotifications } from '../features/notifications/useRunnerNotifications';

interface RunnerWorkspaceProps {
    account: RunnerAccount;
    onLogout: () => void;
    logoutLoading: boolean;
    logoutError: string;
}

export default function RunnerWorkspace({
    account,
    onLogout,
    logoutLoading,
    logoutError,
}: RunnerWorkspaceProps) {
    const [activeTab, setActiveTab] =
        useState<RunnerDashboardTab>('TASKS');

    const [quotationSuccess, setQuotationSuccess] = useState('');

    const tasksModule = useRunnerTasks();
    const marketplaceModule = useRunnerMarketplace();
    const notificationsModule = useRunnerNotifications();

    useEffect(() => {
        if (activeTab === 'HISTORY') {
            void tasksModule.loadHistory();
        }

        if (activeTab === 'REQUESTS') {
            void marketplaceModule.loadRequests();
        }

        if (activeTab === 'NOTIFICATIONS') {
            void notificationsModule.loadNotifications();
        }
    }, [
        activeTab,
        tasksModule.loadHistory,
        marketplaceModule.loadRequests,
        notificationsModule.loadNotifications,
    ]);

    async function handleSubmitQuotation(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();
        setQuotationSuccess('');

        const submitted = await marketplaceModule.submitQuotation();

        if (submitted) {
            setQuotationSuccess(
                'Sebut harga berjaya dihantar kepada pelanggan.',
            );
        }
    }

    function handleOpenQuotation(requestId: string) {
        setQuotationSuccess('');
        tasksModule.clearActionMessages();

        void marketplaceModule.openQuotation(requestId);
    }

    function handleCloseQuotation() {
        marketplaceModule.closeQuotation();
        setQuotationSuccess('');
    }

    function handleOpenNotificationRequest(
        notification: RunnerNotification,
    ) {
        const requestId = notification.data.request_id;

        if (!requestId) {
            return;
        }

        setQuotationSuccess('');
        tasksModule.clearActionMessages();

        if (!notification.read_at) {
            void notificationsModule.markNotificationRead(
                notification,
            );
        }

        setActiveTab('REQUESTS');

        void marketplaceModule.openNotificationRequest(
            requestId,
        );
    }

    const activeTaskCount = tasksModule.tasksLoading
        ? null
        : tasksModule.activeTasks.length;

    const completedTaskCount = tasksModule.historyLoading
        ? null
        : tasksModule.completedTaskCount;

    const actionError = tasksModule.actionError;
    const actionSuccess = tasksModule.actionSuccess;

    return (
        <AppShell
            profileName={account.profile_name}
            activeTab={activeTab}
            unreadTotal={notificationsModule.unreadTotal}
            activeTaskCount={activeTaskCount}
            completedTaskCount={completedTaskCount}
            onTabChange={setActiveTab}
            onLogout={onLogout}
            logoutLoading={logoutLoading}
        >
            {logoutError && (
                <p className="runner-dashboard__alert" role="alert">
                    {logoutError}
                </p>
            )}

            {actionError && (
                <p className="runner-dashboard__alert" role="alert">
                    {actionError}
                </p>
            )}

            {actionSuccess && (
                <p className="runner-dashboard__success" role="status">
                    {actionSuccess}
                </p>
            )}

            {quotationSuccess && (
                <p className="runner-dashboard__success" role="status">
                    {quotationSuccess}
                </p>
            )}

            {activeTab === 'TASKS' && (
                <TasksPage
                    tasks={tasksModule.tasks}
                    pagination={tasksModule.tasksPagination}
                    loading={tasksModule.tasksLoading}
                    error={tasksModule.tasksError}
                    actionLoading={tasksModule.actionLoading}
                    onRefresh={() => tasksModule.loadTasks()}
                    onPageChange={tasksModule.loadTasks}
                    onStart={tasksModule.startTask}
                    onArrive={tasksModule.arriveTask}
                    onComplete={tasksModule.completeTask}
                />
            )}

            {activeTab === 'HISTORY' && (
                <HistoryPage
                    history={tasksModule.history}
                    pagination={tasksModule.historyPagination}
                    loading={tasksModule.historyLoading}
                    error={tasksModule.historyError}
                    actionLoading={tasksModule.actionLoading}
                    onRefresh={() => tasksModule.loadHistory()}
                    onPageChange={tasksModule.loadHistory}
                />
            )}

            {activeTab === 'REQUESTS' && (
                <RequestsPage
                    requests={marketplaceModule.requests}
                    pagination={marketplaceModule.requestsPagination}
                    loading={marketplaceModule.requestsLoading}
                    error={marketplaceModule.requestsError}
                    quotationRequestId={marketplaceModule.quotationRequestId}
                    quotationAmount={marketplaceModule.quotationAmount}
                    quotationMessage={marketplaceModule.quotationMessage}
                    quotationLoading={marketplaceModule.quotationLoading}
                    quotationDetailLoading={marketplaceModule.quotationDetailLoading}
                    existingQuotation={marketplaceModule.existingQuotation}
                    notificationRequest={marketplaceModule.notificationRequest}
                    notificationRequestLoading={marketplaceModule.notificationRequestLoading}
                    notificationRequestError={marketplaceModule.notificationRequestError}
                    onRefresh={() => marketplaceModule.loadRequests()}
                    onPageChange={marketplaceModule.loadRequests}
                    onOpenQuotation={handleOpenQuotation}
                    onCloseQuotation={handleCloseQuotation}
                    onAmountChange={marketplaceModule.setQuotationAmount}
                    onMessageChange={marketplaceModule.setQuotationMessage}
                    onSubmitQuotation={handleSubmitQuotation}
                    onCloseNotificationRequest={marketplaceModule.closeNotificationRequest}
                />
            )}

            {activeTab === 'NOTIFICATIONS' && (
                <NotificationsPage
                    notifications={notificationsModule.notifications}
                    pagination={notificationsModule.notificationsPagination}
                    loading={notificationsModule.notificationsLoading}
                    error={notificationsModule.notificationsError}
                    onRefresh={() => notificationsModule.loadNotifications()}
                    onPageChange={notificationsModule.loadNotifications}
                    onMarkRead={notificationsModule.markNotificationRead}
                    onOpenRequest={handleOpenNotificationRequest}
                />
            )}

            {activeTab === 'PROFILE' && (
                <RunnerProfileSettings
                    account={account}
                    onLogout={onLogout}
                    logoutLoading={logoutLoading}
                />
            )}
        </AppShell>
    );
}
