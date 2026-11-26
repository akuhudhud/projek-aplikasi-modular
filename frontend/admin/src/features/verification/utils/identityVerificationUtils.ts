
import type { AdminProfile } from '../../../types/admin';

export type VerificationStatus = 'PENDING' | 'VERIFIED' | 'REJECTED' | 'ALL';

export interface DocumentPreview {
  id: string;
  url: string;
  mimeType: string;
}

export const STATUS_TABS: Array<{
  value: VerificationStatus;
  label: string;
}> = [
  { value: 'PENDING', label: 'Menunggu' },
  { value: 'VERIFIED', label: 'Disahkan' },
  { value: 'REJECTED', label: 'Ditolak' },
  { value: 'ALL', label: 'Semua' }
];

export function hasReviewPermission(account: AdminProfile): boolean {
  return (
    account.role === 'ROOT_SUPER_ADMIN' ||
    account.permissions?.includes('identity_verifications.review') === true
  );
}

export function hasDeleteDataPermission(account: AdminProfile): boolean {
  return (
    account.role === 'ROOT_SUPER_ADMIN' ||
    account.permissions?.includes('identity_verifications.delete_data') === true
  );
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('ms-MY', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
}

export function formatBytes(value: number): string {
  if (value < 1024) return `${value} B`;
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

export function statusLabel(status: string): string {
  switch (status) {
    case 'PENDING':
      return 'MENUNGGU';
    case 'VERIFIED':
      return 'DISAHKAN';
    case 'REJECTED':
      return 'DITOLAK';
    default:
      return status;
  }
}
