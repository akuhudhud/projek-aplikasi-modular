<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseVerificationDecisionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $type = DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'GENERAL',
            'name' => 'Lesen Am',
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
            'driving_license_type_id' => $type->id,
            'code' => 'D',
            'name' => 'Lesen D',
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

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
        string $expiresAt
    ): RunnerLicenseVerification {
        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number' => 'D123456789012',
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'expires_at' => $expiresAt,
            'status' => $status,
            'is_current' => false,
            'submitted_at' => now(),
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

    public function test_expired_license_cannot_be_verified_by_admin(): void
    {
        $admin = $this->createAccount(
            'Admin Semakan Lesen',
            'SUPER_ADMIN'
        );

        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount('Pemohon Lesen Tamat');

        $verification = $this->createLicense(
            $applicant,
            RunnerLicenseVerification::STATUS_PENDING,
            now()->subDay()->toDateString()
        );

        $this->asAccount($admin, 'expired-license-decision-token')
            ->postJson(
                '/api/admin/runner-license-verifications/'
                    .$applicant->id.'/decision',
                ['decision' => 'VERIFIED']
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verification->id,
            'status' => RunnerLicenseVerification::STATUS_PENDING,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_VERIFIED',
        ]);
    }

    public function test_admin_cannot_change_a_license_decision_after_review(): void
    {
        $admin = $this->createAccount(
            'Admin Semakan Berulang',
            'SUPER_ADMIN'
        );

        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount('Pemohon Sudah Disahkan');

        $verification = $this->createLicense(
            $applicant,
            RunnerLicenseVerification::STATUS_VERIFIED,
            now()->addYear()->toDateString()
        );

        $this->asAccount($admin, 'reviewed-license-decision-token')
            ->postJson(
                '/api/admin/runner-license-verifications/'
                    .$applicant->id.'/decision',
                [
                    'decision' => 'REJECTED',
                    'rejection_reason' => 'Percubaan mengubah keputusan.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verification->id,
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_REJECTED',
        ]);
    }

    public function test_admin_without_review_permission_cannot_change_license_decision(): void
    {
        $admin = $this->createAccount(
            'Admin Tanpa Permission',
            'SUPER_ADMIN'
        );

        $applicant = $this->createAccount('Pemohon Lesen');

        $verification = $this->createLicense(
            $applicant,
            RunnerLicenseVerification::STATUS_PENDING,
            now()->addYear()->toDateString()
        );

        $this->asAccount(
            $admin,
            'license-decision-denied-token'
        )->postJson(
            '/api/admin/runner-license-verifications/'
                .$verification->id.'/decision',
            ['decision' => 'VERIFIED']
        )->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verification->id,
            'status' => RunnerLicenseVerification::STATUS_PENDING,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_VERIFIED',
        ]);
    }

    public function test_root_can_list_license_applications_without_assigned_permission(): void
    {
        $root = $this->createAccount(
            'Root Owner',
            'ROOT_SUPER_ADMIN'
        );

        $applicant = $this->createAccount('Pemohon Lesen');

        $this->createLicense(
            $applicant,
            RunnerLicenseVerification::STATUS_PENDING,
            now()->addYear()->toDateString()
        );

        $this->asAccount($root, 'root-license-list-token')
            ->getJson('/api/admin/runner-license-verifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.applications.0.account_id',
                $applicant->id
            );
    }
}
