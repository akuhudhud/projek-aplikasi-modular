<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogAccessAfterActorDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_audit_log_after_actor_account_is_physically_deleted(): void
    {
        $admin = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Root Super Admin',
            'phone' => '60111111111',
            'email' => 'audit-actor-admin@example.com',
            'password' => 'Password1',
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $actor = Account::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'name' => 'Deleted Audit Actor',
            'phone' => '60222222222',
            'email' => 'deleted-audit-actor@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $target = Account::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'name' => 'Remaining Audit Target',
            'phone' => '60333333333',
            'email' => 'remaining-audit-target@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'audit-actor-deletion-access-token';

        Session::create([
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $auditLog = AuditLog::create([
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_UPDATED',
            'reason' => 'Account update recorded before actor deletion.',
        ]);

        $auditLogId = $auditLog->id;
        $actorId = $actor->id;

        $actor->delete();

        $this->assertDatabaseMissing('accounts', [
            'id' => $actorId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLogId,
            'actor_account_id' => $actorId,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_UPDATED',
            'reason' => 'Account update recorded before actor deletion.',
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
                'actor_account_id' => $actorId,
                'target_account_id' => $target->id,
                'action' => 'ACCOUNT_UPDATED',
                'reason' => 'Account update recorded before actor deletion.',
            ]);
    }
}
