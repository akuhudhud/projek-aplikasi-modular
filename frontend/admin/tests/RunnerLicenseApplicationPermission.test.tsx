import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  render,
  screen,
  fireEvent
} from '@testing-library/react';

import RunnerApplicationsPage from '../src/features/runner-applications/RunnerApplicationsPage';
import * as adminApi from '../src/services/adminApi';

import type {
  AdminProfile,
  RunnerApplication,
  RunnerLicenseVerificationDetail
} from '../src/types/admin';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const viewOnlyAdmin: AdminProfile = {
  id: 'admin-view-only',
  profile_name: 'Admin Permohonan',
  verified_name: null,
  phone: '60111111111',
  email: 'admin@example.com',
  profile_picture: null,
  role: 'SUPER_ADMIN',
  status: 'ACTIVE',
  permissions: ['runner_applications.view']
};

const licenseOnlyAdmin: AdminProfile = {
  ...viewOnlyAdmin,
  id: 'admin-license-only',
  permissions: ['runner_license_verifications.review']
};

const application: RunnerApplication = {
  id: 'runner-pending-001',
  profile_name: 'Runner Ujian',
  phone: '60123456789',
  email: 'runner@example.com',
  role: 'USER',
  status: 'ACTIVE',
  runner_status: 'PENDING',
  created_at: '2026-11-20T10:00:00.000Z',
  updated_at: '2026-11-20T10:00:00.000Z',
  runner_license_application: {
    id: 'license-001',
    license_type: 'CDL',
    license_class: 'D',
    expires_at: '2027-12-31',
    status: 'PENDING',
    submitted_at: '2026-11-20T10:00:00.000Z',
    mime_type: 'image/jpeg',
    size_bytes: 204800
  }
};

const licenseDetail: RunnerLicenseVerificationDetail = {
  id: 'license-001',
  account_id: application.id,
  profile_name: application.profile_name,
  license_number_masked: '******5678',
  license_type: 'CDL',
  license_class: 'D',
  expires_at: '2027-12-31',
  status: 'PENDING',
  is_current: true,
  mime_type: 'image/jpeg',
  size_bytes: 204800,
  submitted_at: '2026-11-20T10:00:00.000Z',
  reviewed_at: null
};

function mockApplications() {
  return {
    applications: [application],
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 1,
      from: 1,
      to: 1
    }
  };
}

describe('Runner license application permissions', () => {
  it('admin dengan runner_applications.view boleh melihat butiran dan dokumen lesen', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue(mockApplications());

    const getLicense = vi.spyOn(
      adminApi,
      'getRunnerLicenseVerification'
    ).mockResolvedValue(licenseDetail);

    render(
      <RunnerApplicationsPage account={viewOnlyAdmin} />
    );

    expect(
      await screen.findByText('Runner Ujian')
    ).toBeTruthy();

    expect(
      screen.getByRole('button', { name: 'Butiran Lesen' })
    ).toBeTruthy();

    expect(
      screen.getByRole('button', { name: 'Paparkan Dokumen' })
    ).toBeTruthy();

    fireEvent.click(
      screen.getByRole('button', { name: 'Butiran Lesen' })
    );

    expect(
      await screen.findByText('******5678')
    ).toBeTruthy();

    expect(getLicense).toHaveBeenCalledWith(application.id);
  });

  it('permission lesen lama sahaja tidak memberikan akses kepada permohonan Runner', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue(mockApplications());

    render(
      <RunnerApplicationsPage account={licenseOnlyAdmin} />
    );

    expect(
      await screen.findByText(
        'Anda tidak mempunyai kebenaran melihat permohonan Runner.'
      )
    ).toBeTruthy();

    expect(
      screen.queryByRole('button', { name: 'Butiran Lesen' })
    ).toBeNull();

    expect(
      screen.queryByRole('button', { name: 'Paparkan Dokumen' })
    ).toBeNull();

    expect(
      screen.queryByText('Runner Ujian')
    ).toBeNull();
  });
});
