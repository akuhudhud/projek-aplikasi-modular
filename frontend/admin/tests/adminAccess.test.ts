
import { describe, expect, it } from 'vitest';

import {
  canAccessPage,
  getAccessiblePages,
  getDefaultAdminPage
} from '../src/app/adminAccess';

import type { AdminProfile } from '../src/types/admin';

function createAdmin(
  role: string,
  permissions: string[] = []
): AdminProfile {
  return {
    id: 'admin-test',
    profile_name: 'Admin Test',
    verified_name: null,
    phone: null,
    email: 'admin@example.com',
    profile_picture: null,
    role,
    status: 'ACTIVE',
    permissions
  };
}

describe('Admin permission access', () => {
  it('memberikan akses semua halaman kepada Root Super Admin', () => {
    const root = createAdmin('ROOT_SUPER_ADMIN');

    expect(
      getAccessiblePages(root)
    ).toEqual([
      'dashboard',
      'accounts',
      'runner-applications',
      'identity-verifications',
      'license-verifications',
      'audit-logs'
    ]);
  });

  it('hanya memberikan halaman berdasarkan permission Super Admin', () => {
    const admin = createAdmin('SUPER_ADMIN', [
      'accounts.view',
      'audit_logs.view'
    ]);

    expect(canAccessPage(admin, 'dashboard')).toBe(true);
    expect(canAccessPage(admin, 'accounts')).toBe(true);
    expect(canAccessPage(admin, 'audit-logs')).toBe(true);
    expect(canAccessPage(admin, 'runner-applications')).toBe(false);
    expect(canAccessPage(admin, 'identity-verifications')).toBe(false);
  });

  it('menggunakan dashboard sebagai halaman lalai', () => {
    const admin = createAdmin('SUPER_ADMIN', [
      'audit_logs.view'
    ]);

    expect(getDefaultAdminPage(admin)).toBe('dashboard');
  });

  it('menolak akses halaman untuk akaun biasa', () => {
    const user = createAdmin('USER', [
      'accounts.view',
      'audit_logs.view'
    ]);

    expect(getAccessiblePages(user)).toEqual([]);
    expect(canAccessPage(user, 'accounts')).toBe(false);
  });

  it('tidak memberikan permission berdasarkan jawatan sahaja', () => {
    const admin = createAdmin('SUPER_ADMIN', []);

    expect(canAccessPage(admin, 'accounts')).toBe(false);
    expect(canAccessPage(admin, 'audit-logs')).toBe(false);
  });
});
