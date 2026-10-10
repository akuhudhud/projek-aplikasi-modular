<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogAccessAfterAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_audit_log_after_target_account_is_physically_deleted(): void
    {
        $admin = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Root Super Admin',
            'phone' => '60111111111',
            'email' => 'audit-retention-admin@example.com',
            'password' => 'Password1',
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $target = Account::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'name' => 'Deleted Target',
            'phone' => '60222222222',
            'email' => 'audit-retention-target@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'audit-retention-access-token';

        Session::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $auditLog = AuditLog::create([
            'actor_account_id' => $admin->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Account suspended following an administrative review.',
        ]);

        $auditLogId = $auditLog->id;
        $targetId = $target->id;

        $target->delete();

        $this->assertDatabaseMissing('accounts', [
            'id' => $targetId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLogId,
            'actor_account_id' => $admin->id,
            'target_account_id' => $targetId,
            'action' => 'ACCOUNT_SUSPENDED',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/audit-logs');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'id' => $auditLogId,
                'actor_account_id' => $admin->id,
                'target_account_id' => $targetId,
                'action' => 'ACCOUNT_SUSPENDED',
                'reason' => 'Account suspended following an administrative review.',
            ]);
    }
}
