<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountLifecycleConcurrentTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspend_rejects_stale_status_after_locked_read(): void
    {
        $this->assertConcurrentTransitionRejected(
            'ACTIVE',
            'SUSPENDED',
            'suspend',
            'Account is already suspended.',
            'ACCOUNT_SUSPENDED'
        );
    }

    public function test_reactivate_rejects_stale_status_after_locked_read(): void
    {
        $this->assertConcurrentTransitionRejected(
            'DEACTIVATED',
            'ACTIVE',
            'reactivate',
            'Account is already active.',
            'ACCOUNT_REACTIVATED'
        );
    }

    public function test_unsuspend_rejects_stale_status_after_locked_read(): void
    {
        $this->assertConcurrentTransitionRejected(
            'SUSPENDED',
            'ACTIVE',
            'unsuspend',
            'Only suspended accounts can be unsuspended.',
            'ACCOUNT_UNSUSPENDED'
        );
    }

    public function test_deactivate_rejects_account_that_is_no_longer_active(): void
    {
        $this->assertSelfServiceTransitionRejected(
            'DEACTIVATED',
            '/api/me/deactivate',
            'Only active accounts can be deactivated.',
            'ACCOUNT_DEACTIVATED'
        );
    }

    public function test_delete_rejects_account_that_is_no_longer_active(): void
    {
        $this->assertSelfServiceTransitionRejected(
            'DELETED',
            '/api/me/delete',
            'Only active accounts can be deleted.',
            'ACCOUNT_DELETED'
        );
    }

    private function assertConcurrentTransitionRejected(
        string $initialStatus,
        string $concurrentStatus,
        string $endpoint,
        string $expectedMessage,
        string $auditAction
    ): void {
        $root = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Root Admin',
            'phone' => '60111111111',
            'email' => 'root@example.com',
            'password' => 'Password1',
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $token = 'account-transition-race-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $root->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $target = Account::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'name' => 'Target User',
            'phone' => '60222222222',
            'email' => 'target@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => $initialStatus,
        ]);

        $injected = false;

        DB::listen(function (QueryExecuted $query) use (
            $target,
            $concurrentStatus,
            &$injected
        ): void {
            if ($injected) {
                return;
            }

            $sql = strtolower($query->sql);

            if (
                !str_contains($sql, 'select')
                || !str_contains($sql, 'accounts')
                || !in_array($target->id, $query->bindings, true)
            ) {
                return;
            }

            $injected = true;

            DB::table('accounts')
                ->where('id', $target->id)
                ->update([
                    'status' => $concurrentStatus,
                ]);
        });

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$target->id.'/'.$endpoint,
            ['reason' => 'Administrative review required.']
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', $expectedMessage);

        $this->assertTrue(
            $injected,
            'The test did not inject the concurrent status change.'
        );

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'status' => $concurrentStatus,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $target->id,
            'action' => $auditAction,
        ]);
    }

    private function assertSelfServiceTransitionRejected(
        string $concurrentStatus,
        string $endpoint,
        string $expectedMessage,
        string $auditAction
    ): void {
        $account = Account::create([
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'name' => 'Account Owner',
            'phone' => '60333333333',
            'email' => 'owner@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'self-service-transition-race-token';

        Session::create([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $injected = false;

        DB::listen(function (QueryExecuted $query) use (
            $account,
            $concurrentStatus,
            &$injected
        ): void {
            if ($injected) {
                return;
            }

            $sql = strtolower($query->sql);

            if (
                !str_contains($sql, 'select')
                || !str_contains($sql, 'accounts')
                || !in_array($account->id, $query->bindings, true)
            ) {
                return;
            }

            $injected = true;

            DB::table('accounts')
                ->where('id', $account->id)
                ->update([
                    'status' => $concurrentStatus,
                ]);
        });

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson($endpoint);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', $expectedMessage);

        $this->assertTrue(
            $injected,
            'The test did not inject the concurrent status change.'
        );

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => $concurrentStatus,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $account->id,
            'action' => $auditAction,
        ]);
    }
}
