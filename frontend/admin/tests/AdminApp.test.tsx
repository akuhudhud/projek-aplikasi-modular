
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';

import AdminApp from '../src/app/AdminApp';

afterEach(() => {
  cleanup();
});

describe('Admin Web Application', () => {
  it('memaparkan identiti SUPER RUNNER Admin', () => {
    render(<AdminApp />);

    expect(screen.getAllByText('SUPER RUNNER').length).toBeGreaterThan(0);
    expect(screen.getByText('ADMINISTRATION')).toBeTruthy();
  });

  it('memaparkan semua navigasi utama', () => {
    render(<AdminApp />);

    const navigation = screen.getByRole('navigation', {
      name: 'Navigasi utama'
    });

    const items = [
      'Dashboard',
      'Pengurusan Pengguna',
      'Permohonan Runner',
      'Pengesahan Identiti',
      'Pengesahan Lesen',
      'Log Audit'
    ];

    for (const item of items) {
      expect(navigation.textContent?.includes(item)).toBe(true);
    }
  });

  it('memaparkan dashboard tanpa statistik rekaan', () => {
    render(<AdminApp />);

    expect(
      screen.getByRole('heading', { name: 'Selamat datang' })
    ).toBeTruthy();

    expect(
      screen.getByText('Dashboard sedang disediakan')
    ).toBeTruthy();

    expect(
      screen.getByText(/tidak menggunakan data contoh/i)
    ).toBeTruthy();
  });

  it('menandakan Dashboard sebagai halaman aktif', () => {
    render(<AdminApp />);

    const navigation = screen.getByRole('navigation', {
      name: 'Navigasi utama'
    });

    const activeItem = navigation.querySelector(
      '[aria-current="page"]'
    );

    expect(activeItem?.textContent).toContain('Dashboard');
  });
});
