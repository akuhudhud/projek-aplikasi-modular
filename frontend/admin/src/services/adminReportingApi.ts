import type {
  ApiResponse,
  AuditLogResult
} from '../types/admin';

import type { AdminDashboard } from '../features/dashboard/dashboardTypes';

import { adminApi } from './adminApiClient';

export async function getAuditLogs(
  filters: {
    page?: number;
    per_page?: number;
  } = {}
): Promise<AuditLogResult> {
  const response = await adminApi.get<
    ApiResponse<AuditLogResult>
  >('/admin/audit-logs', {
    params: filters
  });

  return response.data.data;
}

export async function getAdminDashboard(): Promise<AdminDashboard> {
  const response = await adminApi.get<ApiResponse<AdminDashboard>>(
    '/admin/dashboard'
  );

  return response.data.data;
}
