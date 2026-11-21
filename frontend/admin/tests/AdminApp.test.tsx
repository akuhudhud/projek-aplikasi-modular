
import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import AdminApp from '../src/app/AdminApp';

describe('Admin Web Application', () => {
  it('memaparkan identiti SUPER RUNNER Admin', () => {
    render(<AdminApp />);

    expect(screen.getByText('SUPER RUNNER')).toBeTruthy();
    expect(screen.getByText('ADMINISTRATION')).toBeTruthy();
  });

  it('memaparkan semua navigasi utama', () => {
    render(<AdminApp />);

    const navigation = screen.getByRole('navigation', {
      name: 'Navigasi utama'
    });

    expect(navigation).toBeTruthy();

    const items = [
      'Dashboard',
      'Pengurusan Pengguna',
      'Permohonan Runner',
      'Pengesahan Identiti',
      'Pengesahan Lesen',
      'Log Audit'
    ];

    for (const item of items) {
      expect(screen.getByText(item)).toBeTruthy();
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

    const activeItem = screen.getByText('Dashboard');

    expect(
      activeItem.closest('[aria-current="page"]')
    ).toBeTruthy();
  });
});
