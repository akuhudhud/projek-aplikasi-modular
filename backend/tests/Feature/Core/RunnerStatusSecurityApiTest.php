<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerStatusSecurityApiTest extends TestCase
{
    use RefreshDatabase;

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

    private function verifyIdentity(Account $account): void
    {
        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Contoh Nama Sebenar',
            'mykad_number_encrypted' => Crypt::encryptString('900101145678'),
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
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

    public function test_profile_update_cannot_escalate_runner_status(): void
    {
        $account = $this->createAccount();
        $token = 'runner-profile-security-token';

        $this->authenticate($account, $token);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
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
        $this->verifyIdentity($account);

        $token = 'runner-pending-security-token';

        $this->authenticate($account, $token);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/runner-application')
            ->assertCreated();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'name' => 'Pending Runner Security Test',
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ])->assertOk();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Pending Runner Security Test',
            'role' => 'USER',
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);
    }
}
