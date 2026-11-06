import axios from 'axios';

const customerApiClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL ?? '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export interface CustomerAccount {
    id: string;
    name: string;
    phone: string | null;
    email: string | null;
    role: string;
    status: string;
}

export interface CustomerTaskItem {
    task: {
        task_id: string;
        generic_status: string;
        created_at: string;
        assigned_at: string | null;
        started_at: string | null;
        completed_at: string | null;
    };
    request: {
        task_class: string;
        description: string;
        status: string;
        expires_at: string | null;
        pickup_location_snapshot: Record<string, unknown> | null;
        delivery_location_snapshot: Record<string, unknown> | null;
        task_location_snapshot: Record<string, unknown> | null;
    };
}

export interface CustomerRequestPayload {
    task_class: 'PICKUP_DELIVERY' | 'NON_DELIVERY';
    description: string;
    expires_at: string;
    pickup_location_snapshot?: {
        address: string;
        contact_name: string | null;
        phone: string | null;
    };
    delivery_location_snapshot?: {
        address: string;
        contact_name: string | null;
        phone: string | null;
    };
    task_location_snapshot?: {
        address: string;
        contact_name: string | null;
        phone: string | null;
    };
}

export interface CustomerOwnMarketplaceRequest {
    request_id: string;
    task_class: string;
    description: string;
    status: string;
    expires_at: string | null;
    created_at: string;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export const customerApi = {
    async login(payload: {
        email?: string;
        phone?: string;
        password: string;
    }): Promise<CustomerAccount> {
        const response = await customerApiClient.post(
            '/web/auth/login',
            payload,
        );

        return response.data.data;
    },

    async me(): Promise<CustomerAccount> {
        const response = await customerApiClient.get('/web/auth/me');

        return response.data.data;
    },

    async logout(): Promise<void> {
        await customerApiClient.post('/web/auth/logout');
    },

    async getTasks(page = 1): Promise<{
        tasks: CustomerTaskItem[];
        pagination: Pagination;
    }> {
        const response = await customerApiClient.get(
            '/web/customer/tasks',
            { params: { page } },
        );

        return response.data.data;
    },

    async getTask(taskId: string): Promise<CustomerTaskItem> {
        const response = await customerApiClient.get(
            `/web/customer/tasks/${encodeURIComponent(taskId)}`,
        );

        return response.data.data;
    },

    async getRequests(page = 1): Promise<{
        requests: CustomerOwnMarketplaceRequest[];
        pagination: Pagination;
    }> {
        const response = await customerApiClient.get(
            '/web/customer/requests',
            { params: { page } },
        );

        return response.data.data;
    },

    async createRequest(
        payload: CustomerRequestPayload,
    ): Promise<CustomerOwnMarketplaceRequest> {
        const response = await customerApiClient.post(
            '/web/customer/requests',
            payload,
        );

        return response.data.data;
    },
};
