<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use App\Models\IdentityVerification;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_DOCUMENT_CONTENT = '%PDF-test-content';

    private function createAccount(
        string $role = 'USER',
        string $status = 'ACTIVE',
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner License API Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);

        $account->runner_status = $runnerStatus;
        $account->save();

        return $account->fresh();
    }

    private function createPendingLicense(
        Account $account,
        string $licenseNumber = 'D123456789012'
    ): RunnerLicenseVerification {
        $path = 'runner-license-documents/'
            .Str::uuid()
            .'.pdf';

        Storage::disk('local')->put(
            $path,
            self::TEST_DOCUMENT_CONTENT
        );

        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number' => $licenseNumber,
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen(self::TEST_DOCUMENT_CONTENT),
        ]);
    }

    private function grantReviewPermission(Account $account): void
    {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'runner_license_verifications.review',
        ]);
    }

    private function verifyIdentity(Account $account): void
    {
        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Contoh Nama Sebenar',
            'mykad_number' => '900101145678',
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);
    }

    private function authenticate(Account $account, string $token): void
    {
        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function asAccount(Account $account, string $token): static
    {
        $this->authenticate($account, $token);

        return $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function assertAuditLogContainsNoSensitiveData(
        string $action,
        Account $actor,
        Account $target
    ): void {
        $auditLog = AuditLog::query()
            ->where('actor_account_id', $actor->id)
            ->where('target_account_id', $target->id)
            ->where('action', $action)
            ->latest('created_at')
            ->first();

        $this->assertNotNull(
            $auditLog,
            'Expected audit log was not created.'
        );

        $this->assertStringNotContainsString(
            'D123456789012',
            $auditLog->reason
        );

        $this->assertStringNotContainsString(
            self::TEST_DOCUMENT_CONTENT,
            $auditLog->reason
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

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

    public function test_status_endpoint_reports_not_submitted_without_exposing_license_number(): void
    {
        $account = $this->createAccount();

        $this->asAccount(
            $account,
            'license-status-token'
        )->getJson('/api/me/runner-license-verification')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                'NOT_SUBMITTED'
            )
            ->assertJsonPath(
                'data.runner_license_verification.license_class',
                null
            )
            ->assertJsonMissingPath(
                'data.runner_license_verification.license_number'
            );
    }

    public function test_authorised_admin_can_view_license_record_and_download_document(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->verifyIdentity($applicant);

        $verification = $this->createPendingLicense($applicant);

        // Admin can view the license record for the Runner application.
        $this->asAccount(
            $admin,
            'license-review-token'
        )->getJson(
            '/api/admin/runner-license-verifications/'.$applicant->id
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->assertJsonPath(
                'data.runner_license_verification.license_number_masked',
                '*********9012'
            )
            ->assertJsonMissing([
                'license_number' => 'D123456789012',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_VIEWED',
            'reason' => 'Runner license verification record viewed by authorised reviewer.',
        ]);

        $this->assertAuditLogContainsNoSensitiveData(
            'RUNNER_LICENSE_VERIFICATION_VIEWED',
            $admin,
            $applicant
        );

        // Admin can download the license document for review.
        $this->asAccount(
            $admin,
            'license-document-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$verification->id.'/document'
        )->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader(
                'Cache-Control',
                'no-store, private'
            );

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_LICENSE_DOCUMENT_DOWNLOADED',
            'reason' => 'Runner license document downloaded by authorised reviewer.',
        ]);

        $this->assertAuditLogContainsNoSensitiveData(
            'RUNNER_LICENSE_DOCUMENT_DOWNLOADED',
            $admin,
            $applicant
        );

        Storage::disk('local')->assertExists(
            $verification->document_path
        );
    }

    public function test_admin_without_review_permission_cannot_view_license_record(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $verification = $this->createPendingLicense($applicant);

        $this->asAccount(
            $admin,
            'license-review-denied-token'
        )->getJson(
            '/api/admin/runner-license-verifications/'.$applicant->id
        )->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_VIEWED',
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verification->id,
            'account_id' => $applicant->id,
            'status' => RunnerLicenseVerification::STATUS_PENDING,
        ]);
    }

    public function test_admin_without_review_permission_cannot_download_license_document(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $verification = $this->createPendingLicense($applicant);

        $this->asAccount(
            $admin,
            'license-document-denied-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$verification->id.'/document'
        )->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_LICENSE_DOCUMENT_DOWNLOADED',
        ]);
    }
}
