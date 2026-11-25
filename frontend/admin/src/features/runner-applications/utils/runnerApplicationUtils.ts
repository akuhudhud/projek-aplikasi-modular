import type { AdminProfile } from '../../../types/admin';

export function hasRunnerPermission(
  account: AdminProfile,
  permission: string
): boolean {
  return (
    account.role === 'ROOT_SUPER_ADMIN' ||
    account.permissions?.includes(permission) === true
  );
}

export function formatRunnerDate(
  value: string | null | undefined
): string {
  if (!value) return '—';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('ms-MY', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  }).format(date);
}

export function formatRunnerBytes(
  value: number | null | undefined
): string {
  if (!value) return '—';
  if (value < 1024) return `${value} B`;

  return `${(value / 1024).toFixed(1)} KB`;
}
