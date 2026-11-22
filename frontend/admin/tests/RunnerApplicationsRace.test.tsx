
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

    fireEvent.click(screen.getByRole('button', { name: 'Cari' }));

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

  it('mengabaikan butiran lesen lama apabila permintaan lesen baharu selesai dahulu', async () => {
    const firstDetail = deferred<RunnerLicenseVerificationDetail>();
    const secondDetail = deferred<RunnerLicenseVerificationDetail>();

    vi.spyOn(adminApi, 'getRunnerApplications')
      .mockResolvedValue(
        applicationsResult([firstApplication, secondApplication])
      );

    vi.spyOn(adminApi, 'getRunnerLicenseVerification')
      .mockReturnValueOnce(firstDetail.promise)
      .mockReturnValueOnce(secondDetail.promise);

    render(<RunnerApplicationsPage account={account} />);

    expect(
      await screen.findByText('Runner Pertama')
    ).toBeTruthy();

    expect(screen.getByText('Runner Kedua')).toBeTruthy();

    const detailButtons = screen.getAllByRole('button', {
      name: 'Butiran Lesen'
    });

    fireEvent.click(detailButtons[0]);
    fireEvent.click(detailButtons[1]);

    secondDetail.resolve(
      licenseDetail(secondApplication.id, '******2222')
    );

    expect(
      await screen.findByText('******2222')
    ).toBeTruthy();

    firstDetail.resolve(
      licenseDetail(firstApplication.id, '******1111')
    );

    await waitFor(() => {
      expect(
        screen.queryByText('******1111')
      ).toBeNull();
    });

    expect(screen.getByText('******2222')).toBeTruthy();
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

    expect(applicationsTab).toHaveAttribute('tabindex', '0');
    expect(directoryTab).toHaveAttribute('tabindex', '-1');

    fireEvent.keyDown(applicationsTab, {
      key: 'ArrowRight'
    });

    await waitFor(() => {
      expect(directoryTab).toHaveAttribute('aria-selected', 'true');
    });

    expect(directoryTab).toHaveAttribute('tabindex', '0');
    expect(applicationsTab).toHaveAttribute('tabindex', '-1');
  });
});
