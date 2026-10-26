<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityVerificationDeletionRunnerProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role = 'USER'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner Protection Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticateAsRoot(Account $root): void
    {
        $token = 'runner-protection-'.Str::uuid();

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $root->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function createIdentityVerification(
        Account $account
    ): IdentityVerification {
        return IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Nama Runner Ujian',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'verified_at' => now(),
        ]);
    }

    private function assertDeletionBlockedForRunnerStatus(
        string $runnerStatus
    ): void {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $runner = $this->createAccount();

        DB::table('accounts')
            ->where('id', $runner->id)
            ->update([
                'runner_status' => $runnerStatus,
            ]);

        $verification = $this->createIdentityVerification($runner);

        $this->authenticateAsRoot($root);

        $this->deleteJson(
            '/api/admin/identity-verifications/'.$runner->id.'/data',
            [
                'reason' => 'Ujian perlindungan Runner',
            ]
        )->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $runner->id,
            'status' => IdentityVerification::STATUS_VERIFIED,
        ]);
    }

    public function test_identity_data_deletion_is_blocked_for_pending_runner(): void
    {
        $this->assertDeletionBlockedForRunnerStatus(
            Account::RUNNER_STATUS_PENDING
        );
    }

    public function test_identity_data_deletion_is_blocked_for_approved_runner(): void
    {
        $this->assertDeletionBlockedForRunnerStatus(
            Account::RUNNER_STATUS_APPROVED
        );
    }
}
