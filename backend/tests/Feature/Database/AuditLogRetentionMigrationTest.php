<?php

namespace Tests\Feature\Database;

use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditLogRetentionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_refuses_to_restore_foreign_keys_when_historical_accounts_are_missing(): void
    {
        $actor = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Audit Migration Actor',
            'phone' => '60111111111',
            'email' => 'audit-migration-actor@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $target = Account::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'name' => 'Audit Migration Target',
            'phone' => '60222222222',
            'email' => 'audit-migration-target@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $auditLog = AuditLog::create([
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Retention migration regression test.',
        ]);

        $auditLogId = $auditLog->id;
        $targetId = $target->id;

        $target->delete();

        $this->assertDatabaseMissing('accounts', [
            'id' => $targetId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLogId,
            'actor_account_id' => $actor->id,
            'target_account_id' => $targetId,
            'action' => 'ACCOUNT_SUSPENDED',
        ]);

        $migration = require database_path(
            'migrations/2026_10_10_000001_preserve_audit_logs_after_account_deletion.php'
        );

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Cannot restore audit log foreign keys because '
            .'historical audit records reference accounts '
            .'that no longer exist. Keep the retention migration '
            .'applied to preserve audit history.'
        );

        $migration->down();
    }
}
