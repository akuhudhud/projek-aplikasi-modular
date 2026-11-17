
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

            if (
                status === 401 &&
                typeof window !== 'undefined'
            ) {
                window.dispatchEvent(
                    new Event(RUNNER_SESSION_EXPIRED_EVENT),
                );
            }
        }

        return Promise.reject(error);
    },
);

export const runnerApi = {
    async login(payload: LoginPayload): Promise<RunnerAccount> {
        const response = await api.post<
            ApiResponse<AccountData>
        >('/web/auth/login', payload);

        return response.data.data.account;
    },

    async me(): Promise<RunnerAccount> {
        const response = await api.get<
            ApiResponse<AccountData>
        >('/web/auth/me');

        return response.data.data.account;
    },

    async logout(): Promise<void> {
        await api.post('/web/auth/logout');
    },
};
