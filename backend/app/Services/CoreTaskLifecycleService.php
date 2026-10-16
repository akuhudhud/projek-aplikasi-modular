<?php

namespace App\Services;

use App\Contracts\CoreTaskLifecycle;
use App\Models\Account;
use App\Models\CoreTask;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CoreTaskLifecycleService implements CoreTaskLifecycle
{
    public function start(
        string $taskId,
        string $actorAccountId
    ): CoreTask {
        $this->validateIdentifiers($taskId, $actorAccountId);

        return DB::transaction(function () use (
            $taskId,
            $actorAccountId
        ): CoreTask {
            $task = CoreTask::query()
                ->whereKey($taskId)
                ->lockForUpdate()
                ->firstOrFail();

            $account = Account::query()
                ->whereKey($actorAccountId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertAssignedApprovedRunner($task, $account);

            if ($task->generic_status !== CoreTask::STATUS_ASSIGNED) {
                throw new DomainException(
                    'Only assigned tasks can be started.'
                );
            }

            if ($task->assigned_at === null || $task->started_at !== null) {
                throw new DomainException(
                    'Task assignment fields are inconsistent.'
                );
            }

            $task->started_at = now();
            $task->generic_status = CoreTask::STATUS_IN_PROGRESS;
            $task->save();

            return $task->refresh();
        });
    }

    public function complete(
        string $taskId,
        string $actorAccountId
    ): CoreTask {
        $this->validateIdentifiers($taskId, $actorAccountId);

        return DB::transaction(function () use (
            $taskId,
            $actorAccountId
        ): CoreTask {
            $task = CoreTask::query()
                ->whereKey($taskId)
                ->lockForUpdate()
                ->firstOrFail();

            $account = Account::query()
                ->whereKey($actorAccountId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertAssignedApprovedRunner($task, $account);

            if ($task->generic_status !== CoreTask::STATUS_IN_PROGRESS) {
                throw new DomainException(
                    'Only in-progress tasks can be completed.'
                );
            }

            if ($task->started_at === null || $task->completed_at !== null) {
                throw new DomainException(
                    'Task lifecycle timestamps are inconsistent.'
                );
            }

            $task->completed_at = now();
            $task->generic_status = CoreTask::STATUS_COMPLETED;
            $task->save();

            return $task->refresh();
        });
    }

    private function validateIdentifiers(
        string $taskId,
        string $actorAccountId
    ): void {
        if (
            trim($taskId) === ''
            || trim($actorAccountId) === ''
        ) {
            throw new InvalidArgumentException(
                'Task ID and actor account ID must not be empty.'
            );
        }
    }

    private function assertAssignedApprovedRunner(
        CoreTask $task,
        Account $account
    ): void {
        if ($account->status !== 'ACTIVE') {
            throw new DomainException(
                'Only active accounts can perform task actions.'
            );
        }

        if (
            $account->runner_status
            !== Account::RUNNER_STATUS_APPROVED
        ) {
            throw new DomainException(
                'Only approved runners can perform task actions.'
            );
        }

        if (
            $task->assigned_account_id === null
            || $task->assigned_account_id !== $account->id
        ) {
            throw new DomainException(
                'Only the assigned account can perform this task action.'
            );
        }
    }
}
