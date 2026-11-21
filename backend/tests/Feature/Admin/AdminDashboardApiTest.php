<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\CoreTask;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role,
        string $email
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Dashboard Test',
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);

        return $account;
    }

    private function authenticate(Account $account, string $token): void
    {
        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function createTask(
        string $status,
        ?string $completedAt = null
    ): CoreTask {
        return CoreTask::create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'DashboardTest',
            'task_type' => 'TEST_TASK',
            'source_type' => 'dashboard_test',
            'source_id' => (string) Str::uuid(),
            'created_by_account_id' => null,
            'assigned_account_id' => null,
            'generic_status' => $status,
            'completed_at' => $completedAt,
        ]);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/admin/dashboard')
            ->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_dashboard(): void
    {
        $user = $this->createAccount(
            'USER',
            'dashboard-user@example.com'
        );

        $token = 'dashboard-user-token';

        $this->authenticate($user, $token);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_super_admin_can_view_dashboard_statistics(): void
    {
        $admin = $this->createAccount(
            'SUPER_ADMIN',
            'dashboard-admin@example.com'
        );

        $activeUser = $this->createAccount(
            'USER',
            'dashboard-active@example.com'
        );

        $suspendedUser = $this->createAccount(
            'USER',
            'dashboard-suspended@example.com'
        );

        Account::query()
            ->whereKey($suspendedUser->id)
            ->update(['status' => 'SUSPENDED']);

        Account::query()
            ->whereKey($activeUser->id)
            ->update(['runner_status' => Account::RUNNER_STATUS_PENDING]);

        $completedAt = now()->subHours(2);

        $this->createTask(
            CoreTask::STATUS_OPEN
        );

        $this->createTask(
            CoreTask::STATUS_COMPLETED,
            $completedAt->toDateTimeString()
        );

        $token = 'dashboard-admin-token';

        $this->authenticate($admin, $token);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.accounts.total_users', 2)
            ->assertJsonPath('data.accounts.active_users', 1)
            ->assertJsonPath('data.accounts.suspended_users', 1)
            ->assertJsonPath('data.runners.total', 1)
            ->assertJsonPath(
                'data.runner_applications.pending',
                1
            )
            ->assertJsonPath('data.tasks.total', 2)
            ->assertJsonPath(
                'data.tasks.statuses.OPEN',
                1
            )
            ->assertJsonPath(
                'data.tasks.statuses.COMPLETED',
                1
            )
            ->assertJsonCount(
                7,
                'data.tasks.daily_activity'
            );
    }

    public function test_root_super_admin_can_access_dashboard(): void
    {
        $admin = $this->createAccount(
            'ROOT_SUPER_ADMIN',
            'dashboard-root@example.com'
        );

        $token = 'dashboard-root-token';

        $this->authenticate($admin, $token);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'generated_at',
                    'accounts',
                    'runners',
                    'runner_applications',
                    'tasks',
                ],
            ]);
    }
}
