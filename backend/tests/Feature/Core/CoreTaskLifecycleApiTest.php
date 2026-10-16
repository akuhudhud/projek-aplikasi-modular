<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\CoreTask;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreTaskLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $status = 'ACTIVE',
        string $role = 'USER'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Lifecycle API Account',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function authenticate(
        Account $account,
        string $token
    ): void {
        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function createAssignedTask(
        Account $account,
        string $status = CoreTask::STATUS_ASSIGNED,
        bool $started = false
    ): CoreTask {
        return CoreTask::create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'test-module',
            'task_type' => 'TEST_TASK',
            'source_type' => 'test-source',
            'source_id' => (string) Str::uuid(),
            'created_by_account_id' => $account->id,
            'assigned_account_id' => $account->id,
            'generic_status' => $status,
            'assigned_at' => now(),
            'started_at' => $started ? now() : null,
        ]);
    }

    public function test_start_endpoint_requires_authentication(): void
    {
        $response = $this->postJson(
            '/api/core/tasks/'.Str::uuid().'/start'
        );

        $response->assertUnauthorized();
    }

    public function test_assignee_can_start_and_complete_task_via_api(): void
    {
        $account = $this->createAccount();
        $task = $this->createAssignedTask($account);
        $token = 'lifecycle-api-token';

        $this->authenticate($account, $token);

        $startResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/core/tasks/'.$task->task_id.'/start');

        $startResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.task.generic_status',
                CoreTask::STATUS_IN_PROGRESS
            )
            ->assertJsonStructure([
                'success',
                'data' => [
                    'task' => [
                        'task_id',
                        'generic_status',
                        'assigned_account_id',
                        'started_at',
                        'completed_at',
                    ],
                ],
            ]);

        $completeResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/core/tasks/'.$task->task_id.'/complete');

        $completeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.task.generic_status',
                CoreTask::STATUS_COMPLETED
            );

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'generic_status' => CoreTask::STATUS_COMPLETED,
        ]);
    }

    public function test_other_account_cannot_start_assigned_task(): void
    {
        $assignee = $this->createAccount();
        $otherAccount = $this->createAccount();

        $task = $this->createAssignedTask($assignee);
        $token = 'other-account-token';

        $this->authenticate($otherAccount, $token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/core/tasks/'.$task->task_id.'/start')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Only the assigned account can perform this task action.'
            );

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'generic_status' => CoreTask::STATUS_ASSIGNED,
        ]);
    }

    public function test_open_task_cannot_be_started_via_api(): void
    {
        $account = $this->createAccount();

        $task = CoreTask::create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'test-module',
            'task_type' => 'TEST_TASK',
            'source_type' => 'test-source',
            'source_id' => (string) Str::uuid(),
            'created_by_account_id' => $account->id,
            'assigned_account_id' => null,
            'generic_status' => CoreTask::STATUS_OPEN,
        ]);

        $token = 'open-task-token';
        $this->authenticate($account, $token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/core/tasks/'.$task->task_id.'/start')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'generic_status' => CoreTask::STATUS_OPEN,
        ]);
    }

    public function test_unknown_task_returns_not_found(): void
    {
        $account = $this->createAccount();
        $token = 'unknown-task-token';

        $this->authenticate($account, $token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/core/tasks/'.Str::uuid().'/start')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
