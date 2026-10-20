<?php

namespace Tests\Feature\Core;

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

class RunnerApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(
            \Database\Seeders\DrivingLicenseCatalogSeeder::class
        );
    }

    private function createAccount(
        string $role = 'USER',
        string $status = 'ACTIVE',
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner API Test',
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

    private function verifyIdentityOnly(Account $account): void
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

    private function createLicense(
        Account $account,
        string $status = RunnerLicenseVerification::STATUS_PENDING,
        ?string $expiresAt = null
    ): RunnerLicenseVerification {
        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number_encrypted' => Crypt::encryptString(
                'D123456789012'
            ),
            'license_type' => RunnerLicenseVerification::TYPE_GENERAL,
            'license_class' => 'D',
            'expires_at' => $expiresAt
                ?? now()->addYear()->toDateString(),
            'status' => $status,
            'is_current' => $status
                === RunnerLicenseVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => $status
                === RunnerLicenseVerification::STATUS_PENDING
                    ? null
                    : now(),
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => 'runner-license-documents/test-'
                .Str::uuid()
                .'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
    }

    private function verifyIdentity(Account $account): void
    {
        $this->verifyIdentityOnly($account);

        $this->createLicense(
            $account,
            RunnerLicenseVerification::STATUS_VERIFIED
        );
    }

    private function createPendingApplication(): Account
    {
        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->verifyIdentityOnly($applicant);

        $this->createLicense($applicant);

        return $applicant;
    }

    private function grantPermissions(
        Account $account,
        array $permissions
    ): void {
        foreach (array_unique($permissions) as $permission) {
            AccountPermission::create([
                'account_id' => $account->id,
                'permission_key' => $permission,
            ]);
        }
    }

    private function authenticate(
        Account $account,
        string $token
    ): void {
        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function asAccount(
        Account $account,
        string $token
    ): static {
        $this->authenticate($account, $token);

        return $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function validApplicationPayload(): array
    {
        return [
            'license_number' => 'D123456789012',
            'license_type' => RunnerLicenseVerification::TYPE_GENERAL,
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->create(
                'lesen.pdf',
                100,
                'application/pdf'
            ),
        ];
    }

    public function test_runner_application_status_requires_authentication(): void
    {
        $this->getJson('/api/me/runner-application')
            ->assertUnauthorized();
    }

    public function test_user_can_submit_runner_application_through_api(): void
    {
        $user = $this->createAccount();
        $this->verifyIdentityOnly($user);

        $this->asAccount($user, 'runner-apply-token')
            ->post(
                '/api/me/runner-application',
                $this->validApplicationPayload()
            )
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_application.runner_status',
                Account::RUNNER_STATUS_PENDING
            )
            ->assertJsonPath(
                'data.runner_license_verification.status',
                RunnerLicenseVerification::STATUS_PENDING
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'account_id' => $user->id,
            'license_class' => 'D',
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
        ]);

        $this->assertSame(
            1,
            \DB::table('audit_logs')
                ->where('actor_account_id', $user->id)
                ->where('target_account_id', $user->id)
                ->where('action', 'RUNNER_APPLICATION_SUBMITTED')
                ->count(),
            'Satu permohonan berjaya mesti menghasilkan tepat satu audit submission.'
        );
    }

    public function test_verified_identity_without_license_fields_cannot_submit_runner_application_or_use_separate_initial_license_endpoint(): void
    {
        $user = $this->createAccount();
        $this->verifyIdentityOnly($user);

        $this->asAccount($user, 'runner-license-required-token')
            ->postJson('/api/me/runner-application')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'license_number',
                'license_class',
                'expires_at',
                'document',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'runner_status' => Account::RUNNER_STATUS_NOT_RUNNER,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $user->id,
            'action' => 'RUNNER_APPLICATION_SUBMITTED',
        ]);

        $this->assertDatabaseMissing('runner_license_verifications', [
            'account_id' => $user->id,
        ]);

        $this->asAccount($user, 'runner-license-separate-token')
            ->postJson('/api/me/runner-license-verification')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_user_without_verified_identity_is_directed_to_identity_verification(): void
    {
        $user = $this->createAccount();

        $this->asAccount($user, 'runner-identity-required-token')
            ->postJson('/api/me/runner-application')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Sila sahkan identiti dahulu sebelum memohon menjadi Runner.'
            )
            ->assertJsonPath(
                'data.next_action.type',
                'IDENTITY_VERIFICATION'
            )
            ->assertJsonPath(
                'data.next_action.method',
                'GET'
            )
            ->assertJsonPath(
                'data.next_action.endpoint',
                '/api/me/identity-verification'
            )
            ->assertJsonPath(
                'data.next_action.submit_method',
                'POST'
            )
            ->assertJsonPath(
                'data.next_action.submit_endpoint',
                '/api/me/identity-verification'
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'runner_status' => Account::RUNNER_STATUS_NOT_RUNNER,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $user->id,
            'action' => 'RUNNER_APPLICATION_SUBMITTED',
        ]);
    }

    public function test_pending_identity_verification_does_not_allow_runner_application(): void
    {
        $user = $this->createAccount();

        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $user->id,
            'real_name' => 'Contoh Nama Sebenar',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->asAccount($user, 'runner-identity-pending-token')
            ->postJson('/api/me/runner-application')
            ->assertUnprocessable()
            ->assertJsonPath(
                'data.next_action.endpoint',
                '/api/me/identity-verification'
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'runner_status' => Account::RUNNER_STATUS_NOT_RUNNER,
        ]);
    }

    public function test_rejected_identity_verification_does_not_allow_runner_application(): void
    {
        $user = $this->createAccount();

        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $user->id,
            'real_name' => 'Contoh Nama Sebenar',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_REJECTED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'rejection_reason' => 'Dokumen tidak jelas.',
        ]);

        $this->asAccount($user, 'runner-identity-rejected-token')
            ->postJson('/api/me/runner-application')
            ->assertUnprocessable();

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'runner_status' => Account::RUNNER_STATUS_NOT_RUNNER,
        ]);
    }

    public function test_duplicate_runner_application_returns_unprocessable(): void
    {
        $user = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->asAccount($user, 'runner-duplicate-token')
            ->postJson('/api/me/runner-application')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_user_can_view_own_runner_application_status(): void
    {
        $user = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->asAccount($user, 'runner-status-token')
            ->getJson('/api/me/runner-application')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_application.account_id',
                $user->id
            )
            ->assertJsonPath(
                'data.runner_application.runner_status',
                Account::RUNNER_STATUS_PENDING
            );
    }

    public function test_admin_can_list_pending_runner_applications_with_permission(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermissions($admin, [
            'runner_applications.view',
        ]);

        $applicant = $this->createPendingApplication();

        $this->asAccount($admin, 'runner-list-token')
            ->getJson('/api/admin/runner-applications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'id' => $applicant->id,
                'runner_status' => Account::RUNNER_STATUS_PENDING,
            ])
            ->assertJsonFragment([
                'license_class' => 'D',
                'status' => RunnerLicenseVerification::STATUS_PENDING,
            ]);
    }

    public function test_super_admin_without_view_permission_cannot_list_runner_applications(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->asAccount($admin, 'runner-list-no-permission-token')
            ->getJson('/api/admin/runner-applications')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_regular_user_cannot_list_runner_applications(): void
    {
        $user = $this->createAccount();

        $this->asAccount($user, 'runner-list-denied-token')
            ->getJson('/api/admin/runner-applications')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_approve_runner_application_with_permission_and_reason(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermissions($admin, [
            'runner_applications.approve',
        ]);

        $applicant = $this->createPendingApplication();

        $this->asAccount($admin, 'runner-approve-token')
            ->postJson(
                '/api/admin/runner-applications/'.$applicant->id.'/approve',
                ['reason' => 'Application reviewed and approved.']
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_APPROVED
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'account_id' => $applicant->id,
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_APPLICATION_APPROVED',
            'reason' => 'Application reviewed and approved.',
        ]);
    }

    public function test_admin_decision_requires_reason(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermissions($admin, [
            'runner_applications.approve',
        ]);

        $applicant = $this->createPendingApplication();

        $this->asAccount($admin, 'runner-no-reason-token')
            ->postJson(
                '/api/admin/runner-applications/'.$applicant->id.'/approve'
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'account_id' => $applicant->id,
            'status' => RunnerLicenseVerification::STATUS_PENDING,
        ]);
    }

    public function test_super_admin_cannot_approve_another_super_admin(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermissions($admin, [
            'runner_applications.approve',
        ]);

        $applicant = $this->createAccount(
            'SUPER_ADMIN',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->asAccount($admin, 'runner-hierarchy-token')
            ->postJson(
                '/api/admin/runner-applications/'.$applicant->id.'/approve',
                ['reason' => 'Attempted approval.']
            )
            ->assertForbidden();

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);
    }

    public function test_admin_can_reject_application_and_suspend_then_restore_runner(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermissions($admin, [
            'runner_applications.reject',
            'runners.suspend',
            'runners.restore',
        ]);

        $applicant = $this->createPendingApplication();

        $this->asAccount($admin, 'runner-reject-token')
            ->postJson(
                '/api/admin/runner-applications/'.$applicant->id.'/reject',
                ['reason' => 'Application did not meet requirements.']
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_REJECTED
            );

        $this->assertDatabaseHas('runner_license_verifications', [
            'account_id' => $applicant->id,
            'status' => RunnerLicenseVerification::STATUS_REJECTED,
            'is_current' => false,
        ]);

        $runner = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_APPROVED
        );

        $this->verifyIdentity($runner);

        $this->asAccount($admin, 'runner-suspend-token')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/suspend',
                ['reason' => 'Eligibility temporarily suspended.']
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_SUSPENDED
            );

        $this->asAccount($admin, 'runner-restore-token')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/restore',
                ['reason' => 'Eligibility restored after review.']
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_APPROVED
            );

        $this->assertDatabaseHas('audit_logs', [
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_APPLICATION_REJECTED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_SUSPENDED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_RESTORED',
        ]);
    }
}
