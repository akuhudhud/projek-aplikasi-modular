
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

const users: AdminAccount[] = [
  {
    id: 'user-existing-001',
    profile_name: 'Ahmad Pengguna',
    verified_name: null,
    job_title: null,
    phone: '60123456789',
    email: 'ahmad@example.com',
    role: 'USER',
    status: 'ACTIVE',
    created_at: '2026-11-20T10:00:00.000Z',
    updated_at: '2026-11-20T10:00:00.000Z'
  }
];

const catalog = [
  { key: 'accounts.view', label: 'Lihat Akaun' },
  { key: 'accounts.suspend', label: 'Gantung Akaun' },
  {
    key: 'runner_applications.view',
    label: 'Lihat Permohonan Runner'
  }
];

function mockAccountList(accounts: AdminAccount[] = superAdmins) {
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

  const promise = new Promise<T>((resolvePromise) => {
    resolve = resolvePromise;
  });

  return { promise, resolve };
}

function setup() {
  const onRefresh = vi.fn().mockResolvedValue(undefined);

  vi.spyOn(adminApi, 'getAccounts')
    .mockImplementation(async (filters) => {
      if (filters.role === 'USER') {
        return mockAccountList(users);
      }

      return mockAccountList(superAdmins);
    });

  render(
    <SuperAdminManagement onRefresh={onRefresh} />
  );

  return { onRefresh };
}

async function selectAccount(
  accountId = 'super-admin-001'
) {
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
    setup();

    const getAccounts = vi.mocked(adminApi.getAccounts);

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

    const checkbox = await screen.findByRole(
      'checkbox',
      { name: /Lihat Akaun/ }
    );

    expect(
      (checkbox as HTMLInputElement).checked
    ).toBe(true);

    expect(getPermissions).toHaveBeenCalledWith(
      'super-admin-001'
    );
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

    const suspendCheckbox = await screen.findByRole(
      'checkbox',
      { name: /Gantung Akaun/ }
    );

    expect(
      (suspendCheckbox as HTMLInputElement).checked
    ).toBe(true);

    firstRequest.resolve(
      mockPermissions('super-admin-001', [
        'accounts.view'
      ])
    );

    await waitFor(() => {
      expect(
        (screen.getByRole('checkbox', {
          name: /Gantung Akaun/
        }) as HTMLInputElement).checked
      ).toBe(true);

      expect(
        (screen.getByRole('checkbox', {
          name: /Lihat Akaun/
        }) as HTMLInputElement).checked
      ).toBe(false);
    });
  });

  it('mengunci pemilihan akaun ketika jawatan sedang disimpan', async () => {
    const updateRequest = deferred<void>();

    setup();

    vi.spyOn(adminApi, 'getSuperAdminPermissions')
      .mockResolvedValue(mockPermissions());

    vi.spyOn(adminApi, 'updateSuperAdminJobTitle')
      .mockReturnValue(updateRequest.promise);

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
      expect(
        (screen.getByLabelText('Pilih Super Admin') as HTMLSelectElement)
          .disabled
      ).toBe(true);
    });

    expect(
      (screen.getByLabelText('Jawatan') as HTMLInputElement).disabled
    ).toBe(true);

    updateRequest.resolve(undefined);

    await waitFor(() => {
      expect(
        (screen.getByLabelText('Pilih Super Admin') as HTMLSelectElement)
          .disabled
      ).toBe(false);
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

    const saveButton = screen.getByRole('button', {
      name: 'Simpan Permission'
    });

    expect(
      (saveButton as HTMLButtonElement).disabled
    ).toBe(true);

    expect(updatePermissions).not.toHaveBeenCalled();
  });

  it('melantik akaun USER sedia ada tanpa mencipta akaun baharu', async () => {
    const { onRefresh } = setup();

    const appoint = vi.spyOn(
      adminApi,
      'appointSuperAdmin'
    ).mockResolvedValue(undefined);

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Lantik Super Admin'
      })
    );

    expect(
      await screen.findByRole('heading', {
        name: 'Lantik Super Admin daripada Akaun USER'
      })
    ).toBeTruthy();

    expect(
      await screen.findByRole('option', {
        name: /Ahmad Pengguna/
      })
    ).toBeTruthy();

    expect(adminApi.getAccounts).toHaveBeenCalledWith({
      role: 'USER',
      status: 'ACTIVE',
      search: undefined,
      page: 1,
      per_page: 100
    });

    fireEvent.change(
      screen.getByLabelText('Pilih Akaun USER'),
      { target: { value: 'user-existing-001' } }
    );

    fireEvent.change(
      screen.getByLabelText('Jawatan Super Admin'),
      { target: { value: 'Pengurus Operasi' } }
    );

    fireEvent.change(
      screen.getByLabelText('Sebab Pelantikan'),
      { target: { value: 'Keperluan operasi.' } }
    );

    expect(
      screen.getByLabelText('Nama Paparan')
    ).toHaveValue('Ahmad Pengguna');

    expect(
      screen.getByLabelText('Nombor Telefon')
    ).toHaveValue('60123456789');

    expect(
      screen.getByLabelText('E-mel')
    ).toHaveValue('ahmad@example.com');

    expect(
      screen.getByLabelText('ID Akaun')
    ).toHaveValue('user-existing-001');

    expect(
      screen.queryByLabelText('Kata Laluan')
    ).toBeNull();

    expect(
      screen.queryByLabelText('Sahkan Kata Laluan')
    ).toBeNull();

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Lantik sebagai Super Admin'
      })
    );

    await waitFor(() => {
      expect(appoint).toHaveBeenCalledWith(
        'user-existing-001',
        'Pengurus Operasi',
        'Keperluan operasi.'
      );
    });

    expect(
      await screen.findByText(
        'Pelantikan berjaya. Akaun USER sedia ada telah dinaikkan kepada Super Admin.'
      )
    ).toBeTruthy();

    expect(onRefresh).toHaveBeenCalled();
  });

  it('tidak melantik jika sebab pelantikan kosong', async () => {
    setup();

    const appoint = vi.spyOn(
      adminApi,
      'appointSuperAdmin'
    ).mockResolvedValue(undefined);

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Lantik Super Admin'
      })
    );

    await screen.findByRole('option', {
      name: /Ahmad Pengguna/
    });

    fireEvent.change(
      screen.getByLabelText('Pilih Akaun USER'),
      { target: { value: 'user-existing-001' } }
    );

    fireEvent.change(
      screen.getByLabelText('Jawatan Super Admin'),
      { target: { value: 'Pengurus Operasi' } }
    );

    expect(appoint).not.toHaveBeenCalled();
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
