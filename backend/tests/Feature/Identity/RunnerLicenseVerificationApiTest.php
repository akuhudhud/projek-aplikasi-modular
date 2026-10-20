<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\DrivingLicenseCatalog;
use App\Models\IdentityVerification;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role = 'USER',
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner License API Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function verifyIdentity(Account $account): void
    {
        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Contoh Nama Sebenar',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);
    }

    private function createPendingLicense(
        Account $account,
        string $licenseNumber = 'D123456789012'
    ): RunnerLicenseVerification {
        Storage::disk('local')->put(
            'runner-license-documents/test-license.pdf',
            '%PDF-test-content'
        );

        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number_encrypted' => Crypt::encryptString(
                $licenseNumber
            ),
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => 'runner-license-documents/test-license.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('%PDF-test-content'),
        ]);
    }

    private function grantReviewPermission(Account $account): void
    {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'runner_license_verifications.review',
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

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
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

        $this->asAccount($account, 'license-status-token')
            ->getJson('/api/me/runner-license-verification')
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

    public function test_user_can_submit_license_after_identity_is_verified(): void
    {
        $account = $this->createAccount();
        $this->verifyIdentity($account);

        $response = $this->asAccount(
            $account,
            'license-submit-token'
        )->post('/api/me/runner-license-verification', [
            'license_number' => 'D123456789012',
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->image('license.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->assertJsonMissing([
                'license_number' => 'D123456789012',
            ]);

        $verification = RunnerLicenseVerification::query()
            ->where('account_id', $account->id)
            ->firstOrFail();

        $this->assertNotSame(
            'D123456789012',
            $verification->license_number_encrypted
        );

        $this->assertSame(
            'D123456789012',
            $verification->decryptLicenseNumber()
        );

        $this->assertSame(
            RunnerLicenseVerification::STATUS_PENDING,
            $verification->status
        );

        Storage::disk('local')->assertExists(
            $verification->document_path
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $account->id,
            'target_account_id' => $account->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_SUBMITTED',
        ]);
    }

    public function test_user_cannot_submit_license_before_identity_is_verified(): void
    {
        $account = $this->createAccount();

        $this->asAccount(
            $account,
            'license-identity-required-token'
        )->post('/api/me/runner-license-verification', [
            'license_number' => 'D123456789012',
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->image('license.jpg'),
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('runner_license_verifications', [
            'account_id' => $account->id,
        ]);
    }

    public function test_admin_with_permission_can_review_license_and_download_document(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();
        $verification = $this->createPendingLicense($applicant);

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

        $this->asAccount(
            $admin,
            'license-decision-token'
        )->postJson(
            '/api/admin/runner-license-verifications/'
                .$applicant->id.'/decision',
            ['decision' => 'VERIFIED']
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                RunnerLicenseVerification::STATUS_VERIFIED
            );

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verification->id,
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'reviewer_account_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_VERIFIED',
        ]);

        $this->asAccount(
            $admin,
            'license-document-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id.'/document'
        )->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_super_admin_without_review_permission_cannot_review_license(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $applicant = $this->createAccount();

        $this->createPendingLicense($applicant);

        $this->asAccount(
            $admin,
            'license-review-denied-token'
        )->getJson(
            '/api/admin/runner-license-verifications/'.$applicant->id
        )->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_rejected_license_can_be_resubmitted_without_overwriting_history(): void
    {
        $account = $this->createAccount();
        $this->verifyIdentity($account);

        $original = $this->createPendingLicense($account);

        $original->status = RunnerLicenseVerification::STATUS_REJECTED;
        $original->reviewed_at = now();
        $original->rejection_reason = 'Dokumen tidak jelas.';
        $original->save();

        $originalId = $original->id;
        $originalPath = $original->document_path;
        $originalLicenseNumber = $original->decryptLicenseNumber();
        $originalSubmittedAt = $original->submitted_at;
        $originalReviewedAt = $original->reviewed_at;
        $originalRejectionReason = $original->rejection_reason;

        $this->asAccount(
            $account,
            'license-resubmit-token'
        )->post('/api/me/runner-license-verification', [
            'license_number' => 'D987654321098',
            'license_class' => 'D',
            'expires_at' => now()->addYears(2)->toDateString(),
            'document' => UploadedFile::fake()->image('new-license.jpg'),
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->assertJsonPath(
                'data.runner_license_verification.is_current',
                false
            );

        $original->refresh();

        $this->assertSame(
            RunnerLicenseVerification::STATUS_REJECTED,
            $original->status
        );

        $this->assertSame($originalId, $original->id);

        $this->assertSame(
            $originalLicenseNumber,
            $original->decryptLicenseNumber()
        );

        $this->assertSame(
            $originalPath,
            $original->document_path
        );

        $this->assertTrue(
            $original->submitted_at->equalTo($originalSubmittedAt)
        );

        $this->assertTrue(
            $original->reviewed_at->equalTo($originalReviewedAt)
        );

        $this->assertSame(
            $originalRejectionReason,
            $original->rejection_reason
        );

        Storage::disk('local')->assertExists($originalPath);

        $this->assertSame(
            2,
            RunnerLicenseVerification::query()
                ->where('account_id', $account->id)
                ->count()
        );

        $renewal = RunnerLicenseVerification::query()
            ->where('account_id', $account->id)
            ->where('id', '!=', $originalId)
            ->firstOrFail();

        $this->assertNotSame($originalId, $renewal->id);

        $this->assertSame(
            RunnerLicenseVerification::STATUS_PENDING,
            $renewal->status
        );

        $this->assertFalse($renewal->is_current);

        $this->assertSame(
            'D987654321098',
            $renewal->decryptLicenseNumber()
        );

        $this->assertNull($renewal->reviewed_at);
        $this->assertNull($renewal->rejection_reason);

        Storage::disk('local')->assertExists(
            $renewal->document_path
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $account->id,
            'target_account_id' => $account->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_SUBMITTED',
        ]);
    }
}
