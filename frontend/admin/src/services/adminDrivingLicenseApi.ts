
import type { ApiResponse } from '../types/admin';

import type {
  DrivingLicenseType,
  DrivingLicenseClass,
  DrivingLicenseTypeResult,
  DrivingLicenseClassResult,
  CreateDrivingLicenseTypePayload,
  UpdateDrivingLicenseTypePayload,
  CreateDrivingLicenseClassPayload,
  UpdateDrivingLicenseClassPayload
} from '../types/drivingLicense';

import { adminApi } from './adminApiClient';

export async function getDrivingLicenseTypes(): Promise<DrivingLicenseType[]> {
  const response = await adminApi.get<
    ApiResponse<DrivingLicenseTypeResult>
  >('/admin/driving-license-types');

  return response.data.data.types;
}

export async function getActiveDrivingLicenseTypes(): Promise<DrivingLicenseType[]> {
  const response = await adminApi.get<
    ApiResponse<DrivingLicenseTypeResult>
  >('/driving-license-types');

  return response.data.data.types;
}

export async function createDrivingLicenseType(
  payload: CreateDrivingLicenseTypePayload
): Promise<DrivingLicenseType> {
  const response = await adminApi.post<
    ApiResponse<{ type: DrivingLicenseType }>
  >('/admin/driving-license-types', payload);

  return response.data.data.type;
}

export async function updateDrivingLicenseType(
  id: string,
  payload: UpdateDrivingLicenseTypePayload
): Promise<DrivingLicenseType> {
  const response = await adminApi.patch<
    ApiResponse<{ type: DrivingLicenseType }>
  >(`/admin/driving-license-types/${encodeURIComponent(id)}`, payload);

  return response.data.data.type;
}

export async function deactivateDrivingLicenseType(
  id: string
): Promise<DrivingLicenseType> {
  const response = await adminApi.delete<
    ApiResponse<{ type: DrivingLicenseType }>
  >(`/admin/driving-license-types/${encodeURIComponent(id)}`);

  return response.data.data.type;
}

export async function getDrivingLicenseClasses(
  drivingLicenseTypeId?: string
): Promise<DrivingLicenseClass[]> {
  const response = await adminApi.get<
    ApiResponse<DrivingLicenseClassResult>
  >('/admin/driving-license-catalog', {
    params: drivingLicenseTypeId
      ? { driving_license_type_id: drivingLicenseTypeId }
      : {}
  });

  return response.data.data.licenses;
}

export async function createDrivingLicenseClass(
  payload: CreateDrivingLicenseClassPayload
): Promise<DrivingLicenseClass> {
  const response = await adminApi.post<
    ApiResponse<{ license: DrivingLicenseClass }>
  >('/admin/driving-license-catalog', payload);

  return response.data.data.license;
}

export async function updateDrivingLicenseClass(
  id: string,
  payload: UpdateDrivingLicenseClassPayload
): Promise<DrivingLicenseClass> {
  const response = await adminApi.patch<
    ApiResponse<{ license: DrivingLicenseClass }>
  >(`/admin/driving-license-catalog/${encodeURIComponent(id)}`, payload);

  return response.data.data.license;
}

export async function deactivateDrivingLicenseClass(
  id: string
): Promise<DrivingLicenseClass> {
  const response = await adminApi.delete<
    ApiResponse<{ license: DrivingLicenseClass }>
  >(`/admin/driving-license-catalog/${encodeURIComponent(id)}`);

  return response.data.data.license;
}
