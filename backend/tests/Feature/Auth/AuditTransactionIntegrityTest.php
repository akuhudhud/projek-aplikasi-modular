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
        $sessionId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

        $this->createSession($account, $token, $sessionId);

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
            'id' => $sessionId,
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

    public function test_deletion_is_rolled_back_when_audit_recording_fails(): void
    {
        $account = $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Delete Transaction User',
            '60333333333',
            'delete-transaction-user@example.com',
            'USER'
        );

        $token = 'audit-transaction-deletion-token';
        $sessionId = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

        $this->createSession($account, $token, $sessionId);

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
            )->postJson('/api/me/delete');

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
            'id' => $sessionId,
            'ended_at' => null,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $account->id,
            'target_account_id' => $account->id,
            'action' => 'ACCOUNT_DELETED',
        ]);
    }

    public function test_suspension_is_rolled_back_when_audit_recording_fails(): void
    {
        $admin = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'root-status-test@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $target = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Target User',
            '60222222222',
            'target-status-test@example.com',
            'USER'
        );

        $adminToken = 'admin-status-suspend-token';
        $targetToken = 'target-status-suspend-token';
        $adminSessionId = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
        $targetSessionId = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

        $this->createSession($admin, $adminToken, $adminSessionId);
        $this->createSession($target, $targetToken, $targetSessionId);

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
                'Bearer '.$adminToken
            )->postJson(
                '/api/admin/accounts/'.$target->id.'/suspend',
                [
                    'reason' => 'Suspension transaction integrity test.',
                ]
            );

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
            'id' => $target->id,
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => $targetSessionId,
            'ended_at' => null,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
        ]);
    }

    public function test_reactivation_is_rolled_back_when_audit_recording_fails(): void
    {
        $admin = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'root-reactivation-test@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $target = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Deactivated User',
            '60222222222',
            'deactivated-user@example.com',
            'USER',
            'DEACTIVATED'
        );

        $adminToken = 'admin-status-reactivation-token';

        $this->createSession(
            $admin,
            $adminToken,
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc'
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
                'Bearer '.$adminToken
            )->postJson(
                '/api/admin/accounts/'.$target->id.'/reactivate',
                [
                    'reason' => 'Reactivation transaction integrity test.',
                ]
            );

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
            'id' => $target->id,
            'status' => 'DEACTIVATED',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_REACTIVATED',
        ]);
    }

    public function test_unsuspension_is_rolled_back_when_audit_recording_fails(): void
    {
        $admin = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'root-unsuspend-test@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $target = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Suspended User',
            '60222222222',
            'suspended-user@example.com',
            'USER',
            'SUSPENDED'
        );

        $adminToken = 'admin-status-unsuspend-token';

        $this->createSession(
            $admin,
            $adminToken,
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc'
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
                'Bearer '.$adminToken
            )->postJson(
                '/api/admin/accounts/'.$target->id.'/unsuspend',
                [
                    'reason' => 'Unsuspension transaction integrity test.',
                ]
            );

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
            'id' => $target->id,
            'status' => 'SUSPENDED',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_UNSUSPENDED',
        ]);
    }
}
