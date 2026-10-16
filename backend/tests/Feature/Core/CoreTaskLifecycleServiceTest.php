<?php

namespace Tests\Feature\Core;

use App\Contracts\CoreTaskLifecycle;
use App\Models\Account;
use App\Models\CoreTask;
use App\Services\CoreTaskLifecycleService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class CoreTaskLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private CoreTaskLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifecycle = new CoreTaskLifecycleService();
    }

    private function createAccount(
        string $status = 'ACTIVE',
        string $runnerStatus = Account::RUNNER_STATUS_APPROVED
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Lifecycle Test Account',
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
        string $status,
        ?string $assignedAccountId = null,
        bool $started = false
    ): CoreTask {
        return CoreTask::create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'test-module',
            'task_type' => 'TEST_TASK',
            'source_type' => 'test-source',
            'source_id' => (string) Str::uuid(),
            'assigned_account_id' => $assignedAccountId,
            'generic_status' => $status,
            'assigned_at' => $assignedAccountId !== null ? now() : null,
            'started_at' => $started ? now() : null,
        ]);
    }

    public function test_lifecycle_contract_resolves_to_service(): void
    {
        $this->assertInstanceOf(
            CoreTaskLifecycleService::class,
            $this->app->make(CoreTaskLifecycle::class)
        );
    }

    public function test_assigned_approved_runner_can_start_task(): void
    {
        $account = $this->createAccount();

        $task = $this->createTask(
            CoreTask::STATUS_ASSIGNED,
            $account->id
        );

        $result = $this->lifecycle->start(
            $task->task_id,
            $account->id
        );

        $this->assertSame(
            CoreTask::STATUS_IN_PROGRESS,
            $result->generic_status
        );

        $this->assertNotNull($result->started_at);

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'generic_status' => CoreTask::STATUS_IN_PROGRESS,
            'assigned_account_id' => $account->id,
        ]);
    }

    public function test_in_progress_task_can_be_completed_by_assigned_approved_runner(): void
    {
        $account = $this->createAccount();

        $task = $this->createTask(
            CoreTask::STATUS_IN_PROGRESS,
            $account->id,
            true
        );

        $result = $this->lifecycle->complete(
            $task->task_id,
            $account->id
        );

        $this->assertSame(
            CoreTask::STATUS_COMPLETED,
            $result->generic_status
        );

        $this->assertNotNull($result->started_at);
        $this->assertNotNull($result->completed_at);

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'generic_status' => CoreTask::STATUS_COMPLETED,
        ]);
    }

    public function test_pending_runner_cannot_start_assigned_task(): void
    {
        $account = $this->createAccount(
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $task = $this->createTask(
            CoreTask::STATUS_ASSIGNED,
            $account->id
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only approved runners can perform task actions.'
        );

        $this->lifecycle->start(
            $task->task_id,
            $account->id
        );
    }

    public function test_suspended_runner_cannot_complete_assigned_task(): void
    {
        $account = $this->createAccount(
            'ACTIVE',
            Account::RUNNER_STATUS_SUSPENDED
        );

        $task = $this->createTask(
            CoreTask::STATUS_IN_PROGRESS,
            $account->id,
            true
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only approved runners can perform task actions.'
        );

        $this->lifecycle->complete(
            $task->task_id,
            $account->id
        );
    }

    public function test_unassigned_account_cannot_start_task(): void
    {
        $assignee = $this->createAccount();
        $otherAccount = $this->createAccount();

        $task = $this->createTask(
            CoreTask::STATUS_ASSIGNED,
            $assignee->id
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only the assigned account can perform this task action.'
        );

        $this->lifecycle->start(
            $task->task_id,
            $otherAccount->id
        );
    }

    public function test_inactive_account_cannot_start_task(): void
    {
        $account = $this->createAccount('SUSPENDED');

        $task = $this->createTask(
            CoreTask::STATUS_ASSIGNED,
            $account->id
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only active accounts can perform task actions.'
        );

        $this->lifecycle->start(
            $task->task_id,
            $account->id
        );
    }

    public function test_open_task_cannot_be_started_before_assignment(): void
    {
        $account = $this->createAccount();

        $task = $this->createTask(
            CoreTask::STATUS_OPEN
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only the assigned account can perform this task action.'
        );

        $this->lifecycle->start(
            $task->task_id,
            $account->id
        );
    }

    public function test_task_cannot_be_completed_before_starting(): void
    {
        $account = $this->createAccount();

        $task = $this->createTask(
            CoreTask::STATUS_ASSIGNED,
            $account->id
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only in-progress tasks can be completed.'
        );

        $this->lifecycle->complete(
            $task->task_id,
            $account->id
        );
    }

    public function test_completed_task_cannot_be_started_again(): void
    {
        $account = $this->createAccount();

        $task = $this->createTask(
            CoreTask::STATUS_COMPLETED,
            $account->id,
            true
        );

        $task->completed_at = now();
        $task->save();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only assigned tasks can be started.'
        );

        $this->lifecycle->start(
            $task->task_id,
            $account->id
        );
    }

    public function test_start_rejects_assigned_task_without_assigned_timestamp(): void
    {
        $account = $this->createAccount();

        $task = $this->createTask(
            CoreTask::STATUS_ASSIGNED,
            $account->id
        );

        $task->assigned_at = null;
        $task->save();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Task assignment fields are inconsistent.'
        );

        $this->lifecycle->start(
            $task->task_id,
            $account->id
        );
    }

    public function test_start_rejects_task_that_already_has_started_timestamp(): void
    {
        $account = $this->createAccount();

        $task = $this->createTask(
            CoreTask::STATUS_ASSIGNED,
            $account->id,
            true
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Task assignment fields are inconsistent.'
        );

        $this->lifecycle->start(
            $task->task_id,
            $account->id
        );
    }

    public function test_complete_rejects_in_progress_task_without_started_timestamp(): void
    {
        $account = $this->createAccount();

        $task = $this->createTask(
            CoreTask::STATUS_IN_PROGRESS,
            $account->id
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Task lifecycle timestamps are inconsistent.'
        );

        $this->lifecycle->complete(
            $task->task_id,
            $account->id
        );
    }

    public function test_complete_rejects_task_that_already_has_completed_timestamp(): void
    {
        $account = $this->createAccount();

        $task = $this->createTask(
            CoreTask::STATUS_IN_PROGRESS,
            $account->id,
            true
        );

        $task->completed_at = now();
        $task->save();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Task lifecycle timestamps are inconsistent.'
        );

        $this->lifecycle->complete(
            $task->task_id,
            $account->id
        );
    }

    public function test_unknown_task_is_rejected(): void
    {
        $account = $this->createAccount();

        $this->expectException(ModelNotFoundException::class);

        $this->lifecycle->start(
            (string) Str::uuid(),
            $account->id
        );
    }

    public function test_unknown_account_is_rejected(): void
    {
        $task = $this->createTask(
            CoreTask::STATUS_ASSIGNED,
            (string) Str::uuid()
        );

        $this->expectException(ModelNotFoundException::class);

        $this->lifecycle->start(
            $task->task_id,
            (string) Str::uuid()
        );
    }

    public function test_empty_identifiers_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->lifecycle->start('', '');
    }
}
