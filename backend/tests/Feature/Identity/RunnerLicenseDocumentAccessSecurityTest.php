<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
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
            'name' => 'Ujian Akses Dokumen',
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

    public function test_unauthenticated_user_cannot_download_license_document(): void
    {
        $applicant = $this->createAccount();
        $this->createPendingLicense($applicant);

        $this->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id.'/document'
        )->assertUnauthorized();
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
                'private, no-store'
            )
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );
    }
}
