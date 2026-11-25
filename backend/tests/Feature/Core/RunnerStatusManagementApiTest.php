<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerStatusManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role = 'USER',
        string $runnerStatus = Account::RUNNER_STATUS_APPROVED,
        string $status = 'ACTIVE'
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Status Management Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);

        $account->runner_status = $runnerStatus;
        $account->save();

        return $account->fresh();
    }

    private function grantPermissions(
        Account $account,
        array $permissions
    ): void {
        foreach ($permissions as $permission) {
            AccountPermission::create([
                'account_id' => $account->id,
                'permission_key' => $permission,
            ]);
        }
    }

    private function authenticate(
        Account $account,
        string $token
    ): static {
        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function createCurrentVerifiedLicense(
        Account $runner,
        ?string $expiresAt = null
    ): RunnerLicenseVerification {
        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'license_number' => 'D123456789012',
            'license_type' => 'CDL',
            'license_class' => 'D',
            'expires_at' => $expiresAt
                ?? now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
            'submitted_at' => now()->subDays(10),
            'reviewed_at' => now()->subDays(9),
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => 'runner-license-documents/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
    }

    public function test_suspend_requires_reason(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantPermissions($admin, ['runners.suspend']);

        $runner = $this->createAccount();

        $this->authenticate($admin, 'suspend-no-reason')
            ->postJson('/api/admin/runners/'.$runner->id.'/suspend')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_SUSPENDED',
        ]);
    }

    public function test_suspend_rejects_whitespace_only_reason(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantPermissions($admin, ['runners.suspend']);

        $runner = $this->createAccount();

        $this->authenticate($admin, 'suspend-whitespace')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/suspend',
                ['reason' => '     ']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_SUSPENDED',
        ]);
    }

    public function test_suspend_requires_specific_permission(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $runner = $this->createAccount();

        $this->authenticate($admin, 'suspend-no-permission')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/suspend',
                ['reason' => 'Policy violation.']
            )
            ->assertForbidden();

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ]);
    }

    public function test_suspend_records_exactly_one_audit_entry(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantPermissions($admin, ['runners.suspend']);

        $runner = $this->createAccount();

        $reason = 'Runner eligibility suspended after review.';

        $this->authenticate($admin, 'suspend-success')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/suspend',
                ['reason' => $reason]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_SUSPENDED
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_SUSPENDED,
        ]);

        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('actor_account_id', $admin->id)
                ->where('target_account_id', $runner->id)
                ->where('action', 'RUNNER_ELIGIBILITY_SUSPENDED')
                ->where('reason', $reason)
                ->count()
        );
    }

    public function test_restore_requires_reason(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantPermissions($admin, ['runners.restore']);

        $runner = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_SUSPENDED
        );

        $this->authenticate($admin, 'restore-no-reason')
            ->postJson('/api/admin/runners/'.$runner->id.'/restore')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_SUSPENDED,
        ]);
    }

    public function test_restore_requires_specific_permission(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $runner = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_SUSPENDED
        );

        $this->authenticate($admin, 'restore-no-permission')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/restore',
                ['reason' => 'Reviewed.']
            )
            ->assertForbidden();

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_SUSPENDED,
        ]);
    }

    public function test_restore_is_blocked_without_current_verified_license(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantPermissions($admin, ['runners.restore']);

        $runner = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_SUSPENDED
        );

        $this->authenticate($admin, 'restore-no-license')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/restore',
                ['reason' => 'Attempted restoration.']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_SUSPENDED,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_RESTORED',
        ]);
    }

    public function test_restore_is_blocked_when_current_license_has_expired(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantPermissions($admin, ['runners.restore']);

        $runner = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_SUSPENDED
        );

        $this->createCurrentVerifiedLicense(
            $runner,
            now()->subDay()->toDateString()
        );

        $this->authenticate($admin, 'restore-expired-license')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/restore',
                ['reason' => 'Attempted restoration.']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_SUSPENDED,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_RESTORED',
        ]);
    }

    public function test_restore_records_exactly_one_audit_entry(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantPermissions($admin, ['runners.restore']);

        $runner = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_SUSPENDED
        );

        $this->createCurrentVerifiedLicense($runner);

        $reason = 'Runner restored after license verification.';

        $this->authenticate($admin, 'restore-success')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/restore',
                ['reason' => $reason]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_APPROVED
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ]);

        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('actor_account_id', $admin->id)
                ->where('target_account_id', $runner->id)
                ->where('action', 'RUNNER_ELIGIBILITY_RESTORED')
                ->where('reason', $reason)
                ->count()
        );
    }

    public function test_super_admin_cannot_suspend_another_super_admin(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantPermissions($admin, ['runners.suspend']);

        $target = $this->createAccount(
            'SUPER_ADMIN',
            Account::RUNNER_STATUS_APPROVED
        );

        $this->authenticate($admin, 'suspend-admin-target')
            ->postJson(
                '/api/admin/runners/'.$target->id.'/suspend',
                ['reason' => 'Attempted status change.']
            )
            ->assertForbidden();

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ]);
    }

    public function test_super_admin_cannot_suspend_own_runner_status(): void
    {
        $admin = $this->createAccount(
            'SUPER_ADMIN',
            Account::RUNNER_STATUS_APPROVED
        );

        $this->grantPermissions($admin, ['runners.suspend']);

        $this->authenticate($admin, 'suspend-self')
            ->postJson(
                '/api/admin/runners/'.$admin->id.'/suspend',
                ['reason' => 'Attempted self suspension.']
            )
            ->assertForbidden();

        $this->assertDatabaseHas('accounts', [
            'id' => $admin->id,
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ]);
    }

    public function test_runner_cannot_be_suspended_twice(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantPermissions($admin, ['runners.suspend']);

        $runner = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_SUSPENDED
        );

        $this->authenticate($admin, 'suspend-twice')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/suspend',
                ['reason' => 'Repeated suspension.']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_SUSPENDED,
        ]);

        $this->assertSame(
            0,
            DB::table('audit_logs')
                ->where('target_account_id', $runner->id)
                ->where('action', 'RUNNER_ELIGIBILITY_SUSPENDED')
                ->count()
        );
    }

    public function test_approved_runner_cannot_be_restored_again(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantPermissions($admin, ['runners.restore']);

        $runner = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_APPROVED
        );

        $this->createCurrentVerifiedLicense($runner);

        $this->authenticate($admin, 'restore-twice')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/restore',
                ['reason' => 'Repeated restoration.']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('accounts', [
            'id' => $runner->id,
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ]);

        $this->assertSame(
            0,
            DB::table('audit_logs')
                ->where('target_account_id', $runner->id)
                ->where('action', 'RUNNER_ELIGIBILITY_RESTORED')
                ->count()
        );
    }
}
