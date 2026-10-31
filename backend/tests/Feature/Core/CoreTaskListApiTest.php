<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\CoreTask;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreTaskListApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $id,
        string $name,
        string $phone,
        string $email,
        string $role
    ): Account {
        return Account::create([
            'id' => $id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticate(Account $account, string $token): void
    {
        Session::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function createTask(
        string $taskId,
        string $sourceId,
        ?string $assignedAccountId,
        string $status = CoreTask::STATUS_ASSIGNED,
        ?string $createdByAccountId = null
    ): CoreTask {
        return CoreTask::create([
            'task_id' => $taskId,
            'module_key' => 'PickAndDrop',
            'task_type' => 'PICK_AND_DROP_REQUEST',
            'source_type' => 'pick_and_drop_request',
            'source_id' => $sourceId,
            'created_by_account_id' => $createdByAccountId
                ?? $assignedAccountId,
            'assigned_account_id' => $assignedAccountId,
            'generic_status' => $status,
        ]);
    }

    public function test_core_task_list_requires_authentication(): void
    {
        $this->getJson('/api/core/tasks')
            ->assertUnauthorized();
    }

    public function test_regular_user_only_sees_tasks_assigned_to_their_account(): void
    {
        $user = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'User One',
            '60111111111',
            'user1@example.com',
            'USER'
        );

        $otherUser = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'User Two',
            '60222222222',
            'user2@example.com',
            'USER'
        );

        $this->createTask(
            '11111111-1111-4111-8111-111111111111',
            'source-user-one',
            $user->id,
            CoreTask::STATUS_ASSIGNED,
            $otherUser->id
        );

        $this->createTask(
            '22222222-2222-4222-8222-222222222222',
            'source-user-two',
            $otherUser->id
        );

        $this->createTask(
            '33333333-3333-4333-8333-333333333333',
            'source-unassigned',
            null,
            CoreTask::STATUS_OPEN
        );

        $token = 'core-task-user-token';
        $this->authenticate($user, $token);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/core/tasks');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonFragment([
                'task_id' => '11111111-1111-4111-8111-111111111111',
            ])
            ->assertJsonMissing([
                'task_id' => '22222222-2222-4222-8222-222222222222',
            ])
            ->assertJsonMissing([
                'task_id' => '33333333-3333-4333-8333-333333333333',
            ])
            ->assertJsonMissingPath(
                'data.tasks.0.created_by_account_id'
            );

        $this->assertSame(
            $user->id,
            $response->json('data.tasks.0.assigned_account_id')
        );

        $this->assertArrayNotHasKey(
            'created_by_account_id',
            $response->json('data.tasks.0')
        );

        $this->assertNotSame(
            $user->id,
            $otherUser->id
        );
    }

    public function test_super_admin_can_view_all_core_tasks(): void
    {
        $admin = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Super Admin',
            '60111111111',
            'admin@example.com',
            'SUPER_ADMIN'
        );

        $user = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Regular User',
            '60222222222',
            'user@example.com',
            'USER'
        );

        $this->createTask(
            '11111111-1111-4111-8111-111111111111',
            'source-assigned',
            $user->id
        );

        $this->createTask(
            '22222222-2222-4222-8222-222222222222',
            'source-open',
            null,
            CoreTask::STATUS_OPEN
        );

        $token = 'core-task-admin-token';
        $this->authenticate($admin, $token);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/core/tasks');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.tasks')
            ->assertJsonFragment([
                'task_id' => '11111111-1111-4111-8111-111111111111',
            ])
            ->assertJsonFragment([
                'task_id' => '22222222-2222-4222-8222-222222222222',
            ]);

        $this->assertSame(
            $user->id,
            $response->json('data.tasks.0.created_by_account_id')
        );
    }

    public function test_core_task_list_returns_safe_fields_and_pagination(): void
    {
        $user = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Task User',
            '60111111111',
            'taskuser@example.com',
            'USER'
        );

        $this->createTask(
            '11111111-1111-4111-8111-111111111111',
            'source-safe-fields',
            $user->id
        );

        $token = 'core-task-safe-fields-token';
        $this->authenticate($user, $token);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/core/tasks');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tasks' => [
                        '*' => [
                            'task_id',
                            'module_key',
                            'task_type',
                            'source_type',
                            'source_id',
                            'assigned_account_id',
                            'generic_status',
                            'assigned_at',
                            'started_at',
                            'completed_at',
                            'cancelled_at',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ])
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.pagination.per_page', 50)
            ->assertJsonMissingPath(
                'data.tasks.0.created_by_account_id'
            );

        $response->assertJsonMissingPath('data.tasks.0.password');
        $response->assertJsonMissingPath('data.tasks.0.token_hash');
    }
}
