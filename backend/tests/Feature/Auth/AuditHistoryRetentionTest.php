<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditHistoryRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $id,
        string $name,
        string $phone,
        string $email
    ): Account {
        return Account::create([
            'id' => $id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_audit_log_survives_physical_account_deletion(): void
    {
        $actor = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Audit Actor',
            '60111111111',
            'audit-actor@example.com'
        );

        $target = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Audit Target',
            '60222222222',
            'audit-target@example.com'
        );

        $auditLog = AuditLog::create([
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Account suspended following an audit test.',
        ]);

        $auditLogId = $auditLog->id;

        $target->delete();

        $this->assertDatabaseMissing('accounts', [
            'id' => $target->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLogId,
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Account suspended following an audit test.',
        ]);

        $storedAuditLog = AuditLog::findOrFail($auditLogId);

        $this->assertNull($storedAuditLog->target);
        $this->assertNotNull($storedAuditLog->actor);
    }

    public function test_audit_log_survives_physical_deletion_of_actor_account(): void
    {
        $actor = $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Deleted Audit Actor',
            '60333333333',
            'deleted-audit-actor@example.com'
        );

        $target = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Remaining Audit Target',
            '60444444444',
            'remaining-audit-target@example.com'
        );

        $auditLog = AuditLog::create([
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_UPDATED',
            'reason' => 'Account updated before actor deletion.',
        ]);

        $auditLogId = $auditLog->id;

        $actor->delete();

        $this->assertDatabaseMissing('accounts', [
            'id' => $actor->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLogId,
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_UPDATED',
            'reason' => 'Account updated before actor deletion.',
        ]);

        $storedAuditLog = AuditLog::findOrFail($auditLogId);

        $this->assertNull($storedAuditLog->actor);
        $this->assertNotNull($storedAuditLog->target);
    }
}
