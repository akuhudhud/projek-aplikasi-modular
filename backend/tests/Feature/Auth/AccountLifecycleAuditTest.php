<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountLifecycleAuditTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithSession(
        string $accountId,
        string $name,
        string $phone,
        string $email,
        string $token,
        string $sessionId
    ): Account {
        $account = Account::create([
            'id' => $accountId,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        Session::create([
            'id' => $sessionId,
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $account;
    }

    public function test_deactivation_records_audit_event(): void
    {
        $token = 'account-lifecycle-deactivation-token';

        $account = $this->createAccountWithSession(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Deactivation Audit User',
            '60111111111',
            'deactivation-audit@example.com',
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/deactivate');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deactivated successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DEACTIVATED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $account->id,
            'target_account_id' => $account->id,
            'action' => 'ACCOUNT_DEACTIVATED',
            'reason' => 'Account deactivated by account owner.',
        ]);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
        ]);

        $session = Session::where('account_id', $account->id)
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        $this->assertNotNull($session->ended_at);
    }

    public function test_deletion_records_audit_event(): void
    {
        $token = 'account-lifecycle-deletion-token';

        $account = $this->createAccountWithSession(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Deletion Audit User',
            '60222222222',
            'deletion-audit@example.com',
            $token,
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/delete');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DELETED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $account->id,
            'target_account_id' => $account->id,
            'action' => 'ACCOUNT_DELETED',
            'reason' => 'Account marked as deleted by account owner.',
        ]);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
        ]);

        $session = Session::where('account_id', $account->id)
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        $this->assertNotNull($session->ended_at);
    }
}
