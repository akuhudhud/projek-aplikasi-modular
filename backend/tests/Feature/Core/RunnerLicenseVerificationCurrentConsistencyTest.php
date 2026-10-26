<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseVerificationCurrentConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role = 'USER'
    ): Account {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'License Consistency Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticateAs(
        Account $account,
        string $token
    ): static {
        Session::query()->create([
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

    private function createPendingLicense(
        Account $account,
        string $licenseNumber
    ): RunnerLicenseVerification {
        return RunnerLicenseVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number_encrypted' => Crypt::encryptString(
                $licenseNumber
            ),
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'previous_license_verification_id' => null,
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => 'runner-license-documents/'
                .Str::uuid()
                .'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
    }

    private function grantReviewPermission(Account $admin): void
    {
        AccountPermission::query()->create([
            'account_id' => $admin->id,
            'permission_key' => 'runner_license_verifications.review',
        ]);
    }

    public function test_only_one_verified_license_is_current_for_same_type_and_class(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $runner = $this->createAccount();

        $olderLicense = $this->createPendingLicense(
            $runner,
            'D111111111111'
        );

        $newerLicense = $this->createPendingLicense(
            $runner,
            'D222222222222'
        );

        $this->authenticateAs(
            $admin,
            'license-consistency-review-token'
        )->postJson(
            '/api/admin/runner-license-verifications/'
                .$olderLicense->id
                .'/decision',
            [
                'decision' => 'VERIFIED',
            ]
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                'VERIFIED'
            );

        $this->postJson(
            '/api/admin/runner-license-verifications/'
                .$newerLicense->id
                .'/decision',
            [
                'decision' => 'VERIFIED',
            ]
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                'VERIFIED'
            );

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $olderLicense->id,
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => false,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $newerLicense->id,
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
        ]);

        $currentCount = RunnerLicenseVerification::query()
            ->where('account_id', $runner->id)
            ->where('license_type', 'GENERAL')
            ->where('license_class', 'D')
            ->where(
                'status',
                RunnerLicenseVerification::STATUS_VERIFIED
            )
            ->where('is_current', true)
            ->count();

        $this->assertSame(1, $currentCount);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_VERIFIED',
        ]);

        $this->assertSame(
            2,
            \App\Models\AuditLog::query()
                ->where('actor_account_id', $admin->id)
                ->where('target_account_id', $runner->id)
                ->where(
                    'action',
                    'RUNNER_LICENSE_VERIFICATION_VERIFIED'
                )
                ->count()
        );
    }

    public function test_already_reviewed_license_cannot_be_decided_again(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $runner = $this->createAccount();

        $license = $this->createPendingLicense(
            $runner,
            'D333333333333'
        );

        $this->authenticateAs(
            $admin,
            'license-consistency-repeat-token'
        )->postJson(
            '/api/admin/runner-license-verifications/'
                .$license->id
                .'/decision',
            [
                'decision' => 'VERIFIED',
            ]
        )->assertOk();

        $this->postJson(
            '/api/admin/runner-license-verifications/'
                .$license->id
                .'/decision',
            [
                'decision' => 'REJECTED',
                'rejection_reason' => 'Semakan kedua tidak dibenarkan',
            ]
        )->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $license->id,
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_VERIFIED',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_LICENSE_VERIFICATION_REJECTED',
        ]);
    }
}
