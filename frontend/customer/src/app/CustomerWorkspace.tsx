
import {
    useCallback,
    useEffect,
    useState,
} from 'react';

import type { FormEvent } from 'react';
import type {
    CustomerAccount,
    CustomerTaskRatingPayload,
} from '../api';

import {
    customerApi,
    CUSTOMER_SESSION_EXPIRED_EVENT,
} from '../api';

import AppShell from './AppShell';
import { useCustomerNavigation } from './useCustomerNavigation';

import HomePage from '../pages/HomePage';
import RequestsPage from '../pages/RequestsPage';
import CreateRequestPage from '../pages/CreateRequestPage';
import TasksPage from '../pages/TasksPage';
import TaskDetailPage from '../pages/TaskDetailPage';
import ProfilePage from '../pages/ProfilePage';

import { useCustomerRequests } from '../features/requests/useCustomerRequests';
import { useCreateCustomerRequest } from '../features/requests/useCreateCustomerRequest';
import { useCustomerTasks } from '../features/tasks/useCustomerTasks';
import { useCustomerProfile } from '../features/profile/useCustomerProfile';

type CustomerWorkspaceProps = {
    account: CustomerAccount;
    onAccountUpdated: (account: CustomerAccount) => void;
    onLogout: () => void;
    logoutLoading: boolean;
    logoutError: string;
};

export default function CustomerWorkspace({
    account,
    onAccountUpdated,
    onLogout,
    logoutLoading,
    logoutError,
}: CustomerWorkspaceProps) {
    const navigation = useCustomerNavigation();

    const [profileSuccessMessage, setProfileSuccessMessage] =
        useState('');

    const [ratingSaving, setRatingSaving] = useState(false);
    const [ratingError, setRatingError] = useState('');
    const [ratingMessage, setRatingMessage] = useState('');

    const tasks = useCustomerTasks({
        enabled:
            navigation.activePage === 'dashboard' ||
            navigation.activePage === 'tasks',
    });

    const requests = useCustomerRequests({
        enabled:
            navigation.activePage === 'dashboard' ||
            navigation.activePage === 'requests',
        onQuotationAwarded: tasks.refresh,
    });

    const createRequest = useCreateCustomerRequest({
        runnerSlug: navigation.runnerSlug,
        hasInvalidRunnerSlug: navigation.hasInvalidRunnerSlug,
        onCreated: () => {
            requests.setCurrentPage(1);
            requests.refresh();
        },
    });

    const profile = useCustomerProfile({
        account,
        onAccountUpdated,
    });

    useEffect(() => {
        if (navigation.activePage === 'dashboard') {
            requests.setCurrentPage(1);
        }
    }, [navigation.activePage, requests.setCurrentPage]);

    const handleNavigate = useCallback(
        (page: typeof navigation.activePage) => {
            tasks.closeTask();
            navigation.navigate(page);

            setRatingError('');
            setRatingMessage('');

            if (page !== 'profile') {
                setProfileSuccessMessage('');
                profile.clearMessages();
            }

            if (page !== 'requests') {
                requests.clearActionMessages();
            }

            if (page === 'create') {
                createRequest.clearMessages();
            }
        },
        [
            tasks.closeTask,
            navigation,
            requests.clearActionMessages,
            createRequest.clearMessages,
            profile.clearMessages,
        ],
    );

    const handleOpenTask = useCallback(
        (item: Parameters<typeof tasks.openTask>[0]) => {
            setRatingError('');
            setRatingMessage('');
            tasks.openTask(item);
        },
        [tasks.openTask],
    );

    const handleSubmitTaskRating = useCallback(
        async (payload: CustomerTaskRatingPayload): Promise<void> => {
            const selectedTask = tasks.selectedTask;

            if (!selectedTask) {
                setRatingError(
                    'Butiran tugasan tidak tersedia. Sila cuba semula.',
                );
                return;
            }

            setRatingSaving(true);
            setRatingError('');
            setRatingMessage('');

            try {
                if (selectedTask.rating) {
                    await customerApi.updateTaskRating(
                        selectedTask.task.task_id,
                        payload,
                    );

                    setRatingMessage(
                        'Penilaian berjaya dikemaskini.',
                    );
                } else {
                    await customerApi.submitTaskRating(
                        selectedTask.task.task_id,
                        payload,
                    );

                    setRatingMessage(
                        'Penilaian berjaya dihantar.',
                    );
                }

                tasks.refreshSelectedTask();
            } catch (error: unknown) {
                let message =
                    'Penilaian tidak berjaya disimpan. Sila cuba semula.';

                if (
                    typeof error === 'object' &&
                    error !== null &&
                    'response' in error
                ) {
                    const response = (
                        error as {
                            response?: {
                                data?: {
                                    message?: string;
                                };
                            };
                        }
                    ).response;

                    if (response?.data?.message) {
                        message = response.data.message;
                    }
                }

                setRatingError(message);
            } finally {
                setRatingSaving(false);
            }
        },
        [
            tasks.selectedTask,
            tasks.refreshSelectedTask,
        ],
    );

    const handleProfileNameChange = useCallback(
        (value: string) => {
            setProfileSuccessMessage('');
            profile.setProfileName(value);
        },
        [profile.setProfileName],
    );

    const handleProfilePictureSelect = useCallback(
        async (file: File): Promise<boolean> => {
            setProfileSuccessMessage('');
            return await profile.uploadProfilePicture(file);
        },
        [profile.uploadProfilePicture],
    );

    const handleChangePassword = useCallback(
        async (
            payload: {
                current_password: string;
                new_password: string;
                new_password_confirmation: string;
            },
        ): Promise<boolean> => {
            return await profile.changePassword(payload);
        },
        [profile.changePassword],
    );

    const handleReturnToLogin = useCallback(() => {
        window.dispatchEvent(
            new Event(CUSTOMER_SESSION_EXPIRED_EVENT),
        );
    }, []);

    async function handleProfileSubmit(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        setProfileSuccessMessage('');

        const updated = await profile.updateProfile();

        if (updated) {
            setProfileSuccessMessage(
                'Profil berjaya dikemaskini.',
            );
        }
    }

    function renderTaskDetail() {
        if (tasks.detailLoading) {
            return (
                <section className="customer-panel">
                    <p>Memuatkan butiran tugasan...</p>
                </section>
            );
        }

        if (tasks.detailError) {
            return (
                <section className="customer-panel">
                    <button
                        className="customer-back-button"
                        type="button"
                        onClick={tasks.closeTask}
                    >
                        ← Kembali ke senarai
                    </button>

                    <p role="alert">{tasks.detailError}</p>
                </section>
            );
        }

        if (tasks.selectedTask) {
            return (
                <TaskDetailPage
                    item={tasks.selectedTask}
                    onBack={tasks.closeTask}
                    onSubmitRating={handleSubmitTaskRating}
                    ratingSaving={ratingSaving}
                    ratingError={ratingError}
                    ratingMessage={ratingMessage}
                />
            );
        }

        return null;
    }

    function renderPage() {
        if (
            tasks.selectedTaskId &&
            navigation.activePage !== 'profile' &&
            navigation.activePage !== 'create' &&
            navigation.activePage !== 'requests'
        ) {
            return renderTaskDetail();
        }

        switch (navigation.activePage) {
            case 'dashboard':
                return (
                    <HomePage
                        account={account}
                        taskTotal={tasks.total}
                        activeTaskTotal={tasks.activeTotal}
                        ownRequestTotal={requests.total}
                        tasks={tasks.tasks}
                        ownRequests={requests.requests}
                        tasksLoading={tasks.loading}
                        tasksError={tasks.error}
                        ownRequestsLoading={requests.loading}
                        ownRequestsError={requests.error}
                        onOpenTask={handleOpenTask}
                        onNavigateTasks={() =>
                            handleNavigate('tasks')
                        }
                        onNavigateRequests={() =>
                            handleNavigate('requests')
                        }
                        onNavigateCreate={() =>
                            handleNavigate('create')
                        }
                    />
                );

            case 'requests':
                return (
                    <RequestsPage
                        requests={requests.requests}
                        currentPage={requests.currentPage}
                        lastPage={requests.lastPage}
                        total={requests.total}
                        loading={requests.loading}
                        error={requests.error}
                        cancellingRequestId={
                            requests.cancellingRequestId
                        }
                        actionMessage={requests.actionMessage}
                        actionError={requests.actionError}
                        onCancel={requests.cancelRequest}
                        onPageChange={requests.setCurrentPage}
                        onQuotationAwarded={
                            requests.handleQuotationAwarded
                        }
                    />
                );

            case 'create':
                return (
                    <CreateRequestPage
                        taskClass={createRequest.taskClass}
                        description={createRequest.description}
                        pickupAddress={createRequest.pickupAddress}
                        pickupContact={createRequest.pickupContact}
                        pickupPhone={createRequest.pickupPhone}
                        deliveryAddress={createRequest.deliveryAddress}
                        deliveryContact={createRequest.deliveryContact}
                        deliveryPhone={createRequest.deliveryPhone}
                        taskAddress={createRequest.taskAddress}
                        taskContact={createRequest.taskContact}
                        taskPhone={createRequest.taskPhone}
                        expiresAt={createRequest.expiresAt}
                        requestLoading={createRequest.loading}
                        requestSuccess={createRequest.success}
                        error={createRequest.error}
                        runnerSlug={navigation.runnerSlug}
                        hasInvalidRunnerSlug={
                            navigation.hasInvalidRunnerSlug
                        }
                        onTaskClassChange={createRequest.setTaskClass}
                        onDescriptionChange={
                            createRequest.setDescription
                        }
                        onPickupAddressChange={
                            createRequest.setPickupAddress
                        }
                        onPickupContactChange={
                            createRequest.setPickupContact
                        }
                        onPickupPhoneChange={
                            createRequest.setPickupPhone
                        }
                        onDeliveryAddressChange={
                            createRequest.setDeliveryAddress
                        }
                        onDeliveryContactChange={
                            createRequest.setDeliveryContact
                        }
                        onDeliveryPhoneChange={
                            createRequest.setDeliveryPhone
                        }
                        onTaskAddressChange={
                            createRequest.setTaskAddress
                        }
                        onTaskContactChange={
                            createRequest.setTaskContact
                        }
                        onTaskPhoneChange={
                            createRequest.setTaskPhone
                        }
                        onExpiresAtChange={
                            createRequest.setExpiresAt
                        }
                        onSubmit={createRequest.submit}
                    />
                );

            case 'tasks':
                return (
                    <TasksPage
                        tasks={tasks.tasks}
                        tasksLoading={tasks.loading}
                        tasksError={tasks.error}
                        taskPage={tasks.currentPage}
                        taskLastPage={tasks.lastPage}
                        onOpenTask={handleOpenTask}
                        onPageChange={tasks.setCurrentPage}
                    />
                );

            case 'profile':
                return (
                    <ProfilePage
                        account={account}
                        profileName={profile.profileName}
                        profileSaving={profile.saving}
                        profileError={profile.error}
                        profileMessage={
                            profileSuccessMessage ||
                            profile.message
                        }
                        pictureUploading={profile.pictureUploading}
                        pictureError={profile.pictureError}
                        pictureMessage={profile.pictureMessage}
                        passwordSaving={profile.passwordSaving}
                        passwordError={profile.passwordError}
                        passwordMessage={profile.passwordMessage}
                        onNameChange={handleProfileNameChange}
                        onSubmit={handleProfileSubmit}
                        onPictureSelect={handleProfilePictureSelect}
                        onChangePassword={handleChangePassword}
                        onReturnToLogin={handleReturnToLogin}
                    />
                );
        }
    }

    return (
        <AppShell
            activePage={navigation.activePage}
            accountName={account.profile_name}
            onNavigate={handleNavigate}
            onLogout={onLogout}
            loading={logoutLoading}
        >
            {logoutError && (
                <p role="alert">{logoutError}</p>
            )}

            {renderPage()}
        </AppShell>
    );
}
