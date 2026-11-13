
import axios from 'axios';

export const CUSTOMER_SESSION_EXPIRED_EVENT =
    'customer-session-expired';

export interface CustomerAccount {
    id: number | string;
    profile_name: string;
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

export interface CustomerTaskRating {
    rating: number;
    review: string | null;
    created_at: string;
    updated_at: string;
}

export interface CustomerTaskRatingPayload {
    rating: number;
    review?: string | null;
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

export interface CustomerTaskSummary {
    active_total: number;
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

/**
 * Maklumat profil awam Runner yang dibenarkan
 * untuk dipaparkan kepada Customer.
 *
 * Tidak mengandungi account_id, nombor telefon,
 * e-mel, koordinat GPS atau maklumat identiti sah.
 */
export interface CustomerRunnerPublicProfile {
    public_slug: string;
    profile_name: string;
    bio: string | null;
    base_location: string | null;
    service_area: string | null;
    services: string[];
    rating_average: number | null;
    rating_count: number;
}

export interface CustomerMarketplaceQuotation {
    quotation_id: string;
    request_id: string;
    amount: string | number;
    currency: string;
    message: string | null;
    status: string;
    expires_at: string | null;
    accepted_at: string | null;
    withdrawn_at: string | null;
    created_at: string;

    // null jika profil belum diterbitkan atau Runner tidak layak.
    runner_profile: CustomerRunnerPublicProfile | null;
}

export interface CustomerMarketplaceRequestDetail {
    request: CustomerOwnMarketplaceRequest & {
        winning_quotation_id?: string | null;
    };
    quotations: CustomerMarketplaceQuotation[];
}

export interface CustomerAwardQuotationResult {
    request: {
        request_id: string;
        status: string;
        winning_quotation_id: string;
        awarded_at: string | null;
        updated_at: string;
    };
    task: {
        task_id: string;
        module_key: string;
        task_type: string;
        source_type: string;
        source_id: string;
        generic_status: string;
        assigned_at: string | null;
        created_at: string;
    };
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

export interface ChangePasswordPayload {
    current_password: string;
    new_password: string;
    new_password_confirmation: string;
}

interface AccountData {
    account: CustomerAccount;
}

interface CustomerTasksData {
    tasks: CustomerTaskItem[];
    summary: CustomerTaskSummary;
    pagination: CustomerTaskPagination;
}

interface CustomerTaskData {
    task: CustomerTask;
    request: CustomerTaskRequest;
    rating: CustomerTaskRating | null;
}

interface CustomerTaskRatingData {
    rating: CustomerTaskRating;
}

interface CustomerMarketplaceRequestData {
    request: CustomerMarketplaceRequest;
}

interface CustomerOwnMarketplaceRequestsData {
    requests: CustomerOwnMarketplaceRequest[];
    pagination: CustomerTaskPagination;
}

interface CustomerMarketplaceRequestDetailData {
    request: CustomerMarketplaceRequestDetail['request'];
    quotations: CustomerMarketplaceQuotation[];
}

interface CustomerAwardQuotationData {
    request: CustomerAwardQuotationResult['request'];
    task: CustomerAwardQuotationResult['task'];
}

interface CustomerCancelRequestData {
    request: {
        request_id: string;
        status: string;
        cancelled_at: string | null;
        updated_at: string;
    };
}

export interface RegistrationOtpRequestPayload {
    channel: 'phone' | 'email';
    contact: string;
    purpose: 'REGISTRATION';
}

export interface RegistrationOtpRequestResult {
    verification_id: string;
    expires_at: string;
}

export interface RegistrationOtpVerifyPayload {
    verification_id: string;
    otp: string;
}

export interface RegistrationOtpVerifyResult {
    verification_token: string;
    expires_at: string;
}

export interface RegistrationCompletePayload {
    verification_token: string;
    profile_name: string;
    password: string;
    password_confirmation: string;
}

function isProtectedCustomerEndpoint(
    url: string | undefined,
): boolean {
    if (!url) {
        return false;
    }

    const path = url
        .split('?')[0]
        .replace(/^https?:\/\/[^/]+/i, '');

    return (
        path === '/web/auth/me' ||
        path === '/web/auth/profile' ||
        path === '/web/auth/profile-picture' ||
        path === '/web/auth/change-password' ||
        path === '/web/auth/logout' ||
        path.startsWith('/web/customer/')
    );
}

function isAccountUnavailableResponse(
    status: number | undefined,
    message: unknown,
): boolean {
    return (
        status === 403 &&
        (
            message === 'Account is suspended.' ||
            message === 'Account is not available.'
        )
    );
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

api.interceptors.response.use(
    (response) => response,
    (error: unknown) => {
        if (axios.isAxiosError(error)) {
            const status = error.response?.status;
            const message = error.response?.data?.message;
            const protectedEndpoint =
                isProtectedCustomerEndpoint(
                    error.config?.url,
                );

            const sessionIsInvalid =
                status === 401 ||
                isAccountUnavailableResponse(
                    status,
                    message,
                );

            if (
                protectedEndpoint &&
                sessionIsInvalid &&
                typeof window !== 'undefined'
            ) {
                window.dispatchEvent(
                    new Event(CUSTOMER_SESSION_EXPIRED_EVENT),
                );
            }
        }

        return Promise.reject(error);
    },
);

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

    async updateProfile(
        payload: { profile_name: string },
    ): Promise<CustomerAccount> {
        const response = await api.patch<ApiResponse<AccountData>>(
            '/web/auth/profile',
            payload,
        );

        return response.data.data.account;
    },

    async uploadProfilePicture(
        file: File,
    ): Promise<CustomerAccount> {
        const formData = new FormData();

        formData.append('profile_picture', file);

        const response = await api.post<ApiResponse<AccountData>>(
            '/web/auth/profile-picture',
            formData,
        );

        return response.data.data.account;
    },

    async changePassword(
        payload: ChangePasswordPayload,
    ): Promise<void> {
        await api.post<ApiResponse<null>>(
            '/web/auth/change-password',
            payload,
        );
    },

    async logout(): Promise<void> {
        await api.post('/web/auth/logout');
    },

    async requestRegistrationOtp(
        payload: RegistrationOtpRequestPayload,
    ): Promise<RegistrationOtpRequestResult> {
        const response = await api.post<
            ApiResponse<RegistrationOtpRequestResult>
        >(
            '/web/auth/register/otp/request',
            payload,
        );

        return response.data.data;
    },

    async verifyRegistrationOtp(
        payload: RegistrationOtpVerifyPayload,
    ): Promise<RegistrationOtpVerifyResult> {
        const response = await api.post<
            ApiResponse<RegistrationOtpVerifyResult>
        >(
            '/web/auth/register/otp/verify',
            payload,
        );

        return response.data.data;
    },

    async completeRegistration(
        payload: RegistrationCompletePayload,
    ): Promise<CustomerAccount> {
        const response = await api.post<ApiResponse<AccountData>>(
            '/web/auth/register/complete',
            payload,
        );

        return response.data.data.account;
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

    async submitTaskRating(
        taskId: string,
        payload: CustomerTaskRatingPayload,
    ): Promise<CustomerTaskRating> {
        const response = await api.post<
            ApiResponse<CustomerTaskRatingData>
        >(
            `/web/customer/tasks/${encodeURIComponent(taskId)}/rating`,
            payload,
        );

        return response.data.data.rating;
    },

    async updateTaskRating(
        taskId: string,
        payload: CustomerTaskRatingPayload,
    ): Promise<CustomerTaskRating> {
        const response = await api.patch<
            ApiResponse<CustomerTaskRatingData>
        >(
            `/web/customer/tasks/${encodeURIComponent(taskId)}/rating`,
            payload,
        );

        return response.data.data.rating;
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

    async getRequest(
        requestId: string,
    ): Promise<CustomerMarketplaceRequestDetail> {
        const response = await api.get<
            ApiResponse<CustomerMarketplaceRequestDetailData>
        >(
            `/web/customer/requests/${encodeURIComponent(requestId)}`,
        );

        return response.data.data;
    },

    async awardQuotation(
        requestId: string,
        quotationId: string,
    ): Promise<CustomerAwardQuotationResult> {
        const response = await api.post<
            ApiResponse<CustomerAwardQuotationData>
        >(
            `/web/customer/requests/${encodeURIComponent(requestId)}/award`,
            {
                quotation_id: quotationId,
            },
        );

        return response.data.data;
    },

    async cancelRequest(
        requestId: string,
    ): Promise<CustomerCancelRequestData['request']> {
        const response = await api.post<
            ApiResponse<CustomerCancelRequestData>
        >(
            `/web/customer/requests/${encodeURIComponent(requestId)}/cancel`,
        );

        return response.data.data.request;
    },
};
