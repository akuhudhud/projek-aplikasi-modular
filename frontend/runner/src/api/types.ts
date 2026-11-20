
export type RunnerStatus =
    | 'NOT_RUNNER'
    | 'PENDING'
    | 'APPROVED'
    | 'REJECTED'
    | 'SUSPENDED';

export type IdentityVerificationStatus =
    | 'NOT_SUBMITTED'
    | 'PENDING'
    | 'VERIFIED'
    | 'REJECTED';

export interface RunnerAccount {
    id: number | string;
    profile_name: string;
    phone: string | null;
    email: string | null;
    phone_verified_at?: string | null;
    email_verified_at?: string | null;
    profile_picture: string | null;
    role: string;
    status: string;
    runner_status: RunnerStatus;
    identity_verification_status: IdentityVerificationStatus;
}

export interface RunnerPagination {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export interface RunnerTask {
    task_id: string;
    module_key: string;
    task_type: string;
    source_type: string;
    source_id: string;
    assigned_account_id: number | string | null;
    generic_status: string;
    assigned_at: string | null;
    started_at: string | null;
    arrived_at: string | null;
    arrival_latitude: string | number | null;
    arrival_longitude: string | number | null;
    completed_at: string | null;
    completion_proof_path: string | null;
    cancelled_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface RunnerMarketplaceLocation {
    address: string;
    contact_name?: string | null;
    phone?: string | null;
}

export interface RunnerMarketplaceRequest {
    request_id: string;
    source_type: string;
    task_class: 'PICKUP_DELIVERY' | 'NON_DELIVERY';
    description: string;
    pickup_location_snapshot: RunnerMarketplaceLocation | null;
    delivery_location_snapshot: RunnerMarketplaceLocation | null;
    task_location_snapshot: RunnerMarketplaceLocation | null;
    status: string;
    expires_at: string | null;
    created_at: string;
    updated_at?: string;
}

export interface RunnerQuotation {
    quotation_id: string;
    amount: number;
    currency: string;
    message: string | null;
    status: string;
    expires_at: string | null;
    created_at: string;
}

export interface RunnerMarketplaceRequestDetail
    extends RunnerMarketplaceRequest {
    my_quotations?: RunnerQuotation[];
}

export interface RunnerPublicProfile {
    profile_id?: string;
    account_id?: number | string;
    public_slug: string;
    profile_name: string;
    bio: string | null;
    base_location: string | null;
    service_area: string | null;
    services: string[];
    is_published: boolean;
    views_count: number;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface RunnerProfileAnalytics {
    profile_views: number;
    requests_from_profile: number;
    quotations_for_profile_requests: number;
    completed_tasks_from_profile_requests: number;
}

export interface RunnerNotification {
    id: string;
    type: string;
    data: {
        event?: string;
        title?: string;
        message?: string;
        request_id?: string | null;
        quotation_id?: string | null;
        task_id?: string | null;
        [key: string]: unknown;
    };
    read_at: string | null;
    created_at: string;
}
