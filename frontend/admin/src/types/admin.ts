export type AdminPage =
  | 'dashboard'
  | 'accounts'
  | 'runner-applications'
  | 'identity-verifications'
  | 'license-verifications'
  | 'audit-logs';

export interface AdminAccount {
  id: string;
  profile_name: string;
  verified_name: string | null;
  job_title: string | null;
  phone: string | null;
  email: string | null;
  profile_picture?: string | null;
  role: string;
  status: string;
  created_at: string;
  updated_at: string;
}

export interface AccountPagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface AccountDirectoryResult {
  accounts: AdminAccount[];
  pagination: AccountPagination;
}

export interface AdminAccountDetail extends AdminAccount {
  identity_status: string | null;
  identity_submitted_at: string | null;
  identity_reviewed_at: string | null;
}

export interface AdminPermission {
  key: string;
  label: string;
}

export interface SuperAdminPermissionResult {
  account: {
    id: string;
    profile_name: string;
    job_title: string | null;
    role: string;
  };
  catalog: AdminPermission[];
  permissions: string[];
}

export interface CreateSuperAdminPayload {
  profile_name: string;
  job_title: string;
  phone: string;
  email: string;
  password: string;
  password_confirmation: string;
  reason: string;
}

export interface RunnerLicenseApplication {
  id: string;
  license_type: string | null;
  license_class: string;
  expires_at: string;
  status: string;
  submitted_at: string | null;
  mime_type: string | null;
  size_bytes: number | null;
}

export interface RunnerApplication {
  id: string;
  profile_name: string;
  phone: string | null;
  email: string | null;
  role: string;
  status: string;
  runner_status: string;
  created_at: string;
  updated_at: string;
  runner_license_application: RunnerLicenseApplication | null;
}

export interface RunnerLicenseVerificationDetail {
  id: string;
  account_id: string;
  profile_name: string;
  license_number_masked: string | null;
  license_type: string | null;
  license_class: string;
  expires_at: string | null;
  status: string;
  is_current: boolean;
  mime_type: string | null;
  size_bytes: number | null;
  submitted_at: string | null;
  reviewed_at: string | null;
  rejection_reason?: string | null;
}

export interface RunnerDirectoryItem {
  id: string;
  profile_name: string;
  phone: string | null;
  email: string | null;
  role: string;
  status: string;
  runner_status: 'APPROVED' | 'SUSPENDED' | string;
  created_at: string;
  updated_at: string;
  current_license: {
    license_type: string | null;
    license_class: string;
    expires_at: string | null;
    status: string;
  } | null;
}

export interface RunnerDirectoryResult {
  runners: RunnerDirectoryItem[];
  pagination: AccountPagination;
}

export interface IdentityVerificationQueueItem {
  account_id: string;
  profile_name: string;
  phone: string | null;
  email: string | null;
  verified_name: string;
  mykad_number_masked: string;
  status: 'PENDING' | 'VERIFIED' | 'REJECTED' | string;
  submitted_at: string | null;
  reviewed_at: string | null;
  documents_count: number;
}

export interface IdentityVerificationQueueResult {
  verifications: IdentityVerificationQueueItem[];
  pagination: AccountPagination;
}

export interface IdentityVerificationDocument {
  id: string;
  mime_type: string;
  size_bytes: number;
  created_at: string | null;
}

export interface IdentityVerificationDetail {
  account_id: string;
  profile_name: string;
  verified_name: string;
  status: 'PENDING' | 'VERIFIED' | 'REJECTED' | string;
  mykad_number_masked: string;
  submitted_at: string | null;
  reviewed_at: string | null;
  rejection_reason: string | null;
  documents: IdentityVerificationDocument[];
}

export interface AuditLog {
  id: string;
  actor_account_id: string;
  target_account_id: string | null;
  action: string;
  reason: string | null;
  created_at: string;
}

export interface AdminProfile {
  id: string;
  profile_name: string;
  verified_name: string | null;
  phone: string | null;
  email: string | null;
  profile_picture: string | null;
  role: string;
  status: string;
  permissions: string[];
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
}
