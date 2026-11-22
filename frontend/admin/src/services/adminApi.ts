
import axios from 'axios';

import type {
  AccountDirectoryResult,
  AdminAccountDetail,
  AdminPermission,
  AdminProfile,
  ApiResponse,
  AuditLog,
  CreateSuperAdminPayload,
  RunnerApplication,
  SuperAdminPermissionResult
} from '../types/admin';

import type { AdminDashboard } from '../features/dashboard/dashboardTypes';

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

export function isUnauthorizedError(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 401;
}

export function getApiError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    return (
      error.response?.data?.message ||
      'Permintaan tidak berjaya. Sila cuba lagi.'
    );
  }

  if (error instanceof Error && error.message) {
    return error.message;
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

  const token = response.data.data.session?.token;

  if (!token) {
    throw new Error('Token sesi tidak diterima daripada server.');
  }

  saveSession(token);

  try {
    return await getCurrentAdmin();
  } catch (error) {
    clearSession();
    throw error;
  }
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

export interface AccountDirectoryFilters {
  search?: string;
  status?: string;
  role?: string;
  page?: number;
  per_page?: number;
}

export async function getAccounts(
  filters: AccountDirectoryFilters = {}
): Promise<AccountDirectoryResult> {
  const response = await adminApi.get<
    ApiResponse<AccountDirectoryResult>
  >('/admin/account-directory', {
    params: filters
  });

  return response.data.data;
}

export async function getAccountDetail(
  accountId: string
): Promise<AdminAccountDetail> {
  const response = await adminApi.get<
    ApiResponse<{ account: AdminAccountDetail }>
  >(`/admin/account-directory/${encodeURIComponent(accountId)}`);

  return response.data.data.account;
}

export async function suspendAccount(
  accountId: string,
  reason: string
): Promise<void> {
  await adminApi.post(
    `/admin/accounts/${encodeURIComponent(accountId)}/suspend`,
    { reason }
  );
}

export async function unsuspendAccount(
  accountId: string,
  reason: string
): Promise<void> {
  await adminApi.post(
    `/admin/accounts/${encodeURIComponent(accountId)}/unsuspend`,
    { reason }
  );
}

export async function reactivateAccount(
  accountId: string,
  reason: string
): Promise<void> {
  await adminApi.post(
    `/admin/accounts/${encodeURIComponent(accountId)}/reactivate`,
    { reason }
  );
}

export async function createSuperAdmin(
  payload: CreateSuperAdminPayload
): Promise<void> {
  await adminApi.post('/admin/accounts', payload);
}

export async function updateSuperAdminJobTitle(
  accountId: string,
  jobTitle: string,
  reason: string
): Promise<void> {
  await adminApi.patch(
    `/admin/accounts/${encodeURIComponent(accountId)}/job-title`,
    {
      job_title: jobTitle,
      reason
    }
  );
}

export async function getSuperAdminPermissions(
  accountId: string
): Promise<SuperAdminPermissionResult> {
  const response = await adminApi.get<
    ApiResponse<SuperAdminPermissionResult>
  >(
    `/admin/accounts/${encodeURIComponent(accountId)}/permissions`
  );

  return response.data.data;
}

export async function updateSuperAdminPermissions(
  accountId: string,
  permissions: string[],
  reason: string
): Promise<void> {
  await adminApi.put(
    `/admin/accounts/${encodeURIComponent(accountId)}/permissions`,
    {
      permissions,
      reason
    }
  );
}

export async function getAdminPermissionCatalog(): Promise<AdminPermission[]> {
  const response = await adminApi.get<
    ApiResponse<{ catalog: AdminPermission[] }>
  >('/admin/permission-catalog');

  return response.data.data.catalog;
}

export async function getRunnerApplications(): Promise<RunnerApplication[]> {
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
    `/admin/runner-applications/${encodeURIComponent(accountId)}/${decision}`,
    { reason }
  );
}

export async function getAuditLogs(): Promise<AuditLog[]> {
  const response = await adminApi.get<
    ApiResponse<{ audit_logs: AuditLog[] }>
  >('/admin/audit-logs');

  return response.data.data.audit_logs;
}

export async function getAdminDashboard(): Promise<AdminDashboard> {
  const response = await adminApi.get<ApiResponse<AdminDashboard>>(
    '/admin/dashboard'
  );

  return response.data.data;
}
