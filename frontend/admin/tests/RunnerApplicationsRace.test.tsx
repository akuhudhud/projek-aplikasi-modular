
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

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;

  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });

  return { promise, resolve, reject };
}

const account: AdminProfile = {
  id: 'admin-race-001',
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

const licenseApplication = {
  id: 'license-race-001',
  license_type: 'CDL',
  license_class: 'D',
  expires_at: '2027-12-31',
  status: 'PENDING',
  submitted_at: '2026-11-20T10:00:00.000Z',
  mime_type: 'image/jpeg',
  size_bytes: 204800
};

const firstApplication: RunnerApplication = {
  id: 'runner-race-001',
  profile_name: 'Runner Pertama',
  phone: '60123456781',
  email: 'first@example.com',
  role: 'USER',
  status: 'ACTIVE',
  runner_status: 'PENDING',
  created_at: '2026-11-20T10:00:00.000Z',
  updated_at: '2026-11-20T10:00:00.000Z',
  runner_license_application: {
    ...licenseApplication,
    id: 'license-race-001'
  }
};

const secondApplication: RunnerApplication = {
  ...firstApplication,
  id: 'runner-race-002',
  profile_name: 'Runner Kedua',
  phone: '60123456782',
  email: 'second@example.com',
  runner_license_application: {
    ...licenseApplication,
    id: 'license-race-002'
  }
};

const firstRunner: RunnerDirectoryItem = {
  id: 'directory-race-001',
  profile_name: 'Runner Carian Lama',
  phone: '60122222221',
  email: 'old@example.com',
  role: 'USER',
  status: 'ACTIVE',
  runner_status: 'APPROVED',
  created_at: '2026-11-19T10:00:00.000Z',
  updated_at: '2026-11-20T10:00:00.000Z',
  current_license: null
};

const secondRunner: RunnerDirectoryItem = {
  ...firstRunner,
  id: 'directory-race-002',
  profile_name: 'Runner Carian Baharu',
  phone: '60122222222',
  email: 'new@example.com'
};

function applicationsResult(
  applications: RunnerApplication[] = [firstApplication]
) {
  return {
    applications,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: applications.length,
      from: applications.length ? 1 : null,
      to: applications.length || null
    }
  };
}

function directoryResult(runners: RunnerDirectoryItem[]) {
  return {
    runners,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: runners.length,
      from: runners.length ? 1 : null,
      to: runners.length || null
    }
  };
}

function licenseDetail(
  accountId: string,
  maskedNumber: string
): RunnerLicenseVerificationDetail {
  return {
    id: `license-detail-${accountId}`,
    account_id: accountId,
    profile_name: accountId,
    license_number_masked: maskedNumber,
    license_type: 'CDL',
    license_class: 'D',
    expires_at: '2027-12-31',
    status: 'PENDING',
    is_current: false,
    mime_type: 'image/jpeg',
    size_bytes: 204800,
    submitted_at: '2026-11-20T10:00:00.000Z',
    reviewed_at: null
  };
}

describe('RunnerApplicationsPage race conditions', () => {
  it('mengabaikan respons permohonan lama selepas bertukar ke direktori', async () => {
    const pendingApplications = deferred<
      ReturnType<typeof applicationsResult>
    >();

    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockReturnValue(pendingApplications.promise);

    vi.spyOn(adminApi, 'getRunnerDirectory')
      .mockResolvedValue(directoryResult([firstRunner]));

    render(<RunnerApplicationsPage account={account} />);

    await waitFor(() => {
      expect(adminApi.getRunnerApplications).toHaveBeenCalledTimes(1);
    });

    fireEvent.click(
      screen.getByRole('tab', { name: 'Senarai Runner' })
    );

    expect(
      await screen.findByText('Runner Carian Lama')
    ).toBeTruthy();

    pendingApplications.resolve(
      applicationsResult([firstApplication])
    );

    await waitFor(() => {
      expect(
        screen.queryByText('Runner Pertama')
      ).toBeNull();
    });

    expect(screen.getByText('Runner Carian Lama')).toBeTruthy();
  });

  it('mengabaikan hasil carian direktori yang lebih lama', async () => {
    const oldSearch = deferred<ReturnType<typeof directoryResult>>();
    const newSearch = deferred<ReturnType<typeof directoryResult>>();

    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue(applicationsResult([]));

    const getDirectory = vi.spyOn(adminApi, 'getRunnerDirectory')
      .mockReturnValueOnce(
        Promise.resolve(directoryResult([firstRunner]))
      )
      .mockReturnValueOnce(oldSearch.promise)
      .mockReturnValueOnce(newSearch.promise);

    render(<RunnerApplicationsPage account={account} />);

    fireEvent.click(
      screen.getByRole('tab', { name: 'Senarai Runner' })
    );

    expect(
      await screen.findByText('Runner Carian Lama')
    ).toBeTruthy();

    const searchInput = screen.getByRole('searchbox', {
      name: 'Cari Runner'
    });

    fireEvent.change(searchInput, {
      target: { value: 'lama' }
    });

    fireEvent.click(screen.getByRole('button', { name: 'Cari' }));

    await waitFor(() => {
      expect(getDirectory).toHaveBeenCalledTimes(2);
    });

    fireEvent.change(searchInput, {
      target: { value: 'baharu' }
    });

    fireEvent.keyDown(searchInput, {
      key: 'Enter',
      code: 'Enter'
    });

    await waitFor(() => {
      expect(getDirectory).toHaveBeenCalledTimes(3);
    });

    newSearch.resolve(directoryResult([secondRunner]));

    expect(
      await screen.findByText('Runner Carian Baharu')
    ).toBeTruthy();

    oldSearch.resolve(directoryResult([firstRunner]));

    await waitFor(() => {
      expect(
        screen.getByText('Runner Carian Baharu')
      ).toBeTruthy();
    });

    expect(
      screen.queryByText('Runner Carian Lama')
    ).toBeNull();
  });

  it('mengunci butiran lesen lain semasa satu permintaan sedang berjalan', async () => {
    const pendingDetail = deferred<RunnerLicenseVerificationDetail>();

    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue(
        applicationsResult([firstApplication, secondApplication])
      );

    const getLicenseDetail = vi.spyOn(
      adminApi,
      'getRunnerLicenseVerification'
    ).mockReturnValue(pendingDetail.promise);

    render(<RunnerApplicationsPage account={account} />);

    expect(
      await screen.findByText('Runner Pertama')
    ).toBeTruthy();

    expect(screen.getByText('Runner Kedua')).toBeTruthy();

    const detailButtons = screen.getAllByRole('button', {
      name: 'Butiran Lesen'
    });

    expect(detailButtons).toHaveLength(2);

    fireEvent.click(detailButtons[0]);

    await waitFor(() => {
      expect(getLicenseDetail).toHaveBeenCalledTimes(1);
      expect(getLicenseDetail).toHaveBeenCalledWith(firstApplication.id);
    });

    expect(detailButtons[0].hasAttribute('disabled')).toBe(true);
    expect(detailButtons[1].hasAttribute('disabled')).toBe(true);

    fireEvent.click(detailButtons[1]);

    expect(getLicenseDetail).toHaveBeenCalledTimes(1);

    pendingDetail.resolve(
      licenseDetail(firstApplication.id, '******1111')
    );

    expect(
      await screen.findByText('******1111')
    ).toBeTruthy();

    await waitFor(() => {
      expect(
        screen.getAllByRole('button', { name: 'Butiran Lesen' })
          .every((button) => !button.hasAttribute('disabled'))
      ).toBe(true);
    });
  });

  it('mengunci tindakan keputusan pada semua permohonan semasa satu tindakan berjalan', async () => {
    const pendingDecision = deferred<void>();

    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue(
        applicationsResult([firstApplication, secondApplication])
      );

    const decideApplication = vi.spyOn(
      adminApi,
      'decideRunnerApplication'
    ).mockReturnValue(pendingDecision.promise);

    render(<RunnerApplicationsPage account={account} />);

    expect(
      await screen.findByText('Runner Pertama')
    ).toBeTruthy();

    expect(screen.getByText('Runner Kedua')).toBeTruthy();

    const reasonFields = screen.getAllByLabelText('Alasan keputusan');

    fireEvent.change(reasonFields[0], {
      target: { value: 'Dokumen telah disemak.' }
    });

    const approveButtons = screen.getAllByRole('button', {
      name: 'Luluskan'
    });

    const rejectButtons = screen.getAllByRole('button', {
      name: 'Tolak'
    });

    expect(approveButtons).toHaveLength(2);
    expect(rejectButtons).toHaveLength(2);

    fireEvent.click(approveButtons[0]);

    await waitFor(() => {
      expect(decideApplication).toHaveBeenCalledTimes(1);
      expect(decideApplication).toHaveBeenCalledWith(
        firstApplication.id,
        'approve',
        'Dokumen telah disemak.'
      );
    });

    expect(
      screen.getAllByRole('button', { name: 'Memproses...' })
    ).toHaveLength(4);

    expect(
      screen.getAllByRole('button', { name: 'Memproses...' })
        .every((button) => button.hasAttribute('disabled'))
    ).toBe(true);

    fireEvent.click(approveButtons[1]);
    fireEvent.click(rejectButtons[1]);

    expect(decideApplication).toHaveBeenCalledTimes(1);

    pendingDecision.resolve();

    await waitFor(() => {
      expect(adminApi.getRunnerApplications).toHaveBeenCalledTimes(2);
    });

    await waitFor(() => {
      expect(
        screen.getAllByRole('button', { name: 'Luluskan' })
          .every((button) => !button.hasAttribute('disabled'))
      ).toBe(true);
    });
  });

  it('menyokong navigasi papan kekunci pada tab Runner', async () => {
    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue(applicationsResult([]));

    vi.spyOn(adminApi, 'getRunnerDirectory')
      .mockResolvedValue(directoryResult([]));

    render(<RunnerApplicationsPage account={account} />);

    const applicationsTab = screen.getByRole('tab', {
      name: 'Permohonan'
    });

    const directoryTab = screen.getByRole('tab', {
      name: 'Senarai Runner'
    });

    expect(applicationsTab.getAttribute('tabindex')).toBe('0');
    expect(directoryTab.getAttribute('tabindex')).toBe('-1');

    fireEvent.keyDown(applicationsTab, {
      key: 'ArrowRight'
    });

    await waitFor(() => {
      expect(directoryTab.getAttribute('aria-selected')).toBe('true');
    });

    expect(directoryTab.getAttribute('tabindex')).toBe('0');
    expect(applicationsTab.getAttribute('tabindex')).toBe('-1');
  });
});
