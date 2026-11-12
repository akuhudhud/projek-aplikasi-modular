<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseDocumentAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const DOCUMENT_CONTENT = '%PDF-runner-license-security-test';

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

        Storage::fake('local');
    }

    private function createAccount(string $role = 'USER'): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Ujian Akses Dokumen',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function grantReviewPermission(Account $account): void
    {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'runner_license_verifications.review',
        ]);
    }

    private function createPendingLicense(
        Account $applicant
    ): RunnerLicenseVerification {
        $path = 'runner-license-documents/'
            .Str::uuid().'.pdf';

        Storage::disk('local')->put(
            $path,
            self::DOCUMENT_CONTENT
        );

        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $applicant->id,
            'license_number' => 'D123456789012',
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen(self::DOCUMENT_CONTENT),
        ]);
    }

    private function authenticate(
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

    private function assertNoAuditLog(
        string $action,
        Account $target
    ): void {
        $this->assertDatabaseMissing('audit_logs', [
            'action' => $action,
            'target_account_id' => $target->id,
        ]);
    }

    private function assertAuditLogRecorded(
        string $action,
        Account $actor,
        Account $target,
        string $expectedReason
    ): void {
        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => $action,
            'reason' => $expectedReason,
        ]);

        $auditLog = AuditLog::query()
            ->where('actor_account_id', $actor->id)
            ->where('target_account_id', $target->id)
            ->where('action', $action)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($auditLog);

        $this->assertStringNotContainsString(
            'D123456789012',
            $auditLog->reason
        );

        $this->assertStringNotContainsString(
            self::DOCUMENT_CONTENT,
            $auditLog->reason
        );
    }

    public function test_unauthenticated_user_cannot_download_license_document(): void
    {
        $applicant = $this->createAccount();
        $this->createPendingLicense($applicant);

        $this->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id.'/document'
        )->assertUnauthorized();

        $this->assertNoAuditLog(
            'RUNNER_LICENSE_DOCUMENT_DOWNLOADED',
            $applicant
        );
    }

    public function test_admin_without_review_permission_cannot_download_license_document(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $applicant = $this->createAccount();

        $this->createPendingLicense($applicant);

        $this->authenticate(
            $admin,
            'license-document-denied-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id.'/document'
        )->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertNoAuditLog(
            'RUNNER_LICENSE_DOCUMENT_DOWNLOADED',
            $applicant
        );
    }

    public function test_reviewer_cannot_download_another_accounts_license_document(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $owner = $this->createAccount();
        $otherAccount = $this->createAccount();

        $this->createPendingLicense($owner);

        $this->authenticate(
            $admin,
            'license-document-cross-account-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$otherAccount->id.'/document'
        )->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Dokumen lesen tidak ditemui.'
            );

        $this->assertNoAuditLog(
            'RUNNER_LICENSE_DOCUMENT_DOWNLOADED',
            $owner
        );
    }

    public function test_reviewer_cannot_download_license_document_when_storage_file_is_missing(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();
        $verification = $this->createPendingLicense($applicant);

        Storage::disk('local')->delete(
            $verification->document_path
        );

        $this->authenticate(
            $admin,
            'license-document-missing-file-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id.'/document'
        )->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Dokumen lesen tidak ditemui.'
            );

        $this->assertNoAuditLog(
            'RUNNER_LICENSE_DOCUMENT_DOWNLOADED',
            $applicant
        );
    }

    public function test_authorized_reviewer_can_download_plaintext_license_document_with_security_headers(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();
        $verification = $this->createPendingLicense($applicant);

        $storedContents = Storage::disk('local')->get(
            $verification->document_path
        );

        $this->assertSame(
            self::DOCUMENT_CONTENT,
            $storedContents
        );

        $this->authenticate(
            $admin,
            'license-document-authorized-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id.'/document'
        )->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/pdf'
            )
            ->assertHeader(
                'Cache-Control',
                'no-store, private'
            )
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $this->assertAuditLogRecorded(
            'RUNNER_LICENSE_DOCUMENT_DOWNLOADED',
            $admin,
            $applicant,
            'Runner license document downloaded by authorised reviewer.'
        );
    }

    public function test_unauthenticated_user_cannot_view_license_verification_record(): void
    {
        $applicant = $this->createAccount();
        $this->createPendingLicense($applicant);

        $this->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id
        )->assertUnauthorized();

        $this->assertNoAuditLog(
            'RUNNER_LICENSE_VERIFICATION_VIEWED',
            $applicant
        );
    }

    public function test_admin_without_review_permission_cannot_view_license_verification_record(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $applicant = $this->createAccount();

        $this->createPendingLicense($applicant);

        $this->authenticate(
            $admin,
            'license-view-denied-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id
        )->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertNoAuditLog(
            'RUNNER_LICENSE_VERIFICATION_VIEWED',
            $applicant
        );
    }

    public function test_reviewer_cannot_view_another_accounts_license_verification_record(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $owner = $this->createAccount();
        $otherAccount = $this->createAccount();

        $this->createPendingLicense($owner);

        $this->authenticate(
            $admin,
            'license-view-cross-account-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$otherAccount->id
        )->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertNoAuditLog(
            'RUNNER_LICENSE_VERIFICATION_VIEWED',
            $owner
        );
    }

    public function test_authorized_reviewer_can_view_masked_license_verification_record_and_access_is_audited(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();
        $this->createPendingLicense($applicant);

        $this->authenticate(
            $admin,
            'license-view-authorized-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.license_number_masked',
                '*********9012'
            )
            ->assertJsonMissing([
                'license_number' => 'D123456789012',
            ]);

        $this->assertAuditLogRecorded(
            'RUNNER_LICENSE_VERIFICATION_VIEWED',
            $admin,
            $applicant,
            'Runner license verification record viewed by authorised reviewer.'
        );
    }
}
