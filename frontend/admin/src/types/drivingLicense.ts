export interface DrivingLicenseType {
  id: string;
  code: string;
  name: string;
  description: string | null;
  is_active: boolean;
  sort_order: number;
  classes_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface DrivingLicenseClass {
  id: string;
  driving_license_type_id: string;
  code: string;
  name: string;
  description: string | null;
  is_active: boolean;
  sort_order: number;
  license_type?: {
    id: string;
    code: string;
    name: string;
    is_active?: boolean;
  };
  created_at?: string;
  updated_at?: string;
}

export interface DrivingLicenseTypeResult {
  types: DrivingLicenseType[];
}

export interface DrivingLicenseClassResult {
  licenses: DrivingLicenseClass[];
}

export interface CreateDrivingLicenseTypePayload {
  code: string;
  name: string;
  description?: string | null;
  sort_order?: number;
}

export interface UpdateDrivingLicenseTypePayload {
  code?: string;
  name?: string;
  description?: string | null;
  sort_order?: number;
  is_active?: boolean;
}

export interface CreateDrivingLicenseClassPayload {
  driving_license_type_id: string;
  code: string;
  name: string;
  description?: string | null;
  sort_order?: number;
}

export interface UpdateDrivingLicenseClassPayload {
  driving_license_type_id?: string;
  code?: string;
  name?: string;
  description?: string | null;
  sort_order?: number;
  is_active?: boolean;
}
