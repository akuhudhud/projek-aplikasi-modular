<?php

namespace App\Services;

use App\Contracts\CoreTaskLifecycle;
use App\Models\Account;
use App\Models\CoreTask;
use App\Models\IdentityVerification;
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

    public function arrive(
        string $taskId,
        string $actorAccountId,
        float $latitude,
        float $longitude
    ): CoreTask {
        $this->validateIdentifiers($taskId, $actorAccountId);

        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException(
                'Latitude must be between -90 and 90.'
            );
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException(
                'Longitude must be between -180 and 180.'
            );
        }

        return DB::transaction(function () use (
            $taskId,
            $actorAccountId,
            $latitude,
            $longitude
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
                    'Only in-progress tasks can be marked as arrived.'
                );
            }

            if ($task->started_at === null) {
                throw new DomainException(
                    'Task must be started before arrival can be recorded.'
                );
            }

            if (
                $task->arrived_at !== null
                || $task->arrival_latitude !== null
                || $task->arrival_longitude !== null
            ) {
                throw new DomainException(
                    'Task arrival has already been recorded.'
                );
            }

            $task->arrived_at = now();
            $task->arrival_latitude = $latitude;
            $task->arrival_longitude = $longitude;
            $task->save();

            $this->notifyMarketplaceCustomer(
                $task,
                'marketplace.task_arrived',
                'Runner telah tiba',
                'Runner telah tiba di lokasi tugasan.'
            );

            return $task->refresh();
        });
    }

    public function complete(
        string $taskId,
        string $actorAccountId,
        ?string $completionProofPath = null
    ): CoreTask {
        $this->validateIdentifiers($taskId, $actorAccountId);

        return DB::transaction(function () use (
            $taskId,
            $actorAccountId,
            $completionProofPath
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

            /*
             * Marketplace tasks are Runner-facing jobs.
             * They must have arrival GPS and photo proof before completion.
             * Other Core Task modules retain their existing completion flow.
             */
            if ($task->module_key === 'Marketplace') {
                if (
                    $task->arrived_at === null
                    || $task->arrival_latitude === null
                    || $task->arrival_longitude === null
                ) {
                    throw new DomainException(
                        'Runner must arrive and record GPS before completing the task.'
                    );
                }

                if (
                    $completionProofPath === null
                    || trim($completionProofPath) === ''
                ) {
                    throw new DomainException(
                        'Completion photo proof is required for Marketplace tasks.'
                    );
                }
            }

            $task->completed_at = now();

            if ($completionProofPath !== null) {
                $task->completion_proof_path = $completionProofPath;
            }

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
                taskId: $task->task_id,
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

        $identityVerified = IdentityVerification::query()
            ->where('account_id', $account->id)
            ->where('status', IdentityVerification::STATUS_VERIFIED)
            ->exists();

        if (!$identityVerified) {
            throw new DomainException(
                'Verified identity is required to perform task actions.'
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
