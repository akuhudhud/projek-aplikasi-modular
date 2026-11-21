
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  render,
  screen,
  waitFor,
  within
} from '@testing-library/react';

import AdminApp from '../src/app/AdminApp';
import * as adminApi from '../src/services/adminApi';
import type { AdminProfile } from '../src/types/admin';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  sessionStorage.clear();
});

const adminProfile: AdminProfile = {
  id: 'admin-001',
  profile_name: 'Admin Test',
  verified_name: null,
  phone: '0123456789',
  email: 'admin@example.com',
  profile_picture: null,
  role: 'SUPER_ADMIN',
  status: 'ACTIVE',
  permissions: []
};

const userProfile: AdminProfile = {
  ...adminProfile,
  id: 'user-001',
  role: 'USER'
};

describe('Admin Web Application', () => {
  it('memaparkan identiti SUPER RUNNER pada halaman log masuk', () => {
    render(<AdminApp />);

    expect(
      screen.getByRole('heading', {
        name: 'Log Masuk Pentadbir'
      })
    ).toBeTruthy();

    expect(screen.getByText('SUPER RUNNER')).toBeTruthy();
  });

  it('menyediakan medan nombor telefon atau e-mel', () => {
    render(<AdminApp />);

    expect(
      screen.getByLabelText('Nombor Telefon / E-mel')
    ).toBeTruthy();

    expect(
      screen.getByLabelText('Kata Laluan')
    ).toBeTruthy();
  });

  it('tidak memaparkan dashboard sebelum pentadbir log masuk', () => {
    render(<AdminApp />);

    expect(
      screen.queryByRole('navigation', {
        name: 'Navigasi utama'
      })
    ).toBeNull();

    expect(
      screen.queryByRole('heading', {
        name: 'Selamat datang'
      })
    ).toBeNull();
  });

  it('menerima sesi yang dipulihkan untuk akaun Super Admin', async () => {
    adminApi.saveSession('test-admin-token');

    vi.spyOn(adminApi, 'getCurrentAdmin')
      .mockResolvedValue(adminProfile);

    render(<AdminApp />);

    const navigation = await screen.findByRole('navigation', {
      name: 'Navigasi utama'
    });

    expect(
      within(navigation).getByText('Dashboard')
    ).toBeTruthy();

    expect(
      within(navigation).queryByText('Pengurusan Pengguna')
    ).toBeNull();
  });

  it('menolak sesi yang dipulihkan untuk akaun USER', async () => {
    adminApi.saveSession('test-user-token');

    vi.spyOn(adminApi, 'getCurrentAdmin')
      .mockResolvedValue(userProfile);

    render(<AdminApp />);

    await waitFor(() => {
      expect(
        screen.getByRole('heading', {
          name: 'Log Masuk Pentadbir'
        })
      ).toBeTruthy();
    });

    expect(
      screen.getByText(
        'Akaun ini tidak mempunyai akses pentadbir.'
      )
    ).toBeTruthy();

    expect(adminApi.hasSession()).toBe(false);

    expect(
      screen.queryByRole('navigation', {
        name: 'Navigasi utama'
      })
    ).toBeNull();
  });
});
