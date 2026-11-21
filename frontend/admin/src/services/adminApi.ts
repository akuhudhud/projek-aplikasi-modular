
import axios from 'axios';

import type {
  AdminAccount,
  AdminProfile,
  ApiResponse,
  AuditLog,
  RunnerApplication
} from '../types/admin';

const TOKEN_KEY = 'super_runner_admin_session';

export const adminApi = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json'
  }
});

adminApi.interceptors.request.use((config) => {
  const token = sessionStorage.getItem(TOKEN_KEY);

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

export function saveSession(token: string): void {
  sessionStorage.setItem(TOKEN_KEY, token);
}

export function clearSession(): void {
  sessionStorage.removeItem(TOKEN_KEY);
}

export function hasSession(): boolean {
  return Boolean(sessionStorage.getItem(TOKEN_KEY));
}

export function getApiError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    return (
      error.response?.data?.message ||
      'Permintaan tidak berjaya. Sila cuba lagi.'
    );
  }

  return 'Ralat tidak dijangka berlaku.';
}

export async function login(
  identifier: string,
  password: string
): Promise<AdminProfile> {
  const isEmail = identifier.includes('@');

  const response = await adminApi.post<
    ApiResponse<{
      account: AdminProfile;
      session: { token: string };
    }>
  >('/login', {
    [isEmail ? 'email' : 'phone']: identifier,
    password
  });

  const { account, session } = response.data.data;

  if (!session?.token) {
    throw new Error('Token sesi tidak diterima daripada server.');
  }

  saveSession(session.token);

  return account;
}

export async function getCurrentAdmin(): Promise<AdminProfile> {
  const response = await adminApi.get<
    ApiResponse<{ account: AdminProfile }>
  >('/me');

  return response.data.data.account;
}

export async function logout(): Promise<void> {
  try {
    await adminApi.post('/logout');
  } finally {
    clearSession();
  }
}

export async function getAccounts(): Promise<AdminAccount[]> {
  const response = await adminApi.get<
    ApiResponse<{ accounts: AdminAccount[] }>
  >('/admin/accounts');

  return response.data.data.accounts;
}

export async function getRunnerApplications(): Promise<
  RunnerApplication[]
> {
  const response = await adminApi.get<
    ApiResponse<{ applications: RunnerApplication[] }>
  >('/admin/runner-applications');

  return response.data.data.applications;
}

export async function decideRunnerApplication(
  accountId: string,
  decision: 'approve' | 'reject',
  reason: string
): Promise<void> {
  await adminApi.post(
    `/admin/runner-applications/${accountId}/${decision}`,
    { reason }
  );
}

export async function getAuditLogs(): Promise<AuditLog[]> {
  const response = await adminApi.get<
    ApiResponse<{ audit_logs: AuditLog[] }>
  >('/admin/audit-logs');

  return response.data.data.audit_logs;
}
