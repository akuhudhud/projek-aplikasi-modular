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
            'status' => 'ACTIVE',
        ]);

        $injected = false;

        DB::listen(function (QueryExecuted $query) use (
            $target,
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
                    'status' => 'SUSPENDED',
                ]);
        });

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$target->id.'/suspend', [
            'reason' => 'Administrative review required.',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Account is already suspended.',
            ]);

        $this->assertTrue(
            $injected,
            'The test did not inject the concurrent status change.'
        );

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'status' => 'SUSPENDED',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
        ]);
    }
}
