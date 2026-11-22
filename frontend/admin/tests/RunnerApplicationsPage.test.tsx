import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor
} from '@testing-library/react';

import RunnerApplicationsPage from '../src/features/runner-applications/RunnerApplicationsPage';
import * as adminApi from '../src/services/adminApi';

import type {
  AdminProfile,
  RunnerApplication,
  RunnerDirectoryItem,
  RunnerLicenseVerificationDetail
} from '../src/types/admin';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const adminAccount: AdminProfile = {
  id: 'admin-001',
  profile_name: 'Pentadbir Ujian',
  verified_name: null,
  phone: '60111111111',
  email: 'admin@example.com',
  profile_picture: null,
  role: 'SUPER_ADMIN',
  status: 'ACTIVE',
  permissions: [
    'runner_applications.view',
    'runner_applications.approve',
    'runner_applications.reject',
    'runner_license_verifications.review',
    'runners.suspend',
    'runners.restore'
  ]
};

const rootAccount: AdminProfile = {
  ...adminAccount,
  id: 'root-001',
  profile_name: 'Root Admin',
  role: 'ROOT_SUPER_ADMIN',
  permissions: []
};

const restrictedAccount: AdminProfile = {
  ...adminAccount,
  permissions: ['runner_applications.view']
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

const runner: RunnerDirectoryItem = {
  id: 'runner-approved-001',
  profile_name: 'Runner Diluluskan',
  phone: '60122222222',
  email: 'approved@example.com',
  role: 'USER',
  status: 'ACTIVE',
  runner_status: 'APPROVED',
  created_at: '2026-11-19T10:00:00.000Z',
  updated_at: '2026-11-20T10:00:00.000Z',
  current_license: {
    license_type: 'CDL',
    license_class: 'D',
    expires_at: '2027-12-31',
    status: 'VERIFIED'
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

function mockRunnerDirectory(
  runners: RunnerDirectoryItem[] = [runner]
) {
  return {
    runners,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: runners.length,
      from: runners.length > 0 ? 1 : null,
      to: runners.length > 0 ? runners.length : null
    }
  };
}

describe('RunnerApplicationsPage', () => {
  it('memaparkan senarai permohonan Runner daripada API', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([application]);

    render(<RunnerApplicationsPage account={adminAccount} />);

    expect(await screen.findByText('Runner Ujian')).toBeTruthy();
    expect(screen.getByText('60123456789')).toBeTruthy();
    expect(screen.getByText('CDL')).toBeTruthy();
    expect(screen.getByText('PENDING')).toBeTruthy();
    expect(screen.getByText('Tarikh Permohonan')).toBeTruthy();
  });

  it('memaparkan keadaan kosong apabila tiada permohonan', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([]);

    render(<RunnerApplicationsPage account={adminAccount} />);

    expect(
      await screen.findByText(
        'Tiada permohonan Runner yang menunggu semakan.'
      )
    ).toBeTruthy();
  });

  it('menghantar keputusan lulus bersama alasan kepada API', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([application]);

    const decide = vi.spyOn(adminApi, 'decideRunnerApplication')
      .mockResolvedValue();

    render(<RunnerApplicationsPage account={adminAccount} />);
    await screen.findByText('Runner Ujian');

    fireEvent.change(
      screen.getByPlaceholderText('Masukkan alasan keputusan...'),
      { target: { value: 'Dokumen telah disemak.' } }
    );

    fireEvent.click(screen.getByRole('button', { name: 'Luluskan' }));

    await waitFor(() => {
      expect(decide).toHaveBeenCalledWith(
        application.id,
        'approve',
        'Dokumen telah disemak.'
      );
    });
  });

  it('menghantar keputusan tolak bersama alasan kepada API', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([application]);

    const decide = vi.spyOn(adminApi, 'decideRunnerApplication')
      .mockResolvedValue();

    render(<RunnerApplicationsPage account={adminAccount} />);
    await screen.findByText('Runner Ujian');

    fireEvent.change(
      screen.getByPlaceholderText('Masukkan alasan keputusan...'),
      { target: { value: 'Dokumen tidak memenuhi syarat.' } }
    );

    fireEvent.click(screen.getByRole('button', { name: 'Tolak' }));

    await waitFor(() => {
      expect(decide).toHaveBeenCalledWith(
        application.id,
        'reject',
        'Dokumen tidak memenuhi syarat.'
      );
    });
  });

  it('mewajibkan alasan sebelum keputusan dihantar', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([application]);

    const decide = vi.spyOn(adminApi, 'decideRunnerApplication')
      .mockResolvedValue();

    render(<RunnerApplicationsPage account={adminAccount} />);
    await screen.findByText('Runner Ujian');

    fireEvent.click(screen.getByRole('button', { name: 'Luluskan' }));

    expect(
      await screen.findByText('Alasan keputusan wajib diisi.')
    ).toBeTruthy();

    expect(decide).not.toHaveBeenCalled();
  });

  it('tidak memaparkan tindakan keputusan tanpa permission', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([application]);

    render(<RunnerApplicationsPage account={restrictedAccount} />);
    await screen.findByText('Runner Ujian');

    expect(
      screen.queryByRole('button', { name: 'Luluskan' })
    ).toBeNull();

    expect(
      screen.queryByRole('button', { name: 'Tolak' })
    ).toBeNull();
  });

  it('memaparkan butiran lesen melalui API apabila diminta', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([application]);

    const getLicense = vi.spyOn(
      adminApi,
      'getRunnerLicenseVerification'
    ).mockResolvedValue(licenseDetail);

    render(<RunnerApplicationsPage account={adminAccount} />);
    await screen.findByText('Runner Ujian');

    fireEvent.click(
      screen.getByRole('button', { name: 'Butiran Lesen' })
    );

    expect(await screen.findByText('******5678')).toBeTruthy();
    expect(getLicense).toHaveBeenCalledWith(application.id);
  });

  it('memaparkan ralat apabila API permohonan gagal', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockRejectedValue(new Error('Runner API failed'));

    render(<RunnerApplicationsPage account={adminAccount} />);

    expect(await screen.findByRole('alert')).toBeTruthy();
    expect(screen.getByText('Runner API failed')).toBeTruthy();
  });

  it('membuka direktori Runner dan memaparkan rekod daripada API', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([]);

    const getDirectory = vi.spyOn(adminApi, 'getRunnerDirectory')
      .mockResolvedValue(mockRunnerDirectory());

    render(<RunnerApplicationsPage account={adminAccount} />);

    fireEvent.click(
      screen.getByRole('tab', { name: 'Senarai Runner' })
    );

    expect(await screen.findByText('Runner Diluluskan')).toBeTruthy();

    expect(getDirectory).toHaveBeenCalledWith({
      search: undefined,
      status: undefined,
      page: 1,
      per_page: 15
    });
  });

  it('menghantar carian direktori Runner kepada API', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([]);

    const getDirectory = vi.spyOn(adminApi, 'getRunnerDirectory')
      .mockResolvedValue(mockRunnerDirectory());

    render(<RunnerApplicationsPage account={adminAccount} />);

    fireEvent.click(
      screen.getByRole('tab', { name: 'Senarai Runner' })
    );

    await screen.findByText('Runner Diluluskan');

    fireEvent.change(
      screen.getByPlaceholderText(
        'Cari nama, telefon atau e-mel Runner'
      ),
      { target: { value: 'Nazim' } }
    );

    fireEvent.click(screen.getByRole('button', { name: 'Cari' }));

    await waitFor(() => {
      expect(getDirectory).toHaveBeenLastCalledWith({
        search: 'Nazim',
        status: undefined,
        page: 1,
        per_page: 15
      });
    });
  });

  it('menggantung Runner dengan alasan tindakan', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([]);

    vi.spyOn(adminApi, 'getRunnerDirectory')
      .mockResolvedValue(mockRunnerDirectory());

    const changeStatus = vi.spyOn(adminApi, 'changeRunnerStatus')
      .mockResolvedValue();

    render(<RunnerApplicationsPage account={adminAccount} />);

    fireEvent.click(
      screen.getByRole('tab', { name: 'Senarai Runner' })
    );

    await screen.findByText('Runner Diluluskan');

    fireEvent.change(
      screen.getByPlaceholderText('Masukkan alasan tindakan...'),
      { target: { value: 'Pelanggaran peraturan.' } }
    );

    fireEvent.click(
      screen.getByRole('button', { name: 'Gantung Runner' })
    );

    await waitFor(() => {
      expect(changeStatus).toHaveBeenCalledWith(
        runner.id,
        'suspend',
        'Pelanggaran peraturan.'
      );
    });
  });

  it('Root boleh melihat direktori Runner tanpa permission individu', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue([]);

    vi.spyOn(adminApi, 'getRunnerDirectory')
      .mockResolvedValue(mockRunnerDirectory());

    render(<RunnerApplicationsPage account={rootAccount} />);

    fireEvent.click(
      screen.getByRole('tab', { name: 'Senarai Runner' })
    );

    expect(await screen.findByText('Runner Diluluskan')).toBeTruthy();

    expect(
      screen.getByRole('button', { name: 'Gantung Runner' })
    ).toBeTruthy();
  });
});
