<?php

namespace App\Services;

use App\Contracts\CoreTaskAssigner;
use App\Models\Account;
use App\Models\CoreTask;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CoreTaskAssignmentService implements CoreTaskAssigner
{
    public function assign(
        string $taskId,
        string $assigneeAccountId
    ): CoreTask {
        if (
            trim($taskId) === ''
            || trim($assigneeAccountId) === ''
        ) {
            throw new InvalidArgumentException(
                'Task ID and assignee account ID must not be empty.'
            );
        }

        return DB::transaction(function () use (
            $taskId,
            $assigneeAccountId
        ): CoreTask {
            $task = CoreTask::query()
                ->whereKey($taskId)
                ->lockForUpdate()
                ->firstOrFail();

            $account = Account::query()
                ->whereKey($assigneeAccountId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($account->status !== 'ACTIVE') {
                throw new DomainException(
                    'Only active accounts can be assigned to a task.'
                );
            }

            if ($task->generic_status !== CoreTask::STATUS_OPEN) {
                throw new DomainException(
                    'Only open tasks can be assigned.'
                );
            }

            if (
                $task->assigned_account_id !== null
                || $task->assigned_at !== null
            ) {
                throw new DomainException(
                    'Task assignment fields are inconsistent.'
                );
            }

            $task->assigned_account_id = $account->id;
            $task->assigned_at = now();
            $task->generic_status = CoreTask::STATUS_ASSIGNED;
            $task->save();

            return $task->refresh();
        });
    }
}
