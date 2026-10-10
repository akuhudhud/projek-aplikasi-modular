<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_read_admin_audit_logs(): void
    {
        $user = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Regular User',
            'phone' => '60111111111',
            'email' => 'regular-user-audit@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $target = Account::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'name' => 'Audit Target',
            'phone' => '60222222222',
            'email' => 'audit-target-user@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'regular-user-audit-access-token';

        Session::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'account_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        AuditLog::create([
            'actor_account_id' => $user->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_UPDATED',
            'reason' => 'Authorization test audit record.',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/audit-logs');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
    }
}
