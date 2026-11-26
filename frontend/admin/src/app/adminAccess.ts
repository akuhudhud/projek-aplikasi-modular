
import type { AdminPage, AdminProfile } from '../types/admin';

const PAGE_PERMISSIONS: Partial<Record<AdminPage, string>> = {
  accounts: 'accounts.view',
  'runner-applications': 'runner_applications.view',
  'identity-verifications': 'identity_verifications.review',
  'audit-logs': 'audit_logs.view'
};

const DRIVING_LICENSE_PERMISSIONS = [
  'driving_license_types.manage',
  'driving_license_catalog.manage'
];

export function isAdminAccount(account: AdminProfile): boolean {
  return (
    account.role === 'ROOT_SUPER_ADMIN' ||
    account.role === 'SUPER_ADMIN'
  );
}

export function canAccessPage(
  account: AdminProfile,
  page: AdminPage
): boolean {
  if (!isAdminAccount(account)) {
    return false;
  }

  if (account.role === 'ROOT_SUPER_ADMIN') {
    return true;
  }

  if (page === 'dashboard') {
    return true;
  }

  if (page === 'driving-license-catalog') {
    return DRIVING_LICENSE_PERMISSIONS.some((permission) =>
      account.permissions.includes(permission)
    );
  }

  const requiredPermission = PAGE_PERMISSIONS[page];

  return Boolean(
    requiredPermission &&
    account.permissions.includes(requiredPermission)
  );
}

export function getAccessiblePages(
  account: AdminProfile
): AdminPage[] {
  const pages: AdminPage[] = [
    'dashboard',
    'accounts',
    'runner-applications',
    'identity-verifications',
    'audit-logs',
    'driving-license-catalog'
  ];

  return pages.filter((page) => canAccessPage(account, page));
}

export function getDefaultAdminPage(
  account: AdminProfile
): AdminPage {
  return getAccessiblePages(account)[0] ?? 'dashboard';
}
