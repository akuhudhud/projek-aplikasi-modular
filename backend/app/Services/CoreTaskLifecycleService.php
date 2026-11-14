<?php

namespace App\Services;

use App\Contracts\CoreTaskLifecycle;
use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceRequest;
use App\Notifications\MarketplaceEventNotification;
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

            $this->notifyMarketplaceCustomer(
                $task,
                'marketplace.task_started',
                'Runner telah memulakan tugasan',
                'Runner telah memulakan tugasan anda.'
            );

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

            $this->notifyMarketplaceCustomer(
                $task,
                'marketplace.task_completed',
                'Tugasan telah selesai',
                'Runner telah menyelesaikan tugasan anda.'
            );

            return $task->refresh();
        });
    }

    private function notifyMarketplaceCustomer(
        CoreTask $task,
        string $event,
        string $title,
        string $message
    ): void {
        if (
            $task->module_key !== 'Marketplace'
            || $task->source_type !== 'marketplace_request'
            || !$task->source_id
        ) {
            return;
        }

        $request = MarketplaceRequest::query()
            ->where('request_id', $task->source_id)
            ->first();

        if (!$request) {
            return;
        }

        $customer = Account::query()
            ->whereKey($request->customer_account_id)
            ->first();

        if (!$customer) {
            return;
        }

        $customer->notify(
            new MarketplaceEventNotification(
                event: $event,
                title: $title,
                message: $message,
                requestId: $request->request_id,
            )
        );
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
