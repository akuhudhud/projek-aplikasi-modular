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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Str;
use Tests\TestCase;

class RunnerApprovalIdentityRecheckTest extends TestCase
{
    use RefreshDatabase;

    private DrivingLicenseType $licenseType;

    protected function setUp(): void
    {
        parent::setUp();

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
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $account = Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner Approval Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);

        $account->runner_status = $runnerStatus;
        $account->save();

        return $account->fresh();
    }

    private function grantApprovalPermission(Account $admin): void
    {
        AccountPermission::query()->create([
            'account_id' => $admin->id,
            'permission_key' => 'runner_applications.approve',
        ]);
    }

    private function createIdentity(
        Account $account,
        string $status
    ): IdentityVerification {
        return IdentityVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Nama Identiti Ujian',
            'mykad_number_encrypted' => Crypt::encryptString('900101145678'),
            'status' => $status,
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

    public function test_runner_cannot_be_approved_if_identity_is_not_verified(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantApprovalPermission($admin);

        $applicant = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_PENDING
        );

        $this->createIdentity(
            $applicant,
            IdentityVerification::STATUS_REJECTED
        );

        $license = $this->createPendingLicense($applicant);

        try {
            app(RunnerStatusService::class)->changeStatus(
                $admin,
                $applicant->id,
                Account::RUNNER_STATUS_APPROVED,
                'Semakan permohonan Runner.'
            );

            $this->fail(
                'Runner approval must fail when identity is not VERIFIED.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'Pengesahan identiti mesti berstatus VERIFIED sebelum permohonan Runner diluluskan.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $license->id,
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
            'reviewer_account_id' => null,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_APPLICATION_APPROVED',
        ]);
    }

    public function test_runner_cannot_be_approved_without_an_identity_record(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantApprovalPermission($admin);

        $applicant = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_PENDING
        );

        $license = $this->createPendingLicense($applicant);

        try {
            app(RunnerStatusService::class)->changeStatus(
                $admin,
                $applicant->id,
                Account::RUNNER_STATUS_APPROVED,
                'Semakan permohonan Runner.'
            );

            $this->fail(
                'Runner approval must fail when the identity record is missing.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'Pengesahan identiti mesti berstatus VERIFIED sebelum permohonan Runner diluluskan.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $license->id,
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
        ]);
    }
}
