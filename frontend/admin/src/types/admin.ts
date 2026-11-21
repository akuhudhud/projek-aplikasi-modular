
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
  role: string;
  status: string;
  created_at: string;
  updated_at: string;
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
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
}
