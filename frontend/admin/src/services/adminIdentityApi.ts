
import type {
  ApiResponse,
  IdentityVerificationDetail,
  IdentityVerificationQueueResult
} from '../types/admin';

import { adminApi } from './adminApiClient';

export interface IdentityVerificationFilters {
  search?: string;
  status?: 'PENDING' | 'VERIFIED' | 'REJECTED' | 'ALL';
  page?: number;
  per_page?: number;
}

export async function getIdentityVerifications(
  filters: IdentityVerificationFilters = {}
): Promise<IdentityVerificationQueueResult> {
  const response = await adminApi.get<
    ApiResponse<IdentityVerificationQueueResult>
  >('/admin/identity-verifications', {
    params: filters
  });

  return response.data.data;
}

export async function getIdentityVerificationDetail(
  accountId: string
): Promise<IdentityVerificationDetail> {
  const response = await adminApi.get<
    ApiResponse<{
      identity_verification: IdentityVerificationDetail;
    }>
  >(
    `/admin/identity-verifications/${encodeURIComponent(accountId)}`
  );

  return response.data.data.identity_verification;
}

export async function decideIdentityVerification(
  accountId: string,
  decision: 'VERIFIED' | 'REJECTED',
  rejectionReason?: string
): Promise<void> {
  await adminApi.post(
    `/admin/identity-verifications/${encodeURIComponent(accountId)}/decision`,
    {
      decision,
      rejection_reason: rejectionReason
    }
  );
}

export async function getIdentityVerificationDocument(
  accountId: string,
  documentId: string
): Promise<Blob> {
  const response = await adminApi.get(
    `/admin/identity-verifications/${encodeURIComponent(accountId)}/documents/${encodeURIComponent(documentId)}`,
    {
      responseType: 'blob'
    }
  );

  return response.data as Blob;
}

export async function deleteIdentityVerificationData(
  accountId: string,
  reason: string
): Promise<void> {
  await adminApi.delete<
    ApiResponse<null>
  >(
    `/admin/identity-verifications/${encodeURIComponent(accountId)}/data`,
    {
      data: {
        reason
      }
    }
  );
}
