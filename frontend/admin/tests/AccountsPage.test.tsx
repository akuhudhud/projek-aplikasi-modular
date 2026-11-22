
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor
} from '@testing-library/react';

import AccountsPage from '../src/features/accounts/AccountsPage';
import * as adminApi from '../src/services/adminApi';

import type {
  AdminAccount,
  AdminAccountDetail,
  AdminProfile
} from '../src/types/admin';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const adminAccount: AdminProfile = {
  id: 'admin-001',
  profile_name: 'Super Admin',
  verified_name: null,
  phone: '60111111111',
  email: 'admin@example.com',
  profile_picture: null,
  role: 'SUPER_ADMIN',
  status: 'ACTIVE',
  permissions: [
    'accounts.view',
    'accounts.suspend',
    'accounts.unsuspend',
    'accounts.reactivate'
  ]
};

const rootAccount: AdminProfile = {
  ...adminAccount,
  id: 'root-001',
  profile_name: 'Root Super Admin',
  role: 'ROOT_SUPER_ADMIN',
  permissions: []
};

const userAccount: AdminAccount = {
  id: 'user-001',
  profile_name: 'Pengguna Ujian',
  verified_name: null,
  job_title: null,
  phone: '60123456789',
  email: 'user@example.com',
  profile_picture: null,
  role: 'USER',
  status: 'ACTIVE',
  created_at: '2026-11-20T10:00:00.000Z',
  updated_at: '2026-11-20T10:00:00.000Z'
};

const superAdminAccount: AdminAccount = {
  ...userAccount,
  id: 'super-admin-001',
  profile_name: 'Pentadbir Ujian',
  role: 'SUPER_ADMIN',
  email: 'superadmin@example.com'
};

const accountDetail: AdminAccountDetail = {
  ...userAccount,
  verified_name: 'Nama Sebenar Disahkan',
  identity_status: 'VERIFIED',
  identity_submitted_at: '2026-11-19T10:00:00.000Z',
  identity_reviewed_at: '2026-11-20T10:00:00.000Z'
};

function mockAccountList(accounts: AdminAccount[] = [userAccount]) {
  return {
    accounts,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: accounts.length,
      from: accounts.length > 0 ? 1 : null,
      to: accounts.length > 0 ? accounts.length : null
    }
  };
}

describe('AccountsPage', () => {
  it('memaparkan direktori akaun daripada API', async () => {
    vi.spyOn(adminApi, 'getAccounts')
      .mockResolvedValue(mockAccountList());

    render(<AccountsPage account={adminAccount} />);

    expect(
      await screen.findByText('Pengguna Ujian')
    ).toBeTruthy();

    expect(screen.getByText('user@example.com')).toBeTruthy();
    expect(screen.getByText('ACTIVE')).toBeTruthy();
  });

  it('menghantar carian kepada API', async () => {
    const getAccounts = vi.spyOn(adminApi, 'getAccounts')
      .mockResolvedValue(mockAccountList());

    render(<AccountsPage account={adminAccount} />);

    await screen.findByText('Pengguna Ujian');

    fireEvent.change(
      screen.getByPlaceholderText('Nama, telefon atau e-mel'),
      { target: { value: 'Nazim' } }
    );

    fireEvent.click(screen.getByRole('button', { name: 'Cari' }));

    await waitFor(() => {
      expect(getAccounts).toHaveBeenLastCalledWith(
        expect.objectContaining({
          search: 'Nazim',
          page: 1,
          per_page: 15
        })
      );
    });
  });

  it('memaparkan butiran akaun apabila butang Lihat ditekan', async () => {
    vi.spyOn(adminApi, 'getAccounts')
      .mockResolvedValue(mockAccountList());

    vi.spyOn(adminApi, 'getAccountDetail')
      .mockResolvedValue(accountDetail);

    render(<AccountsPage account={adminAccount} />);

    await screen.findByText('Pengguna Ujian');

    fireEvent.click(screen.getByRole('button', { name: 'Lihat' }));

    expect(
      await screen.findByText('Nama Sebenar Disahkan')
    ).toBeTruthy();

    expect(screen.getByText('Status Identiti')).toBeTruthy();
    expect(screen.getByText('VERIFIED')).toBeTruthy();
  });

  it('memaparkan tindakan Suspend berdasarkan permission', async () => {
    vi.spyOn(adminApi, 'getAccounts')
      .mockResolvedValue(mockAccountList());

    vi.spyOn(adminApi, 'getAccountDetail')
      .mockResolvedValue(accountDetail);

    render(<AccountsPage account={adminAccount} />);

    await screen.findByText('Pengguna Ujian');

    fireEvent.click(screen.getByRole('button', { name: 'Lihat' }));

    expect(
      await screen.findByRole('button', { name: 'Gantung Akaun' })
    ).toBeTruthy();
  });

  it('tidak memaparkan tindakan jika permission tiada', async () => {
    const restrictedAdmin: AdminProfile = {
      ...adminAccount,
      permissions: ['accounts.view']
    };

    vi.spyOn(adminApi, 'getAccounts')
      .mockResolvedValue(mockAccountList());

    vi.spyOn(adminApi, 'getAccountDetail')
      .mockResolvedValue(accountDetail);

    render(<AccountsPage account={restrictedAdmin} />);

    await screen.findByText('Pengguna Ujian');

    fireEvent.click(screen.getByRole('button', { name: 'Lihat' }));

    await screen.findByText('Nama Sebenar Disahkan');

    expect(
      screen.queryByRole('button', { name: 'Gantung Akaun' })
    ).toBeNull();
  });

  it('menghantar sebab tindakan apabila Suspend disahkan', async () => {
    vi.spyOn(adminApi, 'getAccounts')
      .mockResolvedValue(mockAccountList());

    vi.spyOn(adminApi, 'getAccountDetail')
      .mockResolvedValue(accountDetail);

    const suspend = vi.spyOn(adminApi, 'suspendAccount')
      .mockResolvedValue();

    render(<AccountsPage account={adminAccount} />);

    await screen.findByText('Pengguna Ujian');

    fireEvent.click(screen.getByRole('button', { name: 'Lihat' }));

    fireEvent.click(
      await screen.findByRole('button', { name: 'Gantung Akaun' })
    );

    fireEvent.change(
      screen.getByPlaceholderText('Masukkan sebab tindakan ini...'),
      { target: { value: 'Semakan pentadbiran.' } }
    );

    fireEvent.click(
      screen.getByRole('button', { name: 'Sahkan Tindakan' })
    );

    await waitFor(() => {
      expect(suspend).toHaveBeenCalledWith(
        'user-001',
        'Semakan pentadbiran.'
      );
    });
  });

  it('memaparkan keadaan kosong apabila tiada akaun', async () => {
    vi.spyOn(adminApi, 'getAccounts')
      .mockResolvedValue(mockAccountList([]));

    render(<AccountsPage account={adminAccount} />);

    expect(
      await screen.findByText('Tiada rekod akaun ditemui.')
    ).toBeTruthy();
  });

  it('memaparkan ralat apabila API gagal', async () => {
    vi.spyOn(adminApi, 'getAccounts')
      .mockRejectedValue(new Error('Directory API failed'));

    render(<AccountsPage account={adminAccount} />);

    expect(
      await screen.findByRole('alert')
    ).toBeTruthy();

    expect(
      screen.getByText('Directory API failed')
    ).toBeTruthy();
  });

  it('memaparkan Pengurusan Super Admin untuk Root sahaja', async () => {
    vi.spyOn(adminApi, 'getAccounts')
      .mockImplementation(async (params) => {
        if (params?.role === 'SUPER_ADMIN') {
          return mockAccountList([superAdminAccount]);
        }

        return mockAccountList();
      });

    render(<AccountsPage account={rootAccount} />);

    expect(
      await screen.findByRole('heading', {
        name: 'Pengurusan Super Admin'
      })
    ).toBeTruthy();

    expect(
      await screen.findByText('Senarai Super Admin')
    ).toBeTruthy();

    expect(
      await screen.findByRole('option', {
        name: /Pentadbir Ujian/
      })
    ).toBeTruthy();
  });

  it('tidak memaparkan Pengurusan Super Admin kepada Super Admin biasa', async () => {
    vi.spyOn(adminApi, 'getAccounts')
      .mockResolvedValue(mockAccountList());

    render(<AccountsPage account={adminAccount} />);

    await screen.findByText('Pengguna Ujian');

    expect(
      screen.queryByRole('heading', {
        name: 'Pengurusan Super Admin'
      })
    ).toBeNull();

    expect(
      screen.queryByRole('button', {
        name: 'Tambah Super Admin'
      })
    ).toBeNull();
  });
});
