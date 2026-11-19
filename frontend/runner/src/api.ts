
import axios from 'axios';

export const RUNNER_SESSION_EXPIRED_EVENT =
    'runner-session-expired';

export type RunnerStatus =
    | 'NOT_RUNNER'
    | 'PENDING'
    | 'APPROVED'
    | 'REJECTED'
    | 'SUSPENDED';

export type IdentityVerificationStatus =
    | 'NOT_SUBMITTED'
    | 'PENDING'
    | 'VERIFIED'
    | 'REJECTED';

export interface RunnerAccount {
    id: number | string;
    profile_name: string;
    phone: string | null;
    email: string | null;
    phone_verified_at?: string | null;
    email_verified_at?: string | null;
    profile_picture: string | null;
    role: string;
    status: string;
    runner_status: RunnerStatus;
    identity_verification_status: IdentityVerificationStatus;
}

export interface RunnerPagination {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export interface RunnerTask {
    task_id: string;
    module_key: string;
    task_type: string;
    source_type: string;
    source_id: string;
    assigned_account_id: number | string | null;
    generic_status: string;
    assigned_at: string | null;
    started_at: string | null;
    arrived_at: string | null;
    arrival_latitude: string | number | null;
    arrival_longitude: string | number | null;
    completed_at: string | null;
    completion_proof_path: string | null;
    cancelled_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface RunnerMarketplaceLocation {
    address: string;
    contact_name?: string | null;
    phone?: string | null;
}

export interface RunnerMarketplaceRequest {
    request_id: string;
    source_type: string;
    task_class: 'PICKUP_DELIVERY' | 'NON_DELIVERY';
    description: string;
    pickup_location_snapshot: RunnerMarketplaceLocation | null;
    delivery_location_snapshot: RunnerMarketplaceLocation | null;
    task_location_snapshot: RunnerMarketplaceLocation | null;
    status: string;
    expires_at: string | null;
    created_at: string;
    updated_at?: string;
}

export interface RunnerQuotation {
    quotation_id: string;
    amount: number;
    currency: string;
    message: string | null;
    status: string;
    expires_at: string | null;
    created_at: string;
}

export interface RunnerMarketplaceRequestDetail
    extends RunnerMarketplaceRequest {
    my_quotations?: RunnerQuotation[];
}

export interface RunnerPublicProfile {
    profile_id?: string;
    account_id?: number | string;
    public_slug: string;
    profile_name: string;
    bio: string | null;
    base_location: string | null;
    service_area: string | null;
    services: string[];
    is_published: boolean;
    views_count: number;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface RunnerProfileAnalytics {
    profile_views: number;
    requests_from_profile: number;
    quotations_for_profile_requests: number;
    completed_tasks_from_profile_requests: number;
}

export interface RunnerNotification {
    id: string;
    type: string;
    data: {
        event?: string;
        title?: string;
        message?: string;
        request_id?: string | null;
        quotation_id?: string | null;
        task_id?: string | null;
        [key: string]: unknown;
    };
    read_at: string | null;
    created_at: string;
}

interface ApiResponse<T> {
    success: boolean;
    message?: string;
    data: T;
}

interface AccountData {
    account: RunnerAccount;
}

interface LoginPayload {
    phone?: string;
    email?: string;
    password: string;
}

interface TasksData {
    tasks: RunnerTask[];
    pagination: RunnerPagination;
    summary?: {
        completed_total: number;
    };
}

interface MarketplaceRequestsData {
    requests: RunnerMarketplaceRequest[];
    pagination: RunnerPagination;
}

interface MarketplaceRequestData {
    request: RunnerMarketplaceRequestDetail;
}

interface QuotationData {
    quotation: RunnerQuotation & {
        request_id: string;
        runner_account_id: number | string;
    };
}

interface TaskData {
    task: RunnerTask;
}

interface ProfileData {
    profile: RunnerPublicProfile | null;
}

interface AnalyticsData {
    analytics: RunnerProfileAnalytics;
}

interface NotificationsData {
    notifications: RunnerNotification[];
    summary: {
        unread_total: number;
    };
    pagination: RunnerPagination;
}

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL ?? '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

function isProtectedSessionEndpoint(url?: string): boolean {
    if (!url) {
        return false;
    }

    const pathname = url.split('?')[0].replace(/\/+$/, '');

    return (
        pathname === '/web/auth/me'
        || pathname === '/web/auth/logout'
        || pathname.startsWith('/web/runner/')
    );
}

api.interceptors.response.use(
    (response) => response,
    (error: unknown) => {
        if (
            axios.isAxiosError(error)
            && error.response?.status === 401
            && isProtectedSessionEndpoint(error.config?.url)
            && typeof window !== 'undefined'
        ) {
            window.dispatchEvent(
                new Event(RUNNER_SESSION_EXPIRED_EVENT),
            );
        }

        return Promise.reject(error);
    },
);

export const runnerApi = {
    async login(payload: LoginPayload): Promise<RunnerAccount> {
        const response = await api.post<ApiResponse<AccountData>>(
            '/web/auth/login',
            payload,
        );

        return response.data.data.account;
    },

    async me(): Promise<RunnerAccount> {
        const response = await api.get<ApiResponse<AccountData>>(
            '/web/auth/me',
        );

        return response.data.data.account;
    },

    async logout(): Promise<void> {
        await api.post('/web/auth/logout');
    },

    async getTasks(): Promise<TasksData> {
        const response = await api.get<ApiResponse<TasksData>>(
            '/web/runner/tasks',
        );

        return response.data.data;
    },

    async getTaskHistory(page = 1): Promise<TasksData> {
        const response = await api.get<ApiResponse<TasksData>>(
            '/web/runner/tasks/history',
            {
                params: {
                    page,
                },
            },
        );

        return response.data.data;
    },

    async startTask(taskId: string): Promise<RunnerTask> {
        const response = await api.post<ApiResponse<TaskData>>(
            `/web/runner/tasks/${encodeURIComponent(taskId)}/start`,
        );

        return response.data.data.task;
    },

    async arriveTask(
        taskId: string,
        coordinates: {
            latitude: number;
            longitude: number;
        },
    ): Promise<RunnerTask> {
        const response = await api.post<ApiResponse<TaskData>>(
            `/web/runner/tasks/${encodeURIComponent(taskId)}/arrive`,
            coordinates,
        );

        return response.data.data.task;
    },

    async completeTask(
        taskId: string,
        completionProof: File,
    ): Promise<RunnerTask> {
        const formData = new FormData();

        formData.append(
            'completion_proof',
            completionProof,
            completionProof.name,
        );

        const response = await api.post<ApiResponse<TaskData>>(
            `/web/runner/tasks/${encodeURIComponent(taskId)}/complete`,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            },
        );

        return response.data.data.task;
    },

    async getNotifications(): Promise<NotificationsData> {
        const response = await api.get<ApiResponse<NotificationsData>>(
            '/web/runner/notifications',
        );

        return response.data.data;
    },

    async markNotificationAsRead(
        notificationId: string,
    ): Promise<void> {
        await api.patch(
            `/web/runner/notifications/${encodeURIComponent(notificationId)}/read`,
        );
    },

    async getAvailableRequests(): Promise<MarketplaceRequestsData> {
        const response = await api.get<ApiResponse<MarketplaceRequestsData>>(
            '/web/runner/requests',
            {
                params: {
                    view: 'available',
                },
            },
        );

        return response.data.data;
    },

    async getRequest(
        requestId: string,
    ): Promise<RunnerMarketplaceRequestDetail> {
        const response = await api.get<ApiResponse<MarketplaceRequestData>>(
            `/web/runner/requests/${encodeURIComponent(requestId)}`,
        );

        return response.data.data.request;
    },

    async submitQuotation(
        requestId: string,
        payload: {
            amount: number;
            currency?: string;
            message?: string | null;
        },
    ): Promise<QuotationData['quotation']> {
        const response = await api.post<ApiResponse<QuotationData>>(
            `/web/runner/requests/${encodeURIComponent(requestId)}/quotations`,
            payload,
        );

        return response.data.data.quotation;
    },

    async getProfile(): Promise<RunnerPublicProfile | null> {
        const response = await api.get<ApiResponse<ProfileData>>(
            '/web/runner/profile',
        );

        return response.data.data.profile;
    },

    async saveProfile(
        payload: {
            public_slug: string;
            bio?: string | null;
            base_location?: string | null;
            service_area?: string | null;
            services?: string[];
            is_published?: boolean;
        },
    ): Promise<RunnerPublicProfile> {
        const response = await api.post<ApiResponse<ProfileData>>(
            '/web/runner/profile',
            payload,
        );

        if (!response.data.data.profile) {
            throw new Error('Respons profil tidak sah.');
        }

        return response.data.data.profile;
    },

    async getProfileAnalytics(): Promise<RunnerProfileAnalytics> {
        const response = await api.get<ApiResponse<AnalyticsData>>(
            '/web/runner/profile/analytics',
        );

        return response.data.data.analytics;
    },
};
