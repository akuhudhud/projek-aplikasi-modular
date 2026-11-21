
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';

import AdminApp from '../src/app/AdminApp';

afterEach(() => {
  cleanup();
  sessionStorage.clear();
});

describe('Admin navigation', () => {
  it('memaparkan halaman log masuk apabila tiada sesi', () => {
    render(<AdminApp />);

    expect(
      screen.getByRole('heading', { name: 'Log Masuk Pentadbir' })
    ).toBeTruthy();
  });

  it('menyediakan medan log masuk telefon atau e-mel', () => {
    render(<AdminApp />);

    expect(screen.getByLabelText('Nombor Telefon / E-mel')).toBeTruthy();
    expect(screen.getByLabelText('Kata Laluan')).toBeTruthy();
  });
});
