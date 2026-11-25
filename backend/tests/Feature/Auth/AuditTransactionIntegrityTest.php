<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
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
        string $profileName,
        string $phone,
        string $email,
        string $role,
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => $id,
            'profile_name' => $profileName,
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
                ->andThrow(new RuntimeException(
                    'Simulated audit recording failure.'
                ));
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

    public function test_super_admin_appointment_is_rolled_back_when_audit_recording_fails(): void
    {
        $root = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'root-audit-test@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $user = $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Existing User',
            '60222222222',
            'existing-user-audit-test@example.com',
            'USER'
        );

        $token = 'audit-transaction-super-admin-token';
        $userToken = 'existing-user-audit-session-token';
        $rootSessionId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
        $userSessionId = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

        $this->createSession($root, $token, $rootSessionId);
        $this->createSession($user, $userToken, $userSessionId);

        $originalPassword = $user->password;

        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(new RuntimeException(
                    'Simulated audit recording failure.'
                ));
        });

        $this->withoutExceptionHandling();

        try {
            $this->withHeader(
                'Authorization',
                'Bearer '.$token
            )->postJson(
                '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
                [
                    'job_title' => 'Pengurus Akaun',
                    'reason' => 'Approved administrative appointment.',
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

        $this->assertDatabaseCount('accounts', 2);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'profile_name' => 'Existing User',
            'phone' => '60222222222',
            'email' => 'existing-user-audit-test@example.com',
            'password' => $originalPassword,
            'job_title' => null,
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => $rootSessionId,
            'account_id' => $root->id,
            'ended_at' => null,
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => $userSessionId,
            'account_id' => $user->id,
            'token_hash' => hash('sha256', $userToken),
            'ended_at' => null,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $user->id,
            'action' => 'SUPER_ADMIN_APPOINTED',
        ]);
    }

    public function test_deletion_is_rolled_back_when_audit_recording_fails(): void
    {
        $account = $this->createAccount(
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'Delete Transaction User',
            '60333333333',
            'delete-transaction-user@example.com',
            'USER'
        );

        $token = 'audit-transaction-deletion-token';
        $sessionId = 'ffffffff-ffff-4fff-8fff-ffffffffffff';

        $this->createSession($account, $token, $sessionId);

        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(new RuntimeException(
                    'Simulated audit recording failure.'
                ));
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
            '11111111-1111-4111-8111-111111111111',
            'Root Super Admin',
            '60444444444',
            'root-status-test@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $target = $this->createAccount(
            '22222222-2222-4222-8222-222222222222',
            'Target User',
            '60555555555',
            'target-status-test@example.com',
            'USER'
        );

        $adminToken = 'admin-status-suspend-token';
        $targetToken = 'target-status-suspend-token';
        $adminSessionId = '33333333-3333-4333-8333-333333333333';
        $targetSessionId = '44444444-4444-4444-8444-444444444444';

        $this->createSession($admin, $adminToken, $adminSessionId);
        $this->createSession($target, $targetToken, $targetSessionId);

        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(new RuntimeException(
                    'Simulated audit recording failure.'
                ));
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
            '55555555-5555-4555-8555-555555555555',
            'Root Super Admin',
            '60666666666',
            'root-reactivation-test@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $target = $this->createAccount(
            '66666666-6666-4666-8666-666666666666',
            'Deactivated User',
            '60777777777',
            'deactivated-user@example.com',
            'USER',
            'DEACTIVATED'
        );

        $adminToken = 'admin-status-reactivation-token';

        $this->createSession(
            $admin,
            $adminToken,
            '77777777-7777-4777-8777-777777777777'
        );

        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(new RuntimeException(
                    'Simulated audit recording failure.'
                ));
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
            '88888888-8888-4888-8888-888888888888',
            'Root Super Admin',
            '60888888888',
            'root-unsuspend-test@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $target = $this->createAccount(
            '99999999-9999-4999-8999-999999999999',
            'Suspended User',
            '60999999999',
            'suspended-user@example.com',
            'USER',
            'SUSPENDED'
        );

        $adminToken = 'admin-status-unsuspend-token';

        $this->createSession(
            $admin,
            $adminToken,
            '10101010-1010-4010-8010-101010101010'
        );

        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(new RuntimeException(
                    'Simulated audit recording failure.'
                ));
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
