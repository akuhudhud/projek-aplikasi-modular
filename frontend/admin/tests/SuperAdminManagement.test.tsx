
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor
} from '@testing-library/react';

import SuperAdminManagement from '../src/features/accounts/SuperAdminManagement';
import * as adminApi from '../src/services/adminApi';

import type {
  AdminAccount,
  SuperAdminPermissionResult
} from '../src/types/admin';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const superAdmins: AdminAccount[] = [
  {
    id: 'super-admin-001',
    profile_name: 'Pentadbir Satu',
    verified_name: null,
    job_title: 'Pengurus',
    phone: '60111111111',
    email: 'admin1@example.com',
    role: 'SUPER_ADMIN',
    status: 'ACTIVE',
    created_at: '2026-11-20T10:00:00.000Z',
    updated_at: '2026-11-20T10:00:00.000Z'
  },
  {
    id: 'super-admin-002',
    profile_name: 'Pentadbir Dua',
    verified_name: null,
    job_title: 'Penyelaras',
    phone: '60222222222',
    email: 'admin2@example.com',
    role: 'SUPER_ADMIN',
    status: 'ACTIVE',
    created_at: '2026-11-20T10:00:00.000Z',
    updated_at: '2026-11-20T10:00:00.000Z'
  }
];

const catalog = [
  {
    key: 'accounts.view',
    label: 'Lihat Akaun'
  },
  {
    key: 'accounts.suspend',
    label: 'Gantung Akaun'
  },
  {
    key: 'runner_applications.view',
    label: 'Lihat Permohonan Runner'
  }
];

function mockAccountList(accounts = superAdmins) {
  return {
    accounts,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 100,
      total: accounts.length,
      from: accounts.length > 0 ? 1 : null,
      to: accounts.length > 0 ? accounts.length : null
    }
  };
}

function mockPermissions(
  accountId = 'super-admin-001',
  permissions = ['accounts.view']
): SuperAdminPermissionResult {
  const account = superAdmins.find(
    (item) => item.id === accountId
  )!;

  return {
    account: {
      id: account.id,
      profile_name: account.profile_name,
      job_title: account.job_title,
      role: 'SUPER_ADMIN'
    },
    catalog,
    permissions
  };
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;

  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });

  return { promise, resolve, reject };
}

function setup() {
  const onRefresh = vi.fn().mockResolvedValue(undefined);

  vi.spyOn(adminApi, 'getAccounts')
    .mockResolvedValue(mockAccountList());

  render(
    <SuperAdminManagement onRefresh={onRefresh} />
  );

  return { onRefresh };
}

async function selectAccount(accountId = 'super-admin-001') {
  await screen.findByRole('option', {
    name: /Pentadbir Satu/
  });

  fireEvent.change(
    screen.getByLabelText('Pilih Super Admin'),
    { target: { value: accountId } }
  );
}

describe('SuperAdminManagement', () => {
  it('memaparkan senarai Super Admin daripada API', async () => {
    const getAccounts = vi.spyOn(adminApi, 'getAccounts')
      .mockResolvedValue(mockAccountList());

    setup();

    expect(
      await screen.findByRole('heading', {
        name: 'Senarai Super Admin'
      })
    ).toBeTruthy();

    expect(
      screen.getByRole('option', {
        name: /Pentadbir Satu/
      })
    ).toBeTruthy();

    expect(getAccounts).toHaveBeenCalledWith({
      role: 'SUPER_ADMIN',
      page: 1,
      per_page: 100
    });
  });

  it('memuatkan permission apabila akaun dipilih', async () => {
    const getPermissions = vi.spyOn(
      adminApi,
      'getSuperAdminPermissions'
    ).mockResolvedValue(mockPermissions());

    setup();

    await selectAccount();

    expect(
      await screen.findByRole('checkbox', {
        name: /Lihat Akaun/
      })
    ).toBeTruthy();

    expect(getPermissions).toHaveBeenCalledWith(
      'super-admin-001'
    );

    expect(
      screen.getByRole('checkbox', {
        name: /Lihat Akaun/
      })
    ).toBeChecked();
  });

  it('mengabaikan respons permission lama apabila akaun ditukar', async () => {
    const firstRequest = deferred<SuperAdminPermissionResult>();
    const secondRequest = deferred<SuperAdminPermissionResult>();

    vi.spyOn(adminApi, 'getSuperAdminPermissions')
      .mockImplementation((accountId) => {
        if (accountId === 'super-admin-001') {
          return firstRequest.promise;
        }

        return secondRequest.promise;
      });

    setup();

    await selectAccount('super-admin-001');

    fireEvent.change(
      screen.getByLabelText('Pilih Super Admin'),
      { target: { value: 'super-admin-002' } }
    );

    secondRequest.resolve(
      mockPermissions('super-admin-002', [
        'accounts.suspend'
      ])
    );

    expect(
      await screen.findByRole('checkbox', {
        name: /Gantung Akaun/
      })
    ).toBeChecked();

    firstRequest.resolve(
      mockPermissions('super-admin-001', [
        'accounts.view'
      ])
    );

    await waitFor(() => {
      expect(
        screen.getByRole('checkbox', {
          name: /Gantung Akaun/
        })
      ).toBeChecked();

      expect(
        screen.getByRole('checkbox', {
          name: /Lihat Akaun/
        })
      ).not.toBeChecked();
    });
  });

  it('mengemas kini jawatan bersama sebab perubahan', async () => {
    setup();

    const updateJobTitle = vi.spyOn(
      adminApi,
      'updateSuperAdminJobTitle'
    ).mockResolvedValue(undefined);

    vi.spyOn(adminApi, 'getSuperAdminPermissions')
      .mockResolvedValue(mockPermissions());

    await selectAccount();

    await screen.findByRole('checkbox', {
      name: /Lihat Akaun/
    });

    fireEvent.change(
      screen.getByLabelText('Jawatan'),
      { target: { value: 'Pengarah Operasi' } }
    );

    fireEvent.change(
      screen.getByLabelText('Sebab Perubahan Jawatan'),
      { target: { value: 'Penyusunan organisasi.' } }
    );

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Simpan Jawatan'
      })
    );

    await waitFor(() => {
      expect(updateJobTitle).toHaveBeenCalledWith(
        'super-admin-001',
        'Pengarah Operasi',
        'Penyusunan organisasi.'
      );
    });

    expect(
      await screen.findByText(
        'Jawatan Super Admin berjaya dikemas kini.'
      )
    ).toBeTruthy();
  });

  it('menyimpan perubahan permission bersama sebab perubahan', async () => {
    setup();

    vi.spyOn(adminApi, 'getSuperAdminPermissions')
      .mockResolvedValue(mockPermissions());

    const updatePermissions = vi.spyOn(
      adminApi,
      'updateSuperAdminPermissions'
    ).mockResolvedValue(undefined);

    await selectAccount();

    const suspendPermission = await screen.findByRole(
      'checkbox',
      { name: /Gantung Akaun/ }
    );

    fireEvent.click(suspendPermission);

    fireEvent.change(
      screen.getByLabelText('Sebab Perubahan Permission'),
      { target: { value: 'Tanggungjawab pentadbiran.' } }
    );

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Simpan Permission'
      })
    );

    await waitFor(() => {
      expect(updatePermissions).toHaveBeenCalledWith(
        'super-admin-001',
        ['accounts.view', 'accounts.suspend'],
        'Tanggungjawab pentadbiran.'
      );
    });

    expect(
      await screen.findByText(
        'Permission Super Admin berjaya dikemas kini.'
      )
    ).toBeTruthy();
  });

  it('mewajibkan sebab sebelum perubahan permission disimpan', async () => {
    setup();

    vi.spyOn(adminApi, 'getSuperAdminPermissions')
      .mockResolvedValue(mockPermissions());

    const updatePermissions = vi.spyOn(
      adminApi,
      'updateSuperAdminPermissions'
    ).mockResolvedValue(undefined);

    await selectAccount();

    await screen.findByRole('checkbox', {
      name: /Lihat Akaun/
    });

    expect(
      screen.getByRole('button', {
        name: 'Simpan Permission'
      })
    ).toBeDisabled();

    expect(updatePermissions).not.toHaveBeenCalled();
  });

  it('mewujudkan akaun Super Admin dengan data lengkap', async () => {
    setup();

    const create = vi.spyOn(
      adminApi,
      'createSuperAdmin'
    ).mockResolvedValue(undefined);

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Tambah Super Admin'
      })
    );

    fireEvent.change(
      screen.getByLabelText('Nama Paparan'),
      { target: { value: 'Pentadbir Baharu' } }
    );

    fireEvent.change(
      screen.getByLabelText('Jawatan'),
      { target: { value: 'Pengurus' } }
    );

    fireEvent.change(
      screen.getByLabelText('Nombor Telefon'),
      { target: { value: '60123456789' } }
    );

    fireEvent.change(
      screen.getByLabelText('E-mel'),
      { target: { value: 'newadmin@example.com' } }
    );

    fireEvent.change(
      screen.getByLabelText('Kata Laluan'),
      { target: { value: 'Password123' } }
    );

    fireEvent.change(
      screen.getByLabelText('Sahkan Kata Laluan'),
      { target: { value: 'Password123' } }
    );

    fireEvent.change(
      screen.getByLabelText('Sebab Pelantikan'),
      { target: { value: 'Keperluan operasi.' } }
    );

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Sahkan Pelantikan'
      })
    );

    await waitFor(() => {
      expect(create).toHaveBeenCalledWith({
        profile_name: 'Pentadbir Baharu',
        job_title: 'Pengurus',
        phone: '60123456789',
        email: 'newadmin@example.com',
        password: 'Password123',
        password_confirmation: 'Password123',
        reason: 'Keperluan operasi.'
      });
    });

    expect(
      await screen.findByText(
        'Akaun Super Admin berjaya diwujudkan.'
      )
    ).toBeTruthy();
  });

  it('memaparkan ralat apabila permintaan permission gagal', async () => {
    setup();

    vi.spyOn(adminApi, 'getSuperAdminPermissions')
      .mockRejectedValue(
        new Error('Permission API failed')
      );

    await selectAccount();

    expect(
      await screen.findByRole('alert')
    ).toBeTruthy();

    expect(
      screen.getByText('Permission API failed')
    ).toBeTruthy();
  });
});
