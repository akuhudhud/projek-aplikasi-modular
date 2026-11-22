
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor
} from '@testing-library/react';

import IdentityVerificationsPage from '../src/features/verification/IdentityVerificationsPage';
import * as adminApi from '../src/services/adminApi';

import type {
  AdminProfile,
  IdentityVerificationDetail,
  IdentityVerificationQueueResult
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
  permissions: ['identity_verifications.review']
};

const queueResult: IdentityVerificationQueueResult = {
  verifications: [
    {
      account_id: 'account-001',
      profile_name: 'Pengguna Ujian',
      phone: '60123456789',
      email: 'user@example.com',
      verified_name: 'Nama Sebenar Pengguna',
      mykad_number_masked: '********5678',
      status: 'PENDING',
      submitted_at: '2026-11-21T10:00:00.000Z',
      reviewed_at: null,
      documents_count: 1
    }
  ],
  pagination: {
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 1,
    from: 1,
    to: 1
  }
};

const detailResult: IdentityVerificationDetail = {
  account_id: 'account-001',
  profile_name: 'Pengguna Ujian',
  verified_name: 'Nama Sebenar Pengguna',
  status: 'PENDING',
  mykad_number_masked: '********5678',
  submitted_at: '2026-11-21T10:00:00.000Z',
  reviewed_at: null,
  rejection_reason: null,
  documents: [
    {
      id: 'document-001',
      mime_type: 'application/pdf',
      size_bytes: 204800,
      created_at: '2026-11-21T10:00:00.000Z'
    }
  ]
};

function createDeferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;

  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });

  return { promise, resolve, reject };
}

describe('IdentityVerificationsPage race protection', () => {
  it('tidak memaparkan butiran lama selepas panel ditutup', async () => {
    const deferredDetail = createDeferred<IdentityVerificationDetail>();

    vi.spyOn(adminApi, 'getIdentityVerifications')
      .mockResolvedValue(queueResult);

    const getDetail = vi.spyOn(
      adminApi,
      'getIdentityVerificationDetail'
    ).mockReturnValue(deferredDetail.promise);

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    expect(
      await screen.findByText('Pengguna Ujian')
    ).toBeTruthy();

    fireEvent.click(
      screen.getByRole('button', { name: 'Lihat Butiran' })
    );

    expect(getDetail).toHaveBeenCalledWith('account-001');

    expect(
      await screen.findByText('Memuatkan butiran permohonan...')
    ).toBeTruthy();

    fireEvent.click(
      screen.getByRole('button', { name: 'Tutup' })
    );

    expect(
      screen.queryByRole('heading', { name: 'Semakan Identiti' })
    ).toBeNull();

    deferredDetail.resolve(detailResult);

    await waitFor(() => {
      expect(
        screen.queryByRole('heading', { name: 'Semakan Identiti' })
      ).toBeNull();
    });

    expect(
      screen.queryByText('Dokumen sokongan')
    ).toBeNull();
  });

  it('menggunakan butang status dengan keadaan aria-pressed yang betul', async () => {
    vi.spyOn(adminApi, 'getIdentityVerifications')
      .mockResolvedValue(queueResult);

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    await screen.findByText('Pengguna Ujian');

    const pendingButton = screen.getByRole('button', {
      name: 'Menunggu'
    });

    const verifiedButton = screen.getByRole('button', {
      name: 'Disahkan'
    });

    expect(pendingButton.getAttribute('aria-pressed')).toBe('true');
    expect(verifiedButton.getAttribute('aria-pressed')).toBe('false');

    expect(pendingButton.getAttribute('role')).not.toBe('tab');
    expect(verifiedButton.getAttribute('role')).not.toBe('tab');
  });
});
