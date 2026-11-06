import axios from 'axios';

export interface CustomerAccount {
    id: number | string;
    name: string;
    phone: string | null;
    email: string | null;
    profile_picture: string | null;
    role: string;
    status: string;
}

export interface CustomerTask {
    task_id: string;
    module_key: string;
    task_type: string;
    source_type: string;
    source_id: string;
    generic_status: string;
    assigned_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface CustomerTaskRequest {
    request_id: string;
    task_class: 'PICKUP_DELIVERY' | 'NON_DELIVERY';
    description: string;
    pickup_location_snapshot: Record<string, unknown> | null;
    delivery_location_snapshot: Record<string, unknown> | null;
    task_location_snapshot: Record<string, unknown> | null;
    status: string;
    expires_at: string | null;
    awarded_at: string | null;
    cancelled_at: string | null;
}

export interface CustomerTaskItem {
    task: CustomerTask;
    request: CustomerTaskRequest;
}

export interface CustomerTaskPagination {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export interface CustomerRequestPayload {
    source_type?: 'marketplace' | 'runner_profile_link';
    target_runner_slug?: string | null;
    task_class: 'PICKUP_DELIVERY' | 'NON_DELIVERY';
    description: string;
    pickup_location_snapshot?: Record<string, unknown>;
    delivery_location_snapshot?: Record<string, unknown>;
    task_location_snapshot?: Record<string, unknown>;
    expires_at: string;
}

export interface CustomerMarketplaceRequest {
    request_id: string;
    customer_account_id: number | string;
    target_runner_account_id: number | string | null;
    source_type: string;
    task_class: 'PICKUP_DELIVERY' | 'NON_DELIVERY';
    description: string;
    pickup_location_snapshot: Record<string, unknown> | null;
    delivery_location_snapshot: Record<string, unknown> | null;
    task_location_snapshot: Record<string, unknown> | null;
    status: string;
    expires_at: string | null;
    created_at: string;
}

export interface CustomerOwnMarketplaceRequest {
    request_id: string;
    task_class: 'PICKUP_DELIVERY' | 'NON_DELIVERY';
    description: string;
    pickup_location_snapshot: Record<string, unknown> | null;
    delivery_location_snapshot: Record<string, unknown> | null;
    task_location_snapshot: Record<string, unknown> | null;
    status: string;
    expires_at: string | null;
    awarded_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    updated_at: string;
}

interface ApiResponse<T> {
    success: boolean;
    message?: string;
    data: T;
}

interface LoginPayload {
    phone?: string;
    email?: string;
    password: string;
}

interface AccountData {
    account: CustomerAccount;
}

interface CustomerTasksData {
    tasks: CustomerTaskItem[];
    pagination: CustomerTaskPagination;
}

interface CustomerTaskData {
    task: CustomerTask;
    request: CustomerTaskRequest;
}

interface CustomerMarketplaceRequestData {
    request: CustomerMarketplaceRequest;
}

interface CustomerOwnMarketplaceRequestsData {
    requests: CustomerOwnMarketplaceRequest[];
    pagination: CustomerTaskPagination;
}

const api = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export const customerApi = {
    async login(payload: LoginPayload): Promise<CustomerAccount> {
        const response = await api.post<ApiResponse<AccountData>>(
            '/web/auth/login',
            payload,
        );

        return response.data.data.account;
    },

    async me(): Promise<CustomerAccount> {
        const response = await api.get<ApiResponse<AccountData>>(
            '/web/auth/me',
        );

        return response.data.data.account;
    },

    async logout(): Promise<void> {
        await api.post('/web/auth/logout');
    },

    async getTasks(page = 1): Promise<CustomerTasksData> {
        const response = await api.get<ApiResponse<CustomerTasksData>>(
            '/web/customer/tasks',
            {
                params: { page },
            },
        );

        return response.data.data;
    },

    async getTask(taskId: string): Promise<CustomerTaskData> {
        const response = await api.get<ApiResponse<CustomerTaskData>>(
            `/web/customer/tasks/${encodeURIComponent(taskId)}`,
        );

        return response.data.data;
    },

    async createRequest(
        payload: CustomerRequestPayload,
    ): Promise<CustomerMarketplaceRequest> {
        const response = await api.post<
            ApiResponse<CustomerMarketplaceRequestData>
        >(
            '/web/customer/requests',
            payload,
        );

        return response.data.data.request;
    },

    async getRequests(
        page = 1,
    ): Promise<CustomerOwnMarketplaceRequestsData> {
        const response = await api.get<
            ApiResponse<CustomerOwnMarketplaceRequestsData>
        >(
            '/web/customer/requests',
            {
                params: { page },
            },
        );

        return response.data.data;
    },
};
