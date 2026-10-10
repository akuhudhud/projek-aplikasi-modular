<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Session;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditTransactionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $id,
        string $name,
        string $phone,
        string $email,
        string $role,
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => $id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function createSession(
        Account $account,
        string $token,
        string $sessionId
    ): void {
        Session::create([
            'id' => $sessionId,
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    public function test_deactivation_is_rolled_back_when_audit_recording_fails(): void
    {
        $account = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Audit Test User',
            '60111111111',
            'audit-test-user@example.com',
            'USER'
        );

        $token = 'audit-transaction-deactivation-token';

        $this->createSession(
            $account,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(
                    new RuntimeException(
                        'Simulated audit recording failure.'
                    )
                );
        });

        $this->withoutExceptionHandling();

        try {
            $this->withHeader(
                'Authorization',
                'Bearer '.$token
            )->postJson('/api/me/deactivate');

            $this->fail(
                'The audit recording failure should abort the transaction.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated audit recording failure.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'ended_at' => null,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $account->id,
            'action' => 'ACCOUNT_DEACTIVATED',
        ]);
    }

    public function test_super_admin_creation_is_rolled_back_when_audit_recording_fails(): void
    {
        $root = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'root-audit-test@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $token = 'audit-transaction-super-admin-token';

        $this->createSession(
            $root,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(
                    new RuntimeException(
                        'Simulated audit recording failure.'
                    )
                );
        });

        $this->withoutExceptionHandling();

        try {
            $this->withHeader(
                'Authorization',
                'Bearer '.$token
            )->postJson('/api/admin/accounts', [
                'name' => 'New Super Admin',
                'phone' => '60222222222',
                'email' => 'new-admin-audit-test@example.com',
                'password' => 'Password1',
                'password_confirmation' => 'Password1',
                'reason' => 'Approved administrative appointment.',
            ]);

            $this->fail(
                'The audit recording failure should abort the transaction.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated audit recording failure.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing('accounts', [
            'email' => 'new-admin-audit-test@example.com',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $root->id,
            'action' => 'SUPER_ADMIN_CREATED',
        ]);
    }
}
