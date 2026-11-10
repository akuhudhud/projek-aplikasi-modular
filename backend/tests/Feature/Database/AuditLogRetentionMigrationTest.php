<?php

namespace Tests\Feature\Database;

use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogRetentionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_is_preserved_when_target_account_is_deleted(): void
    {
        $actor = $this->createAccount();
        $target = $this->createAccount();

        $auditLog = AuditLog::create([
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Target account deletion retention test.',
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
    }

    public function test_audit_log_is_preserved_when_actor_account_is_deleted(): void
    {
        $actor = $this->createAccount();
        $target = $this->createAccount();

        $auditLog = AuditLog::create([
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Actor account deletion retention test.',
        ]);

        $auditLogId = $auditLog->id;
        $actorId = $actor->id;

        $actor->delete();

        $this->assertDatabaseMissing('accounts', [
            'id' => $actorId,
        ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLogId,
            'actor_account_id' => $actorId,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
        ]);
    }

    public function test_audit_logs_table_has_no_account_foreign_keys(): void
    {
        $foreignKeys = Schema::getForeignKeys('audit_logs');

        $foreignKeyColumns = [];

        foreach ($foreignKeys as $foreignKey) {
            foreach ($foreignKey['columns'] as $column) {
                $foreignKeyColumns[] = $column;
            }
        }

        $this->assertNotContains(
            'actor_account_id',
            $foreignKeyColumns
        );

        $this->assertNotContains(
            'target_account_id',
            $foreignKeyColumns
        );
    }

    private function createAccount(): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Audit Retention Test Account',
            'phone' => null,
            'email' => 'audit-'.Str::uuid().'@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }
}
