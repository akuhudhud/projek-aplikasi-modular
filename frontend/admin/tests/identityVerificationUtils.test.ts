import { describe, expect, it } from 'vitest';

import type { AdminProfile } from '../src/types/admin';
import {
  hasDeleteDataPermission
} from '../src/features/verification/utils/identityVerificationUtils';

describe('hasDeleteDataPermission', () => {
  it('membenarkan ROOT_SUPER_ADMIN', () => {
    const account = {
      role: 'ROOT_SUPER_ADMIN',
      permissions: []
    } as unknown as AdminProfile;

    expect(hasDeleteDataPermission(account)).toBe(true);
  });

  it('membenarkan admin dengan permission delete_data', () => {
    const account = {
      role: 'SUPER_ADMIN',
      permissions: ['identity_verifications.delete_data']
    } as unknown as AdminProfile;

    expect(hasDeleteDataPermission(account)).toBe(true);
  });

  it('menolak admin tanpa permission delete_data', () => {
    const account = {
      role: 'SUPER_ADMIN',
      permissions: ['identity_verifications.review']
    } as unknown as AdminProfile;

    expect(hasDeleteDataPermission(account)).toBe(false);
  });

  it('menolak akaun dengan permission kosong', () => {
    const account = {
      role: 'SUPER_ADMIN',
      permissions: []
    } as unknown as AdminProfile;

    expect(hasDeleteDataPermission(account)).toBe(false);
  });
});
