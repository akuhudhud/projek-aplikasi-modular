<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\RunnerLicenseVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseVerificationCurrentConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(): Account
    {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'License Consistency Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    private function createLicense(
        Account $account,
        string $licenseNumber,
        string $status,
        bool $isCurrent
    ): RunnerLicenseVerification {
        return RunnerLicenseVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number' => $licenseNumber,
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => $status,
            'is_current' => $isCurrent,
            'submitted_at' => now(),
            'reviewed_at' => $status === RunnerLicenseVerification::STATUS_PENDING
                ? null
                : now(),
            'reviewer_account_id' => null,
            'rejection_reason' => $status === RunnerLicenseVerification::STATUS_REJECTED
                ? 'Dokumen tidak jelas.'
                : null,
            'disk' => 'local',
            'document_path' => 'runner-license-documents/'
                .Str::uuid()
                .'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
    }

    public function test_rejected_historical_license_remains_non_current_after_new_application_is_verified(): void
    {
        $runner = $this->createAccount();

        /*
         * Rekod permohonan pertama yang ditolak.
         */
        $rejectedLicense = $this->createLicense(
            $runner,
            'D111111111111',
            RunnerLicenseVerification::STATUS_REJECTED,
            false
        );

        /*
         * Permohonan semula yang kemudian disahkan.
         */
        $verifiedLicense = $this->createLicense(
            $runner,
            'D222222222222',
            RunnerLicenseVerification::STATUS_VERIFIED,
            true
        );

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $rejectedLicense->id,
            'account_id' => $runner->id,
            'status' => RunnerLicenseVerification::STATUS_REJECTED,
            'is_current' => false,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verifiedLicense->id,
            'account_id' => $runner->id,
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
        ]);

        /*
         * Hanya satu rekod lesen boleh menjadi rekod semasa.
         */
        $currentCount = RunnerLicenseVerification::query()
            ->where('account_id', $runner->id)
            ->where('is_current', true)
            ->count();

        $this->assertSame(1, $currentCount);

        /*
         * Rekod semasa mesti berstatus VERIFIED.
         */
        $currentLicense = RunnerLicenseVerification::query()
            ->where('account_id', $runner->id)
            ->where('is_current', true)
            ->firstOrFail();

        $this->assertSame(
            RunnerLicenseVerification::STATUS_VERIFIED,
            $currentLicense->status
        );

        $this->assertSame(
            $verifiedLicense->id,
            $currentLicense->id
        );

        $this->assertSame(
            2,
            RunnerLicenseVerification::query()
                ->where('account_id', $runner->id)
                ->count()
        );
    }

    public function test_pending_license_cannot_be_current(): void
    {
        $runner = $this->createAccount();

        $pendingLicense = $this->createLicense(
            $runner,
            'D333333333333',
            RunnerLicenseVerification::STATUS_PENDING,
            false
        );

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $pendingLicense->id,
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
        ]);

        $this->assertSame(
            0,
            RunnerLicenseVerification::query()
                ->where('account_id', $runner->id)
                ->where('is_current', true)
                ->count()
        );
    }

    public function test_rejected_license_cannot_be_current(): void
    {
        $runner = $this->createAccount();

        $rejectedLicense = $this->createLicense(
            $runner,
            'D444444444444',
            RunnerLicenseVerification::STATUS_REJECTED,
            false
        );

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $rejectedLicense->id,
            'status' => RunnerLicenseVerification::STATUS_REJECTED,
            'is_current' => false,
        ]);

        $this->assertSame(
            0,
            RunnerLicenseVerification::query()
                ->where('account_id', $runner->id)
                ->where('is_current', true)
                ->count()
        );
    }
}
