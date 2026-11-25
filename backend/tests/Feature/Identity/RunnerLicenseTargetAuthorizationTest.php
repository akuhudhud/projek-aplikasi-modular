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

class RunnerLicenseTargetAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const DOCUMENT_CONTENT = '%PDF-runner-license-authorization-test';

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
            'profile_name' => 'Ujian Autorisasi Lesen',
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

    public function test_super_admin_cannot_review_own_license_using_account_id(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);
        $this->createPendingLicense($admin);

        $this->authenticate(
            $admin,
            'self-account-id-token'
        )->get(
            '/api/admin/runner-license-verifications/'.$admin->id
        )->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_super_admin_cannot_bypass_self_review_restriction_using_verification_id(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $verification = $this->createPendingLicense($admin);

        $this->authenticate(
            $admin,
            'self-verification-id-token'
        )->get(
            '/api/admin/runner-license-verifications/'.$verification->id
        )->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_super_admin_cannot_view_own_license_document(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);
        $this->createPendingLicense($admin);

        $this->authenticate(
            $admin,
            'self-document-account-id-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$admin->id.'/document'
        )->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_super_admin_cannot_view_another_super_admin_license(): void
    {
        $reviewer = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($reviewer);

        $targetAdmin = $this->createAccount('SUPER_ADMIN');
        $this->createPendingLicense($targetAdmin);

        $this->authenticate(
            $reviewer,
            'other-super-admin-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$targetAdmin->id
        )->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_super_admin_cannot_view_another_super_admin_license_document(): void
    {
        $reviewer = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($reviewer);

        $targetAdmin = $this->createAccount('SUPER_ADMIN');
        $this->createPendingLicense($targetAdmin);

        $this->authenticate(
            $reviewer,
            'other-super-admin-document-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$targetAdmin->id.'/document'
        )->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_root_super_admin_can_review_user_license_without_explicit_permission(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount();

        $this->createPendingLicense($applicant);

        $this->authenticate(
            $root,
            'root-review-user-license-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id
        )->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_root_super_admin_can_view_user_license_document_without_explicit_permission(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount();

        $this->createPendingLicense($applicant);

        $this->authenticate(
            $root,
            'root-view-user-license-document-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id.'/document'
        )->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/pdf'
            )
            ->assertHeader(
                'Content-Disposition',
                'inline'
            );
    }

    public function test_super_admin_with_permission_can_review_user_license(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();
        $this->createPendingLicense($applicant);

        $this->authenticate(
            $admin,
            'review-user-license-token'
        )->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id
        )->assertOk()
            ->assertJsonPath('success', true);
    }
}
