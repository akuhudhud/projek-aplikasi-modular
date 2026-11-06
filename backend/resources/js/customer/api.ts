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
};
