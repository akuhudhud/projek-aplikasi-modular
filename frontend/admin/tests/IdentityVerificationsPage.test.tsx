
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

const restrictedAccount: AdminProfile = {
  ...adminAccount,
  permissions: []
};

const rootAccount: AdminProfile = {
  ...adminAccount,
  id: 'root-001',
  role: 'ROOT_SUPER_ADMIN',
  permissions: []
};

const queueItem = {
  account_id: 'account-001',
  profile_name: 'Pengguna Ujian',
  phone: '60123456789',
  email: 'user@example.com',
  verified_name: 'Nama Sebenar Pengguna',
  mykad_number_masked: '********5678',
  status: 'PENDING' as const,
  submitted_at: '2026-11-21T10:00:00.000Z',
  reviewed_at: null,
  documents_count: 1
};

const detail: IdentityVerificationDetail = {
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

function mockQueue(
  items = [queueItem]
): IdentityVerificationQueueResult {
  return {
    verifications: items,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: items.length,
      from: items.length > 0 ? 1 : null,
      to: items.length > 0 ? items.length : null
    }
  };
}

describe('IdentityVerificationsPage', () => {
  it('memaparkan senarai permohonan daripada API', async () => {
    const getQueue = vi.spyOn(
      adminApi,
      'getIdentityVerifications'
    ).mockResolvedValue(mockQueue());

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    expect(
      await screen.findByText('Pengguna Ujian')
    ).toBeTruthy();

    expect(screen.getByText('Nama Sebenar Pengguna')).toBeTruthy();
    expect(screen.getByText('********5678')).toBeTruthy();
    expect(screen.getByText('1 fail')).toBeTruthy();

    expect(getQueue).toHaveBeenCalledWith({
      status: 'PENDING',
      search: undefined,
      page: 1,
      per_page: 15
    });
  });

  it('memaparkan keadaan kosong apabila tiada permohonan', async () => {
    vi.spyOn(adminApi, 'getIdentityVerifications')
      .mockResolvedValue(mockQueue([]));

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    expect(
      await screen.findByText(
        'Tiada permohonan ditemui untuk tapisan ini.'
      )
    ).toBeTruthy();
  });

  it('menghantar carian kepada API', async () => {
    const getQueue = vi.spyOn(
      adminApi,
      'getIdentityVerifications'
    ).mockResolvedValue(mockQueue());

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    await screen.findByText('Pengguna Ujian');

    fireEvent.change(
      screen.getByPlaceholderText(
        'Cari nama, nombor telefon atau e-mel'
      ),
      { target: { value: 'Nama Sebenar' } }
    );

    fireEvent.click(
      screen.getByRole('button', { name: 'Cari' })
    );

    await waitFor(() => {
      expect(getQueue).toHaveBeenLastCalledWith({
        status: 'PENDING',
        search: 'Nama Sebenar',
        page: 1,
        per_page: 15
      });
    });
  });

  it('menghantar tapisan status kepada API', async () => {
    const getQueue = vi.spyOn(
      adminApi,
      'getIdentityVerifications'
    ).mockResolvedValue(mockQueue());

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    await screen.findByText('Pengguna Ujian');

    fireEvent.click(
      screen.getByRole('button', { name: 'Disahkan' })
    );

    await waitFor(() => {
      expect(getQueue).toHaveBeenLastCalledWith({
        status: 'VERIFIED',
        search: undefined,
        page: 1,
        per_page: 15
      });
    });
  });

  it('memaparkan butiran permohonan apabila diminta', async () => {
    vi.spyOn(adminApi, 'getIdentityVerifications')
      .mockResolvedValue(mockQueue());

    const getDetail = vi.spyOn(
      adminApi,
      'getIdentityVerificationDetail'
    ).mockResolvedValue(detail);

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    await screen.findByText('Pengguna Ujian');

    fireEvent.click(
      screen.getByRole('button', { name: 'Lihat Butiran' })
    );

    expect(
      await screen.findByRole('heading', {
        name: 'Semakan Identiti'
      })
    ).toBeTruthy();

    expect(
      screen.getByText('Dokumen sokongan')
    ).toBeTruthy();

    expect(
      screen.getByText('application/pdf')
    ).toBeTruthy();

    expect(getDetail).toHaveBeenCalledWith('account-001');
  });

  it('menghantar keputusan VERIFIED kepada API', async () => {
    vi.spyOn(adminApi, 'getIdentityVerifications')
      .mockResolvedValue(mockQueue());

    vi.spyOn(adminApi, 'getIdentityVerificationDetail')
      .mockResolvedValue(detail);

    const decide = vi.spyOn(
      adminApi,
      'decideIdentityVerification'
    ).mockResolvedValue(undefined);

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    await screen.findByText('Pengguna Ujian');

    fireEvent.click(
      screen.getByRole('button', { name: 'Lihat Butiran' })
    );

    await screen.findByText('Dokumen sokongan');

    fireEvent.click(
      screen.getByRole('button', { name: 'Sahkan Identiti' })
    );

    await waitFor(() => {
      expect(decide).toHaveBeenCalledWith(
        'account-001',
        'VERIFIED',
        undefined
      );
    });

    expect(
      await screen.findByText(
        'Pengesahan identiti berjaya diluluskan.'
      )
    ).toBeTruthy();
  });

  it('mewajibkan alasan sebelum permohonan ditolak', async () => {
    vi.spyOn(adminApi, 'getIdentityVerifications')
      .mockResolvedValue(mockQueue());

    vi.spyOn(adminApi, 'getIdentityVerificationDetail')
      .mockResolvedValue(detail);

    const decide = vi.spyOn(
      adminApi,
      'decideIdentityVerification'
    ).mockResolvedValue(undefined);

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    await screen.findByText('Pengguna Ujian');

    fireEvent.click(
      screen.getByRole('button', { name: 'Lihat Butiran' })
    );

    await screen.findByText('Dokumen sokongan');

    fireEvent.click(
      screen.getByRole('button', { name: 'Tolak Permohonan' })
    );

    expect(
      await screen.findByText('Alasan penolakan wajib diisi.')
    ).toBeTruthy();

    expect(decide).not.toHaveBeenCalled();
  });

  it('menghantar alasan penolakan kepada API', async () => {
    vi.spyOn(adminApi, 'getIdentityVerifications')
      .mockResolvedValue(mockQueue());

    vi.spyOn(adminApi, 'getIdentityVerificationDetail')
      .mockResolvedValue(detail);

    const decide = vi.spyOn(
      adminApi,
      'decideIdentityVerification'
    ).mockResolvedValue(undefined);

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    await screen.findByText('Pengguna Ujian');

    fireEvent.click(
      screen.getByRole('button', { name: 'Lihat Butiran' })
    );

    await screen.findByText('Dokumen sokongan');

    fireEvent.change(
      screen.getByLabelText('Alasan penolakan'),
      { target: { value: 'Dokumen tidak jelas.' } }
    );

    fireEvent.click(
      screen.getByRole('button', { name: 'Tolak Permohonan' })
    );

    await waitFor(() => {
      expect(decide).toHaveBeenCalledWith(
        'account-001',
        'REJECTED',
        'Dokumen tidak jelas.'
      );
    });
  });

  it('tidak membenarkan akses kepada akaun tanpa permission', () => {
    const getQueue = vi.spyOn(
      adminApi,
      'getIdentityVerifications'
    );

    render(
      <IdentityVerificationsPage account={restrictedAccount} />
    );

    expect(
      screen.getByText(
        'Anda tidak mempunyai kebenaran menyemak pengesahan identiti.'
      )
    ).toBeTruthy();

    expect(getQueue).not.toHaveBeenCalled();
  });

  it('Root boleh mengakses modul tanpa permission individu', async () => {
    vi.spyOn(adminApi, 'getIdentityVerifications')
      .mockResolvedValue(mockQueue());

    render(
      <IdentityVerificationsPage account={rootAccount} />
    );

    expect(
      await screen.findByText('Pengguna Ujian')
    ).toBeTruthy();
  });

  it('memaparkan ralat apabila API queue gagal', async () => {
    vi.spyOn(adminApi, 'getIdentityVerifications')
      .mockRejectedValue(new Error('Identity queue failed'));

    render(
      <IdentityVerificationsPage account={adminAccount} />
    );

    expect(
      await screen.findByRole('alert')
    ).toBeTruthy();

    expect(
      screen.getByText('Identity queue failed')
    ).toBeTruthy();
  });
});
