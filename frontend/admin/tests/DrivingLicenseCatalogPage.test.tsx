
import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  cleanup,
  fireEvent,
  render,
  screen
} from '@testing-library/react';

import DrivingLicenseCatalogPage from '../src/features/driving-license-catalog/DrivingLicenseCatalogPage';
import * as adminApi from '../src/services/adminApi';

import type {
  DrivingLicenseType,
  DrivingLicenseClass
} from '../src/types/drivingLicense';

import type { AdminProfile } from '../src/types/admin';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const activeType: DrivingLicenseType = {
  id: 'type-001',
  code: 'CDL',
  name: 'Competent Driving Licence',
  description: null,
  is_active: true,
  sort_order: 1
};

const inactiveType: DrivingLicenseType = {
  id: 'type-002',
  code: 'PDL',
  name: 'Probationary Driving Licence',
  description: null,
  is_active: false,
  sort_order: 2
};

const activeClass: DrivingLicenseClass = {
  id: 'class-001',
  driving_license_type_id: 'type-001',
  code: 'B2',
  name: 'Motosikal',
  description: null,
  is_active: true,
  sort_order: 1,
  license_type: {
    id: 'type-001',
    code: 'CDL',
    name: 'Competent Driving Licence',
    is_active: true
  }
};

function createAdmin(
  permissions: string[] = [],
  role = 'SUPER_ADMIN'
): AdminProfile {
  return {
    id: 'admin-test',
    profile_name: 'Admin Test',
    verified_name: null,
    phone: null,
    email: 'admin@example.com',
    profile_picture: null,
    role,
    status: 'ACTIVE',
    permissions
  };
}

describe('DrivingLicenseCatalogPage', () => {
  it('memuat katalog penuh untuk admin yang mengurus jenis dan kelas lesen', async () => {
    const getTypes = vi.spyOn(
      adminApi,
      'getDrivingLicenseTypes'
    ).mockResolvedValue([activeType, inactiveType]);

    const getClasses = vi.spyOn(
      adminApi,
      'getDrivingLicenseClasses'
    ).mockResolvedValue([activeClass]);

    render(
      <DrivingLicenseCatalogPage
        account={createAdmin([
          'driving_license_types.manage',
          'driving_license_catalog.manage'
        ])}
      />
    );

    const typeNames = await screen.findAllByText(
      'Competent Driving Licence'
    );

    expect(typeNames.length).toBeGreaterThanOrEqual(2);
    expect(screen.getByText('Probationary Driving Licence')).toBeTruthy();
    expect(screen.getByText('Motosikal')).toBeTruthy();

    expect(getTypes).toHaveBeenCalled();
    expect(getClasses).toHaveBeenCalled();
  });

  it('membenarkan admin kelas lesen melihat jenis lesen aktif sahaja', async () => {
    const getActiveTypes = vi.spyOn(
      adminApi,
      'getActiveDrivingLicenseTypes'
    ).mockResolvedValue([activeType]);

    const getClasses = vi.spyOn(
      adminApi,
      'getDrivingLicenseClasses'
    ).mockResolvedValue([activeClass]);

    render(
      <DrivingLicenseCatalogPage
        account={createAdmin([
          'driving_license_catalog.manage'
        ])}
      />
    );

    expect(
      await screen.findByRole('heading', { name: 'Kelas Lesen' })
    ).toBeTruthy();

    expect(getActiveTypes).toHaveBeenCalled();
    expect(getClasses).toHaveBeenCalled();

    expect(
      screen.queryByRole('heading', { name: 'Jenis Lesen' })
    ).toBeNull();

    expect(
      screen.getByRole('option', {
        name: 'CDL — Competent Driving Licence'
      })
    ).toBeTruthy();
  });

  it('membenarkan admin jenis lesen mengurus jenis tanpa paparan kelas', async () => {
    const getTypes = vi.spyOn(
      adminApi,
      'getDrivingLicenseTypes'
    ).mockResolvedValue([activeType]);

    const getClasses = vi.spyOn(
      adminApi,
      'getDrivingLicenseClasses'
    ).mockResolvedValue([]);

    render(
      <DrivingLicenseCatalogPage
        account={createAdmin([
          'driving_license_types.manage'
        ])}
      />
    );

    expect(
      await screen.findByRole('heading', { name: 'Jenis Lesen' })
    ).toBeTruthy();

    expect(getTypes).toHaveBeenCalled();
    expect(getClasses).not.toHaveBeenCalled();

    expect(
      screen.queryByRole('heading', { name: 'Kelas Lesen' })
    ).toBeNull();
  });

  it('menghantar data jenis lesen dengan kod dinormalkan', async () => {
    vi.spyOn(
      adminApi,
      'getDrivingLicenseTypes'
    ).mockResolvedValue([]);

    const createType = vi.spyOn(
      adminApi,
      'createDrivingLicenseType'
    ).mockResolvedValue(activeType);

    render(
      <DrivingLicenseCatalogPage
        account={createAdmin([
          'driving_license_types.manage'
        ])}
      />
    );

    await screen.findByRole('heading', { name: 'Jenis Lesen' });

    fireEvent.change(
      screen.getByLabelText('Kod jenis lesen'),
      { target: { value: ' cdl ' } }
    );

    fireEvent.change(
      screen.getByLabelText('Nama jenis lesen'),
      { target: { value: 'Competent Driving Licence' } }
    );

    fireEvent.click(
      screen.getByRole('button', { name: 'Tambah Jenis Lesen' })
    );

    expect(createType).toHaveBeenCalledWith({
      code: 'CDL',
      name: 'Competent Driving Licence',
      description: null,
      sort_order: 0
    });

    expect(
      await screen.findByText('Jenis lesen berjaya ditambah.')
    ).toBeTruthy();
  });

  it('menghantar kelas lesen dengan jenis dan kod yang betul', async () => {
    vi.spyOn(
      adminApi,
      'getActiveDrivingLicenseTypes'
    ).mockResolvedValue([activeType]);

    vi.spyOn(
      adminApi,
      'getDrivingLicenseClasses'
    ).mockResolvedValue([]);

    const createClass = vi.spyOn(
      adminApi,
      'createDrivingLicenseClass'
    ).mockResolvedValue(activeClass);

    render(
      <DrivingLicenseCatalogPage
        account={createAdmin([
          'driving_license_catalog.manage'
        ])}
      />
    );

    await screen.findByRole('heading', { name: 'Kelas Lesen' });

    fireEvent.change(
      screen.getByLabelText('Jenis lesen'),
      { target: { value: 'type-001' } }
    );

    fireEvent.change(
      screen.getByLabelText('Kod kelas'),
      { target: { value: ' b2 ' } }
    );

    fireEvent.change(
      screen.getByLabelText('Nama kelas'),
      { target: { value: 'Motosikal' } }
    );

    fireEvent.click(
      screen.getByRole('button', { name: 'Tambah Kelas Lesen' })
    );

    expect(createClass).toHaveBeenCalledWith({
      driving_license_type_id: 'type-001',
      code: 'B2',
      name: 'Motosikal',
      description: null,
      sort_order: 0
    });

    expect(
      await screen.findByText('Kelas lesen berjaya ditambah.')
    ).toBeTruthy();
  });

  it('meminta pengesahan sebelum menyahaktifkan kelas lesen', async () => {
    vi.spyOn(
      adminApi,
      'getActiveDrivingLicenseTypes'
    ).mockResolvedValue([activeType]);

    vi.spyOn(
      adminApi,
      'getDrivingLicenseClasses'
    ).mockResolvedValue([activeClass]);

    const deactivateClass = vi.spyOn(
      adminApi,
      'deactivateDrivingLicenseClass'
    ).mockResolvedValue({
      ...activeClass,
      is_active: false
    });

    const confirm = vi.fn().mockReturnValue(true);
    vi.stubGlobal('confirm', confirm);

    render(
      <DrivingLicenseCatalogPage
        account={createAdmin([
          'driving_license_catalog.manage'
        ])}
      />
    );

    await screen.findByText('Motosikal');

    fireEvent.click(
      screen.getByRole('button', { name: 'Nyahaktif' })
    );

    expect(confirm).toHaveBeenCalledWith(
      'Adakah anda pasti mahu nyahaktifkan kelas lesen B2?'
    );

    expect(deactivateClass).toHaveBeenCalledWith('class-001');
  });

  it('memaparkan ralat apabila API katalog gagal', async () => {
    vi.spyOn(
      adminApi,
      'getDrivingLicenseTypes'
    ).mockRejectedValue(
      new Error('Driving license API failed')
    );

    render(
      <DrivingLicenseCatalogPage
        account={createAdmin([
          'driving_license_types.manage'
        ])}
      />
    );

    expect(
      await screen.findByRole('alert')
    ).toBeTruthy();

    expect(
      screen.getByText('Driving license API failed')
    ).toBeTruthy();
  });
});
