
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

const admin: AdminProfile = {
  id: 'admin-001',
  profile_name: 'Super Admin',
  verified_name: null,
  phone: '60111111111',
  email: 'admin@example.com',
  profile_picture: null,
  role: 'SUPER_ADMIN',
  status: 'ACTIVE',
  permissions: ['accounts.view']
};

const firstAccount: AdminAccount = {
  id: 'user-001',
  profile_name: 'Pengguna Pertama',
  verified_name: null,
  job_title: null,
  phone: '60111111111',
  email: 'first@example.com',
  profile_picture: null,
  role: 'USER',
  status: 'ACTIVE',
  created_at: '2026-11-20T10:00:00.000Z',
  updated_at: '2026-11-20T10:00:00.000Z'
};

const secondAccount: AdminAccount = {
  ...firstAccount,
  id: 'user-002',
  profile_name: 'Pengguna Kedua',
  email: 'second@example.com'
};

function detail(account: AdminAccount): AdminAccountDetail {
  return {
    ...account,
    identity_status: null,
    identity_submitted_at: null,
    identity_reviewed_at: null
  };
}

function deferred<T>() {
  let resolve!: (value: T) => void;

  const promise = new Promise<T>((resolvePromise) => {
    resolve = resolvePromise;
  });

  return { promise, resolve };
}

describe('AccountsPage detail request ordering', () => {
  it('tidak membenarkan respons akaun lama menggantikan akaun yang dipilih kemudian', async () => {
    const firstRequest = deferred<AdminAccountDetail>();
    const secondRequest = deferred<AdminAccountDetail>();

    vi.spyOn(adminApi, 'getAccounts').mockResolvedValue({
      accounts: [firstAccount, secondAccount],
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 2,
        from: 1,
        to: 2
      }
    });

    vi.spyOn(adminApi, 'getAccountDetail')
      .mockImplementation((accountId) => {
        if (accountId === firstAccount.id) {
          return firstRequest.promise;
        }

        return secondRequest.promise;
      });

    render(<AccountsPage account={admin} />);

    await screen.findByText('Pengguna Pertama');

    const viewButtons = screen.getAllByRole('button', {
      name: 'Lihat'
    });

    fireEvent.click(viewButtons[0]);

    await waitFor(() => {
      expect(adminApi.getAccountDetail).toHaveBeenCalledWith(
        firstAccount.id
      );
    });

    fireEvent.click(viewButtons[1]);

    await waitFor(() => {
      expect(adminApi.getAccountDetail).toHaveBeenCalledWith(
        secondAccount.id
      );
    });

    secondRequest.resolve(detail(secondAccount));

    expect(
      await screen.findByRole('heading', {
        name: 'Pengguna Kedua'
      })
    ).toBeTruthy();

    firstRequest.resolve(detail(firstAccount));

    await waitFor(() => {
      expect(
        screen.getByRole('heading', {
          name: 'Pengguna Kedua'
        })
      ).toBeTruthy();

      expect(
        screen.queryByRole('heading', {
          name: 'Pengguna Pertama'
        })
      ).toBeNull();
    });
  });
});
