<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseVerificationListApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $name,
        string $role = 'USER'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function createLicense(
        Account $account,
        string $status,
        int $submittedMinutesAgo = 0
    ): RunnerLicenseVerification {
        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number_encrypted' => Crypt::encryptString(
                'D123456789012'
            ),
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => $status,
            'submitted_at' => now()->subMinutes(
                $submittedMinutesAgo
            ),
            'reviewed_at' => $status === RunnerLicenseVerification::STATUS_PENDING
                ? null
                : now(),
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => 'runner-license-documents/'
                .Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
    }

    private function grantReviewPermission(Account $account): void
    {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'runner_license_verifications.review',
        ]);
    }

    private function asAccount(
        Account $account,
        string $token
    ): static {
        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    public function test_admin_list_defaults_to_pending_and_paginates_without_sensitive_data(): void
    {
        $admin = $this->createAccount(
            'Admin Senarai Lesen',
            'SUPER_ADMIN'
        );

        $this->grantReviewPermission($admin);

        $firstApplicant = $this->createAccount('Pemohon Pertama');
        $secondApplicant = $this->createAccount('Pemohon Kedua');
        $verifiedApplicant = $this->createAccount('Pemohon Disahkan');

        $this->createLicense(
            $firstApplicant,
            RunnerLicenseVerification::STATUS_PENDING,
            1
        );

        $this->createLicense(
            $secondApplicant,
            RunnerLicenseVerification::STATUS_PENDING,
            2
        );

        $this->createLicense(
            $verifiedApplicant,
            RunnerLicenseVerification::STATUS_VERIFIED,
            3
        );

        $this->asAccount($admin, 'license-list-pending-token')
            ->getJson('/api/admin/runner-license-verifications?per_page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath(
                'data.applications.0.status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->assertJsonMissingPath(
                'data.applications.0.license_number'
            )
            ->assertJsonMissingPath(
                'data.applications.0.document_path'
            );
    }

    public function test_admin_can_filter_license_applications_by_status(): void
    {
        $admin = $this->createAccount(
            'Admin Penapis Lesen',
            'SUPER_ADMIN'
        );

        $this->grantReviewPermission($admin);

        $pendingApplicant = $this->createAccount('Pemohon Pending');
        $verifiedApplicant = $this->createAccount('Pemohon Verified');
        $rejectedApplicant = $this->createAccount('Pemohon Rejected');

        $this->createLicense(
            $pendingApplicant,
            RunnerLicenseVerification::STATUS_PENDING
        );

        $this->createLicense(
            $verifiedApplicant,
            RunnerLicenseVerification::STATUS_VERIFIED
        );

        $this->createLicense(
            $rejectedApplicant,
            RunnerLicenseVerification::STATUS_REJECTED
        );

        $this->asAccount($admin, 'license-list-verified-token')
            ->getJson(
                '/api/admin/runner-license-verifications?status=VERIFIED'
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.applications.0.account_id',
                $verifiedApplicant->id
            )
            ->assertJsonPath(
                'data.applications.0.status',
                RunnerLicenseVerification::STATUS_VERIFIED
            );
    }

    public function test_admin_license_list_rejects_invalid_filters(): void
    {
        $admin = $this->createAccount(
            'Admin Filter Tidak Sah',
            'SUPER_ADMIN'
        );

        $this->grantReviewPermission($admin);

        $this->asAccount($admin, 'license-list-invalid-token')
            ->getJson(
                '/api/admin/runner-license-verifications?status=INVALID'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_admin_without_review_permission_cannot_list_license_applications(): void
    {
        $admin = $this->createAccount(
            'Admin Tanpa Permission',
            'SUPER_ADMIN'
        );

        $this->asAccount($admin, 'license-list-denied-token')
            ->getJson('/api/admin/runner-license-verifications')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
