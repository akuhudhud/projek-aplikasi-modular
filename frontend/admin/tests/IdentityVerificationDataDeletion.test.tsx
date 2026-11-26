
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  cleanup,
  fireEvent,
  render,
  screen
} from '@testing-library/react';

import IdentityVerificationDataDeletion from '../src/features/verification/components/IdentityVerificationDataDeletion';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe('IdentityVerificationDataDeletion', () => {
  it('memaparkan borang alasan pemadaman', () => {
    render(
      <IdentityVerificationDataDeletion
        working={false}
        onDelete={vi.fn()}
      />
    );

    expect(
      screen.getByRole('heading', {
        name: 'Pemadaman Data Identiti'
      })
    ).toBeTruthy();

    expect(
      screen.getByLabelText('Alasan pemadaman')
    ).toBeTruthy();
  });

  it('tidak membenarkan alasan kurang daripada 5 aksara', () => {
    const onDelete = vi.fn();

    render(
      <IdentityVerificationDataDeletion
        working={false}
        onDelete={onDelete}
      />
    );

    fireEvent.change(
      screen.getByLabelText('Alasan pemadaman'),
      { target: { value: 'abc' } }
    );

    expect(
      screen.getByRole('button', {
        name: 'Padam Data Identiti'
      }).hasAttribute('disabled')
    ).toBe(true);

    expect(onDelete).not.toHaveBeenCalled();
  });

  it('meminta pengesahan sebelum menghantar pemadaman', () => {
    const onDelete = vi.fn();
    const confirm = vi.spyOn(window, 'confirm')
      .mockReturnValue(false);

    render(
      <IdentityVerificationDataDeletion
        working={false}
        onDelete={onDelete}
      />
    );

    fireEvent.change(
      screen.getByLabelText('Alasan pemadaman'),
      { target: { value: 'Permintaan pemilik akaun' } }
    );

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Padam Data Identiti'
      })
    );

    expect(confirm).toHaveBeenCalledOnce();
    expect(onDelete).not.toHaveBeenCalled();
  });

  it('menghantar alasan selepas pengesahan diterima', () => {
    const onDelete = vi.fn();
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    render(
      <IdentityVerificationDataDeletion
        working={false}
        onDelete={onDelete}
      />
    );

    fireEvent.change(
      screen.getByLabelText('Alasan pemadaman'),
      { target: { value: '  Permintaan pemilik akaun  ' } }
    );

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Padam Data Identiti'
      })
    );

    expect(onDelete).toHaveBeenCalledWith(
      'Permintaan pemilik akaun'
    );
  });

  it('menyahaktifkan butang semasa pemadaman berjalan', () => {
    render(
      <IdentityVerificationDataDeletion
        working={true}
        onDelete={vi.fn()}
      />
    );

    expect(
      screen.getByRole('button', {
        name: 'Memadam Data...'
      }).hasAttribute('disabled')
    ).toBe(true);
  });
});
