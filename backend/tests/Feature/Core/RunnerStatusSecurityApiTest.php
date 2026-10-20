<?php

namespace Tests\Feature\Core;

use App\Models\Account;
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

class RunnerStatusSecurityApiTest extends TestCase
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

    private function createAccount(): Account
    {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner Security Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $account->runner_status = Account::RUNNER_STATUS_NOT_RUNNER;
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

    public function test_profile_update_cannot_escalate_runner_status(): void
    {
        $account = $this->createAccount();

        $this->asAccount(
            $account,
            'runner-profile-security-token'
        )->patchJson('/api/me', [
            'name' => 'Updated Runner Security Test',
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ])->assertOk()
            ->assertJsonPath(
                'data.account.name',
                'Updated Runner Security Test'
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Runner Security Test',
            'role' => 'USER',
            'runner_status' => Account::RUNNER_STATUS_NOT_RUNNER,
        ]);
    }

    public function test_pending_applicant_cannot_self_approve_through_profile_update(): void
    {
        $account = $this->createAccount();
        $this->verifyIdentityOnly($account);

        $this->asAccount(
            $account,
            'runner-pending-security-token'
        )->post('/api/me/runner-application', [
            'license_number' => 'D123456789012',
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->image(
                'runner-license.jpg'
            ),
        ])->assertCreated()
            ->assertJsonPath('success', true);

        $this->patchJson('/api/me', [
            'name' => 'Pending Runner Security Test',
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ])->assertOk();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Pending Runner Security Test',
            'role' => 'USER',
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'account_id' => $account->id,
            'license_class' => 'D',
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $account->id,
            'target_account_id' => $account->id,
            'action' => 'RUNNER_APPLICATION_SUBMITTED',
        ]);

        $this->assertSame(
            1,
            \App\Models\AuditLog::query()
                ->where('actor_account_id', $account->id)
                ->where('target_account_id', $account->id)
                ->where('action', 'RUNNER_APPLICATION_SUBMITTED')
                ->count()
        );
    }
}
