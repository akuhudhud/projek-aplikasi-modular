
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';

import AdminApp from '../src/app/AdminApp';

afterEach(() => {
  cleanup();
  sessionStorage.clear();
});

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
});
