<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\IdentityVerification;
use App\Models\RunnerLicenseVerification;
use App\Services\RunnerStatusService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private RunnerStatusService $runnerStatusService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(
            \Database\Seeders\DrivingLicenseCatalogSeeder::class
        );

        $this->runnerStatusService = app(RunnerStatusService::class);
    }

    private function createAccount(
        string $role = 'USER',
        string $status = 'ACTIVE',
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner Status Test',
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

    private function verifyIdentity(Account $account): void
    {
        $this->verifyIdentityOnly($account);

        RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number_encrypted' => Crypt::encryptString(
                'D123456789012'
            ),
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
            'submitted_at' => now(),
            'reviewed_at' => now(),
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

    private function applyWithLicense(Account $account): Account
    {
        return $this->runnerStatusService->apply(
            $account,
            'D123456789012',
            'D',
            now()->addYear()->toDateString(),
            UploadedFile::fake()->image('runner-license.jpg')
        );
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

    public function test_user_can_apply_and_status_becomes_pending(): void
    {
        $account = $this->createAccount();
        $this->verifyIdentityOnly($account);

        $result = $this->applyWithLicense($account);

        $this->assertSame(
            Account::RUNNER_STATUS_PENDING,
            $result->runner_status
        );

        $this->assertSame('USER', $result->role);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'runner_status' => Account::RUNNER_STATUS_PENDING,
            'role' => 'USER',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $account->id,
            'target_account_id' => $account->id,
            'action' => 'RUNNER_APPLICATION_SUBMITTED',
        ]);
    }

    public function test_application_cannot_be_submitted_twice(): void
    {
        $account = $this->createAccount();
        $this->verifyIdentityOnly($account);

        $this->applyWithLicense($account);

        try {
            $this->applyWithLicense($account);

            $this->fail('A duplicate application must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'A Runner application cannot be submitted from the current status.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_user_without_verified_identity_cannot_apply(): void
    {
        $account = $this->createAccount();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Identity verification is required before applying to become a Runner.'
        );

        $this->applyWithLicense($account);
    }

    public function test_pending_identity_verification_cannot_apply(): void
    {
        $account = $this->createAccount();

        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Contoh Nama Sebenar',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Identity verification is required before applying to become a Runner.'
        );

        $this->applyWithLicense($account);
    }

    public function test_rejected_identity_verification_cannot_apply(): void
    {
        $account = $this->createAccount();

        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Contoh Nama Sebenar',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_REJECTED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'rejection_reason' => 'Dokumen tidak jelas.',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Identity verification is required before applying to become a Runner.'
        );

        $this->applyWithLicense($account);
    }

    public function test_inactive_account_cannot_apply(): void
    {
        $account = $this->createAccount('USER', 'SUSPENDED');
        $this->verifyIdentityOnly($account);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only active accounts can apply to become a Runner.'
        );

        $this->applyWithLicense($account);
    }

    public function test_super_admin_can_approve_user_application_with_permission(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermissions($admin, [
            'runner_applications.approve',
        ]);

        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );
        $this->verifyIdentity($applicant);

        $result = $this->runnerStatusService->changeStatus(
            $admin,
            $applicant->id,
            Account::RUNNER_STATUS_APPROVED,
            'Application reviewed and approved.'
        );

        $this->assertSame(
            Account::RUNNER_STATUS_APPROVED,
            $result->runner_status
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_APPLICATION_APPROVED',
            'reason' => 'Application reviewed and approved.',
        ]);
    }

    public function test_super_admin_without_permission_cannot_approve_application(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->expectException(AuthorizationException::class);

        $this->runnerStatusService->changeStatus(
            $admin,
            $applicant->id,
            Account::RUNNER_STATUS_APPROVED,
            'Approval attempt without permission.'
        );
    }

    public function test_inactive_pending_account_cannot_be_approved_as_runner(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $applicant = $this->createAccount(
            'USER',
            'SUSPENDED',
            Account::RUNNER_STATUS_PENDING
        );

        try {
            $this->runnerStatusService->changeStatus(
                $admin,
                $applicant->id,
                Account::RUNNER_STATUS_APPROVED,
                'Attempted approval of inactive account.'
            );

            $this->fail('An inactive account must not be approved.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'Only active accounts can be approved as a Runner.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'status' => 'SUSPENDED',
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_APPLICATION_APPROVED',
        ]);
    }

    public function test_inactive_suspended_runner_cannot_be_restored(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $runner = $this->createAccount(
            'USER',
            'SUSPENDED',
            Account::RUNNER_STATUS_SUSPENDED
        );

        try {
            $this->runnerStatusService->changeStatus(
                $admin,
                $runner->id,
                Account::RUNNER_STATUS_APPROVED,
                'Attempted restoration of inactive account.'
            );

            $this->fail('An inactive Runner must not be restored.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'Only active accounts can be approved as a Runner.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'status' => 'SUSPENDED',
            'runner_status' => Account::RUNNER_STATUS_SUSPENDED,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_RESTORED',
        ]);
    }

    public function test_root_can_manage_super_admin_runner_status(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $target = $this->createAccount(
            'SUPER_ADMIN',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $result = $this->runnerStatusService->changeStatus(
            $root,
            $target->id,
            Account::RUNNER_STATUS_REJECTED,
            'Application did not meet requirements.'
        );

        $this->assertSame(
            Account::RUNNER_STATUS_REJECTED,
            $result->runner_status
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $target->id,
            'action' => 'RUNNER_APPLICATION_REJECTED',
        ]);
    }

    public function test_super_admin_cannot_manage_another_super_admin(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $target = $this->createAccount(
            'SUPER_ADMIN',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->expectException(AuthorizationException::class);

        $this->runnerStatusService->changeStatus(
            $admin,
            $target->id,
            Account::RUNNER_STATUS_APPROVED,
            'Approval attempt.'
        );
    }

    public function test_root_can_manage_another_root_runner_status(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $anotherRoot = $this->createAccount(
            'ROOT_SUPER_ADMIN',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );
        $this->verifyIdentity($anotherRoot);

        $result = $this->runnerStatusService->changeStatus(
            $root,
            $anotherRoot->id,
            Account::RUNNER_STATUS_APPROVED,
            'Root approved the application.'
        );

        $this->assertSame(
            Account::RUNNER_STATUS_APPROVED,
            $result->runner_status
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $anotherRoot->id,
            'action' => 'RUNNER_APPLICATION_APPROVED',
            'reason' => 'Root approved the application.',
        ]);
    }

    public function test_root_can_change_own_runner_status(): void
    {
        $root = $this->createAccount(
            'ROOT_SUPER_ADMIN',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );
        $this->verifyIdentity($root);

        $result = $this->runnerStatusService->changeStatus(
            $root,
            $root->id,
            Account::RUNNER_STATUS_APPROVED,
            'Root approved own Runner status.'
        );

        $this->assertSame(
            Account::RUNNER_STATUS_APPROVED,
            $result->runner_status
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $root->id,
            'action' => 'RUNNER_APPLICATION_APPROVED',
            'reason' => 'Root approved own Runner status.',
        ]);
    }

    public function test_super_admin_cannot_change_own_runner_status(): void
    {
        $admin = $this->createAccount(
            'SUPER_ADMIN',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->expectException(AuthorizationException::class);

        $this->runnerStatusService->changeStatus(
            $admin,
            $admin->id,
            Account::RUNNER_STATUS_APPROVED,
            'Self-approval attempt.'
        );
    }

    public function test_reason_is_required_for_admin_decision(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $target = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Reason is required.');

        $this->runnerStatusService->changeStatus(
            $admin,
            $target->id,
            Account::RUNNER_STATUS_APPROVED,
            '   '
        );
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $target = $this->createAccount();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'The requested Runner status transition is not allowed.'
        );

        $this->runnerStatusService->changeStatus(
            $admin,
            $target->id,
            Account::RUNNER_STATUS_APPROVED,
            'Attempted to skip application review.'
        );
    }

    public function test_approved_runner_can_be_suspended_and_restored_with_permissions(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermissions($admin, [
            'runners.suspend',
            'runners.restore',
        ]);

        $runner = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_APPROVED
        );
        $this->verifyIdentity($runner);

        $suspended = $this->runnerStatusService->changeStatus(
            $admin,
            $runner->id,
            Account::RUNNER_STATUS_SUSPENDED,
            'Runner eligibility temporarily suspended.'
        );

        $this->assertSame(
            Account::RUNNER_STATUS_SUSPENDED,
            $suspended->runner_status
        );

        $restored = $this->runnerStatusService->changeStatus(
            $admin,
            $runner->id,
            Account::RUNNER_STATUS_APPROVED,
            'Runner eligibility restored after review.'
        );

        $this->assertSame(
            Account::RUNNER_STATUS_APPROVED,
            $restored->runner_status
        );

        $this->assertDatabaseHas('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_SUSPENDED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_RESTORED',
        ]);
    }

    public function test_pending_application_list_respects_role_and_permissions(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $superAdmin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermissions($superAdmin, [
            'runner_applications.view',
        ]);

        $unprivilegedAdmin = $this->createAccount('SUPER_ADMIN');

        $userApplication = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $superAdminApplication = $this->createAccount(
            'SUPER_ADMIN',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $rootResults = $this->runnerStatusService
            ->pendingApplications($root);

        $this->assertTrue(
            $rootResults->contains('id', $userApplication->id)
        );

        $this->assertTrue(
            $rootResults->contains('id', $superAdminApplication->id)
        );

        $superAdminResults = $this->runnerStatusService
            ->pendingApplications($superAdmin);

        $this->assertTrue(
            $superAdminResults->contains('id', $userApplication->id)
        );

        $this->assertFalse(
            $superAdminResults->contains('id', $superAdminApplication->id)
        );

        $this->expectException(AuthorizationException::class);

        $this->runnerStatusService->pendingApplications(
            $unprivilegedAdmin
        );
    }

    public function test_regular_user_cannot_review_runner_applications(): void
    {
        $user = $this->createAccount();

        $this->expectException(AuthorizationException::class);

        $this->runnerStatusService->pendingApplications($user);
    }
}
