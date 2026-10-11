<?php

namespace Tests\Feature\Database;

use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class AuditLogRetentionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_refuses_to_restore_foreign_keys_when_target_account_is_missing(): void
    {
        $actor = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Audit Migration Actor',
            '60111111111',
            'audit-migration-actor@example.com'
        );

        $target = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Audit Migration Target',
            '60222222222',
            'audit-migration-target@example.com'
        );

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

        $this->assertRollbackIsRejected();
    }

    public function test_migration_refuses_to_restore_foreign_keys_when_actor_account_is_missing(): void
    {
        $actor = $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Audit Migration Deleted Actor',
            '60333333333',
            'audit-migration-deleted-actor@example.com'
        );

        $target = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Audit Migration Existing Target',
            '60444444444',
            'audit-migration-existing-target@example.com'
        );

        $auditLog = AuditLog::create([
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Missing actor regression test.',
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

        $this->assertRollbackIsRejected();
    }

    public function test_migration_allows_rollback_when_all_referenced_accounts_exist(): void
    {
        $actor = $this->createAccount(
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'Audit Migration Safe Actor',
            '60555555555',
            'audit-migration-safe-actor@example.com'
        );

        $target = $this->createAccount(
            'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'Audit Migration Safe Target',
            '60666666666',
            'audit-migration-safe-target@example.com'
        );

        $auditLog = AuditLog::create([
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Safe rollback regression test.',
        ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $actor->id,
        ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
        ]);

        /*
         * Prevent actual DDL changes to the test database.
         * The migration must pass its orphan checks and request
         * restoration of the audit_logs foreign keys.
         */
        Schema::shouldReceive('table')
            ->once()
            ->with(
                'audit_logs',
                \Mockery::type(\Closure::class)
            );

        $migration = $this->loadRetentionMigration();

        $migration->down();
    }

    private function assertRollbackIsRejected(): void
    {
        $migration = $this->loadRetentionMigration();

        try {
            $migration->down();

            $this->fail(
                'Expected rollback to be rejected when historical '
                .'audit records reference missing accounts.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Cannot restore audit log foreign keys because '
                .'historical audit records reference accounts '
                .'that no longer exist. Keep the retention migration '
                .'applied to preserve audit history.',
                $exception->getMessage()
            );
        }
    }

    private function loadRetentionMigration(): object
    {
        return require database_path(
            'migrations/2026_10_10_000001_preserve_audit_logs_after_account_deletion.php'
        );
    }

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
}
