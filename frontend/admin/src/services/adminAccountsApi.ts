import type {
  AccountDirectoryResult,
  AdminAccountDetail,
  ApiResponse,
  CreateSuperAdminPayload,
  SuperAdminPermissionResult
} from '../types/admin';

import { adminApi } from './adminApiClient';

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
  >(`/admin/accounts/${encodeURIComponent(accountId)}/permissions`);

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
