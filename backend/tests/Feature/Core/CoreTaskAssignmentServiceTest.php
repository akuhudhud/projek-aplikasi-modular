<?php

namespace Tests\Feature\Core;

use App\Contracts\CoreTaskAssigner;
use App\Models\Account;
use App\Models\CoreTask;
use App\Services\CoreTaskAssignmentService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreTaskAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private CoreTaskAssignmentService $assigner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assigner = new CoreTaskAssignmentService();
    }

    private function createAccount(
        string $id,
        string $status = 'ACTIVE',
        string $runnerStatus = Account::RUNNER_STATUS_APPROVED
    ): Account {
        $account = Account::create([
            'id' => $id,
            'name' => 'Assignment Test Account',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => $status,
        ]);

        $account->runner_status = $runnerStatus;
        $account->save();

        return $account->fresh();
    }

    private function createTask(
        string $status = CoreTask::STATUS_OPEN
    ): CoreTask {
        return CoreTask::create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'test-module',
            'task_type' => 'TEST_TASK',
            'source_type' => 'test-source',
            'source_id' => (string) Str::uuid(),
            'generic_status' => $status,
        ]);
    }

    public function test_assignment_contract_resolves_to_core_service(): void
    {
        $this->assertInstanceOf(
            CoreTaskAssignmentService::class,
            $this->app->make(CoreTaskAssigner::class)
        );

        $this->assertInstanceOf(
            CoreTaskAssigner::class,
            $this->assigner
        );
    }

    public function test_new_account_is_not_a_runner_by_default(): void
    {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'New Account',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $this->assertSame(
            Account::RUNNER_STATUS_NOT_RUNNER,
            $account->fresh()->runner_status
        );
    }

    public function test_approved_runner_can_be_assigned_to_open_task(): void
    {
        $task = $this->createTask();

        $account = $this->createAccount(
            (string) Str::uuid()
        );

        $this->assertSame(
            Account::RUNNER_STATUS_APPROVED,
            $account->runner_status
        );

        $assigned = $this->assigner->assign(
            $task->task_id,
            $account->id
        );

        $this->assertSame(
            CoreTask::STATUS_ASSIGNED,
            $assigned->generic_status
        );

        $this->assertSame(
            $account->id,
            $assigned->assigned_account_id
        );

        $this->assertNotNull($assigned->assigned_at);

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'assigned_account_id' => $account->id,
            'generic_status' => CoreTask::STATUS_ASSIGNED,
        ]);
    }

    public function test_assignment_rejects_non_active_account(): void
    {
        $task = $this->createTask();

        $account = $this->createAccount(
            (string) Str::uuid(),
            'SUSPENDED'
        );

        try {
            $this->assigner->assign(
                $task->task_id,
                $account->id
            );

            $this->fail('A non-active account must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'Only active accounts can be assigned to a task.',
                $exception->getMessage()
            );
        }

        $task->refresh();

        $this->assertSame(
            CoreTask::STATUS_OPEN,
            $task->generic_status
        );

        $this->assertNull($task->assigned_account_id);
        $this->assertNull($task->assigned_at);
    }

    public function test_assignment_rejects_account_that_is_not_a_runner(): void
    {
        $task = $this->createTask();

        $account = $this->createAccount(
            (string) Str::uuid(),
            'ACTIVE',
            Account::RUNNER_STATUS_NOT_RUNNER
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only approved runners can be assigned to a task.'
        );

        try {
            $this->assigner->assign(
                $task->task_id,
                $account->id
            );
        } finally {
            $task->refresh();

            $this->assertSame(
                CoreTask::STATUS_OPEN,
                $task->generic_status
            );

            $this->assertNull($task->assigned_account_id);
            $this->assertNull($task->assigned_at);
        }
    }

    public function test_assignment_rejects_pending_runner(): void
    {
        $task = $this->createTask();

        $account = $this->createAccount(
            (string) Str::uuid(),
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only approved runners can be assigned to a task.'
        );

        $this->assigner->assign(
            $task->task_id,
            $account->id
        );
    }

    public function test_assignment_rejects_rejected_runner(): void
    {
        $task = $this->createTask();

        $account = $this->createAccount(
            (string) Str::uuid(),
            'ACTIVE',
            Account::RUNNER_STATUS_REJECTED
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only approved runners can be assigned to a task.'
        );

        $this->assigner->assign(
            $task->task_id,
            $account->id
        );
    }

    public function test_assignment_rejects_suspended_runner(): void
    {
        $task = $this->createTask();

        $account = $this->createAccount(
            (string) Str::uuid(),
            'ACTIVE',
            Account::RUNNER_STATUS_SUSPENDED
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only approved runners can be assigned to a task.'
        );

        $this->assigner->assign(
            $task->task_id,
            $account->id
        );
    }

    public function test_assignment_rejects_task_that_is_not_open(): void
    {
        $task = $this->createTask(
            CoreTask::STATUS_IN_PROGRESS
        );

        $account = $this->createAccount(
            (string) Str::uuid()
        );

        try {
            $this->assigner->assign(
                $task->task_id,
                $account->id
            );

            $this->fail('A non-open task must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'Only open tasks can be assigned.',
                $exception->getMessage()
            );
        }

        $task->refresh();

        $this->assertSame(
            CoreTask::STATUS_IN_PROGRESS,
            $task->generic_status
        );

        $this->assertNull($task->assigned_account_id);
        $this->assertNull($task->assigned_at);
    }

    public function test_assignment_rejects_repeated_assignment(): void
    {
        $task = $this->createTask();

        $account = $this->createAccount(
            (string) Str::uuid()
        );

        $this->assigner->assign(
            $task->task_id,
            $account->id
        );

        try {
            $this->assigner->assign(
                $task->task_id,
                $account->id
            );

            $this->fail('A repeated assignment must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'Only open tasks can be assigned.',
                $exception->getMessage()
            );
        }

        $task->refresh();

        $this->assertSame(
            CoreTask::STATUS_ASSIGNED,
            $task->generic_status
        );

        $this->assertSame(
            $account->id,
            $task->assigned_account_id
        );

        $this->assertNotNull($task->assigned_at);

        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_assignment_rejects_unknown_task(): void
    {
        $account = $this->createAccount(
            (string) Str::uuid()
        );

        $this->expectException(ModelNotFoundException::class);

        $this->assigner->assign(
            (string) Str::uuid(),
            $account->id
        );
    }

    public function test_assignment_rejects_unknown_account(): void
    {
        $task = $this->createTask();

        $this->expectException(ModelNotFoundException::class);

        $this->assigner->assign(
            $task->task_id,
            (string) Str::uuid()
        );
    }

    public function test_assignment_rejects_empty_identifiers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->assigner->assign('', '');
    }
}
