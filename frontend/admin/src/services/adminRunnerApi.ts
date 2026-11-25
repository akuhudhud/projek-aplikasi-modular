import type {
  ApiResponse,
  RunnerApplicationResult,
  RunnerDirectoryResult,
  RunnerLicenseVerificationDetail
} from '../types/admin';

import { adminApi } from './adminApiClient';

export async function getRunnerApplications(
  filters: {
    page?: number;
    per_page?: number;
  } = {}
): Promise<RunnerApplicationResult> {
  const response = await adminApi.get<
    ApiResponse<RunnerApplicationResult>
  >('/admin/runner-applications', {
    params: filters
  });

  return response.data.data;
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

export async function getRunnerDirectory(
  filters: {
    search?: string;
    status?: string;
    page?: number;
    per_page?: number;
  } = {}
): Promise<RunnerDirectoryResult> {
  const response = await adminApi.get<
    ApiResponse<RunnerDirectoryResult>
  >('/admin/runners', {
    params: filters
  });

  return response.data.data;
}

export async function changeRunnerStatus(
  accountId: string,
  action: 'suspend' | 'restore',
  reason: string
): Promise<void> {
  await adminApi.post(
    `/admin/runners/${encodeURIComponent(accountId)}/${action}`,
    { reason }
  );
}

export async function getRunnerLicenseVerification(
  accountId: string
): Promise<RunnerLicenseVerificationDetail> {
  const response = await adminApi.get<
    ApiResponse<{
      runner_license_verification: RunnerLicenseVerificationDetail;
    }>
  >(
    `/admin/runner-license-verifications/${encodeURIComponent(accountId)}`
  );

  return response.data.data.runner_license_verification;
}

export async function getRunnerLicenseDocument(
  accountId: string
): Promise<Blob> {
  const response = await adminApi.get(
    `/admin/runner-license-verifications/${encodeURIComponent(accountId)}/document`,
    {
      responseType: 'blob'
    }
  );

  return response.data as Blob;
}
