<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
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

    private DrivingLicenseType $licenseType;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->runnerStatusService = app(RunnerStatusService::class);

        $this->licenseType = DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'GENERAL',
            'name' => 'Lesen Am',
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
            'driving_license_type_id' => $this->licenseType->id,
            'code' => 'D',
            'name' => 'Lesen D',
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function createAccount(
        string $role = 'USER',
        string $status = 'ACTIVE',
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $account = Account::query()->create([
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
        IdentityVerification::query()->create([
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
        Account $account
    ): RunnerLicenseVerification {
        return RunnerLicenseVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number_encrypted' => Crypt::encryptString(
                'D123456789012'
            ),
            'license_type' => $this->licenseType->code,
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
            'submitted_at' => now(),
            'reviewed_at' => null,
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
            UploadedFile::fake()->image('runner-license.jpg'),
            $this->licenseType->code
        );
    }

    private function grantPermissions(
        Account $account,
        array $permissions
    ): void {
        foreach (array_unique($permissions) as $permission) {
            AccountPermission::query()->create([
                'account_id' => $account->id,
                'permission_key' => $permission,
            ]);
        }
    }

    public function test_user_can_apply_with_a_valid_dynamic_license(): void
    {
        $account = $this->createAccount();
        $this->verifyIdentityOnly($account);

        $result = $this->applyWithLicense($account);

        $this->assertSame(
            Account::RUNNER_STATUS_PENDING,
            $result->runner_status
        );

        $this->assertSame('USER', $result->role);

        $this->assertDatabaseHas('runner_license_verifications', [
            'account_id' => $account->id,
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
        ]);

        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('audit_logs')
                ->where('actor_account_id', $account->id)
                ->where('target_account_id', $account->id)
                ->where('action', 'RUNNER_APPLICATION_SUBMITTED')
                ->count()
        );
    }

    public function test_application_rejects_unknown_license_type(): void
    {
        $account = $this->createAccount();
        $this->verifyIdentityOnly($account);

        try {
            $this->runnerStatusService->apply(
                $account,
                'D123456789012',
                'D',
                now()->addYear()->toDateString(),
                UploadedFile::fake()->image('runner-license.jpg'),
                'UNKNOWN_TYPE'
            );

            $this->fail('An unknown license type must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(
                'Jenis lesen tidak sah atau tidak aktif.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing('runner_license_verifications', [
            'account_id' => $account->id,
        ]);
    }

    public function test_application_rejects_class_outside_selected_type(): void
    {
        $account = $this->createAccount();
        $this->verifyIdentityOnly($account);

        try {
            $this->runnerStatusService->apply(
                $account,
                'D123456789012',
                'Z99',
                now()->addYear()->toDateString(),
                UploadedFile::fake()->image('runner-license.jpg'),
                $this->licenseType->code
            );

            $this->fail('An unknown license class must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(
                'Kelas lesen tidak sah, tidak aktif atau bukan milik jenis lesen yang dipilih.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing('runner_license_verifications', [
            'account_id' => $account->id,
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

        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('audit_logs')
                ->where('actor_account_id', $account->id)
                ->where('target_account_id', $account->id)
                ->where('action', 'RUNNER_APPLICATION_SUBMITTED')
                ->count()
        );
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

    public function test_admin_can_approve_pending_application(): void
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

        $this->verifyIdentityOnly($applicant);
        $license = $this->createPendingLicense($applicant);

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

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $license->id,
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
            'reviewer_account_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_APPLICATION_APPROVED',
            'reason' => 'Application reviewed and approved.',
        ]);
    }

    public function test_admin_can_reject_pending_application(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermissions($admin, [
            'runner_applications.reject',
        ]);

        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->verifyIdentityOnly($applicant);
        $license = $this->createPendingLicense($applicant);

        $result = $this->runnerStatusService->changeStatus(
            $admin,
            $applicant->id,
            Account::RUNNER_STATUS_REJECTED,
            'Application did not meet requirements.'
        );

        $this->assertSame(
            Account::RUNNER_STATUS_REJECTED,
            $result->runner_status
        );

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $license->id,
            'status' => RunnerLicenseVerification::STATUS_REJECTED,
            'is_current' => false,
            'rejection_reason' => 'Application did not meet requirements.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_APPLICATION_REJECTED',
            'reason' => 'Application did not meet requirements.',
        ]);
    }

    public function test_admin_without_permission_cannot_approve_application(): void
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

    public function test_root_can_manage_super_admin_runner_status(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $target = $this->createAccount(
            'SUPER_ADMIN',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->verifyIdentityOnly($target);
        $this->createPendingLicense($target);

        $result = $this->runnerStatusService->changeStatus(
            $root,
            $target->id,
            Account::RUNNER_STATUS_APPROVED,
            'Root approved the application.'
        );

        $this->assertSame(
            Account::RUNNER_STATUS_APPROVED,
            $result->runner_status
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $target->id,
            'action' => 'RUNNER_APPLICATION_APPROVED',
            'reason' => 'Root approved the application.',
        ]);
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
}
