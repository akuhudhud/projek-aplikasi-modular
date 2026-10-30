<?php

namespace App\Services;

use App\Contracts\CoreTaskAssigner;
use App\Contracts\CoreTaskRegistry;
use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Models\RunnerPublicProfile;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceService
{
    public function __construct(
        private CoreTaskRegistry $coreTaskRegistry,
        private CoreTaskAssigner $coreTaskAssigner
    ) {
    }

    public function expireDueItems(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $expiredRequests = MarketplaceRequest::query()
                ->where('status', MarketplaceRequest::STATUS_OPEN)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->lockForUpdate()
                ->get();

            $requestIds = $expiredRequests
                ->pluck('request_id')
                ->all();

            foreach ($expiredRequests as $request) {
                $request->forceFill([
                    'status' => MarketplaceRequest::STATUS_EXPIRED,
                ])->save();
            }

            if ($requestIds !== []) {
                MarketplaceQuotation::query()
                    ->whereIn('request_id', $requestIds)
                    ->where(
                        'status',
                        MarketplaceQuotation::STATUS_SUBMITTED
                    )
                    ->update([
                        'status' => MarketplaceQuotation::STATUS_EXPIRED,
                        'updated_at' => $now,
                    ]);
            }

            MarketplaceQuotation::query()
                ->where(
                    'status',
                    MarketplaceQuotation::STATUS_SUBMITTED
                )
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->update([
                    'status' => MarketplaceQuotation::STATUS_EXPIRED,
                    'updated_at' => $now,
                ]);
        });
    }

    public function createRequest(
        Account $customer,
        array $data
    ): MarketplaceRequest {
        $sourceType = $data['source_type']
            ?? MarketplaceRequest::SOURCE_MARKETPLACE;

        if (!in_array($sourceType, [
            MarketplaceRequest::SOURCE_MARKETPLACE,
            MarketplaceRequest::SOURCE_RUNNER_PROFILE_LINK,
        ], true)) {
            throw new DomainException('Unsupported request source.');
        }

        $targetRunnerSlug = isset($data['target_runner_slug'])
            ? strtolower(trim((string) $data['target_runner_slug']))
            : null;

        if (
            $sourceType === MarketplaceRequest::SOURCE_RUNNER_PROFILE_LINK
            && !$targetRunnerSlug
        ) {
            throw new DomainException(
                'A runner profile request must identify its public profile slug.'
            );
        }

        if (
            $sourceType === MarketplaceRequest::SOURCE_MARKETPLACE
            && $targetRunnerSlug
        ) {
            throw new DomainException(
                'A marketplace request cannot specify a target runner profile.'
            );
        }

        $taskClass = strtoupper(trim(
            (string) ($data['task_class'] ?? '')
        ));

        $description = trim((string) ($data['description'] ?? ''));

        $allowedTaskClasses = [
            MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY,
            MarketplaceRequest::TASK_CLASS_NON_DELIVERY,
        ];

        if (!in_array($taskClass, $allowedTaskClasses, true)) {
            throw new DomainException(
                'Task class must be PICKUP_DELIVERY or NON_DELIVERY.'
            );
        }

        if ($description === '') {
            throw new DomainException('Request description is required.');
        }

        return DB::transaction(function () use (
            $customer,
            $sourceType,
            $targetRunnerSlug,
            $taskClass,
            $description,
            $data
        ): MarketplaceRequest {
            $targetRunnerId = null;

            if ($targetRunnerSlug !== null) {
                $profile = RunnerPublicProfile::query()
                    ->where('public_slug', $targetRunnerSlug)
                    ->where('is_published', true)
                    ->lockForUpdate()
                    ->first();

                if (!$profile) {
                    throw new DomainException(
                        'The selected runner profile is unavailable.'
                    );
                }

                $targetRunner = Account::query()
                    ->whereKey($profile->account_id)
                    ->lockForUpdate()
                    ->first();

                if (
                    !$targetRunner
                    || !$this->isEligibleRunner($targetRunner)
                ) {
                    throw new DomainException(
                        'The selected runner is unavailable or not approved.'
                    );
                }

                if ($targetRunner->id === $customer->id) {
                    throw new DomainException(
                        'A runner cannot target their own account.'
                    );
                }

                $targetRunnerId = $targetRunner->id;
            }

            $locationData = [
                'pickup_location_snapshot' => null,
                'delivery_location_snapshot' => null,
                'task_location_snapshot' => null,
            ];

            if (
                $taskClass
                === MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY
            ) {
                $locationData['pickup_location_snapshot'] =
                    $data['pickup_location_snapshot'] ?? null;

                $locationData['delivery_location_snapshot'] =
                    $data['delivery_location_snapshot'] ?? null;
            }

            if (
                $taskClass
                === MarketplaceRequest::TASK_CLASS_NON_DELIVERY
            ) {
                $locationData['task_location_snapshot'] =
                    $data['task_location_snapshot'] ?? null;
            }

            return MarketplaceRequest::query()->create([
                'request_id' => (string) Str::uuid(),
                'customer_account_id' => $customer->id,
                'target_runner_account_id' => $targetRunnerId,
                'source_type' => $sourceType,
                'task_class' => $taskClass,
                'description' => $description,
                'pickup_location_snapshot' =>
                    $locationData['pickup_location_snapshot'],
                'delivery_location_snapshot' =>
                    $locationData['delivery_location_snapshot'],
                'task_location_snapshot' =>
                    $locationData['task_location_snapshot'],
                'status' => MarketplaceRequest::STATUS_OPEN,
                'expires_at' => $data['expires_at'] ?? null,
            ]);
        });
    }

    public function cancelRequest(
        Account $customer,
        string $requestId
    ): MarketplaceRequest {
        $this->expireDueItems();

        $result = DB::transaction(function () use (
            $customer,
            $requestId
        ): ?MarketplaceRequest {
            $request = MarketplaceRequest::query()
                ->whereKey($requestId)
                ->lockForUpdate()
                ->first();

            if (!$request) {
                throw (new ModelNotFoundException())
                    ->setModel(MarketplaceRequest::class, [$requestId]);
            }

            if ($request->customer_account_id !== $customer->id) {
                throw new DomainException(
                    'Only the request owner may cancel this request.'
                );
            }

            if ($this->expireLockedRequestIfDue($request)) {
                return null;
            }

            if ($request->status !== MarketplaceRequest::STATUS_OPEN) {
                throw new DomainException(
                    'Only open requests can be cancelled.'
                );
            }

            $now = now();

            $request->forceFill([
                'status' => MarketplaceRequest::STATUS_CANCELLED,
                'cancelled_at' => $now,
            ])->save();

            MarketplaceQuotation::query()
                ->where('request_id', $request->request_id)
                ->where(
                    'status',
                    MarketplaceQuotation::STATUS_SUBMITTED
                )
                ->update([
                    'status' => MarketplaceQuotation::STATUS_NOT_SELECTED,
                    'updated_at' => $now,
                ]);

            return $request->refresh();
        });

        if (!$result) {
            throw new DomainException('This request has expired.');
        }

        return $result;
    }

    public function submitQuotation(
        Account $runner,
        string $requestId,
        float $amount,
        string $currency = 'MYR',
        ?string $message = null
    ): MarketplaceQuotation {
        $this->expireDueItems();

        if ($amount <= 0) {
            throw new DomainException(
                'Quotation amount must be greater than zero.'
            );
        }

        $currency = strtoupper(trim($currency));

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException(
                'Currency must be a valid three-letter code.'
            );
        }

        $result = DB::transaction(function () use (
            $runner,
            $requestId,
            $amount,
            $currency,
            $message
        ): ?MarketplaceQuotation {
            $request = MarketplaceRequest::query()
                ->whereKey($requestId)
                ->lockForUpdate()
                ->first();

            if (!$request) {
                throw (new ModelNotFoundException())
                    ->setModel(MarketplaceRequest::class, [$requestId]);
            }

            if ($this->expireLockedRequestIfDue($request)) {
                return null;
            }

            if ($request->status !== MarketplaceRequest::STATUS_OPEN) {
                throw new DomainException(
                    'This request is no longer accepting quotations.'
                );
            }

            $lockedRunner = Account::query()
                ->whereKey($runner->id)
                ->lockForUpdate()
                ->first();

            if (
                !$lockedRunner
                || !$this->isEligibleRunner($lockedRunner)
            ) {
                throw new DomainException(
                    'Only active and approved runners may submit quotations.'
                );
            }

            if ($lockedRunner->id === $request->customer_account_id) {
                throw new DomainException(
                    'A customer cannot quote on their own request.'
                );
            }

            if (
                $request->target_runner_account_id !== null
                && $request->target_runner_account_id !== $lockedRunner->id
            ) {
                throw new DomainException(
                    'This request is targeted at another runner.'
                );
            }

            $existingQuotation = MarketplaceQuotation::query()
                ->where('request_id', $request->request_id)
                ->where('runner_account_id', $lockedRunner->id)
                ->where('status', MarketplaceQuotation::STATUS_SUBMITTED)
                ->exists();

            if ($existingQuotation) {
                throw new DomainException(
                    'This runner already has a submitted quotation.'
                );
            }

            return MarketplaceQuotation::query()->create([
                'quotation_id' => (string) Str::uuid(),
                'request_id' => $request->request_id,
                'runner_account_id' => $lockedRunner->id,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
                'message' => $message,
                'status' => MarketplaceQuotation::STATUS_SUBMITTED,
            ]);
        });

        if (!$result) {
            throw new DomainException('This request has expired.');
        }

        return $result;
    }

    public function withdrawQuotation(
        Account $runner,
        string $requestId,
        string $quotationId
    ): MarketplaceQuotation {
        $this->expireDueItems();

        $result = DB::transaction(function () use (
            $runner,
            $requestId,
            $quotationId
        ): ?MarketplaceQuotation {
            $request = MarketplaceRequest::query()
                ->whereKey($requestId)
                ->lockForUpdate()
                ->first();

            if (!$request) {
                throw (new ModelNotFoundException())
                    ->setModel(MarketplaceRequest::class, [$requestId]);
            }

            if ($this->expireLockedRequestIfDue($request)) {
                return null;
            }

            if ($request->status !== MarketplaceRequest::STATUS_OPEN) {
                throw new DomainException(
                    'Quotations can only be withdrawn while the request is open.'
                );
            }

            $quotation = MarketplaceQuotation::query()
                ->whereKey($quotationId)
                ->where('request_id', $request->request_id)
                ->lockForUpdate()
                ->first();

            if (!$quotation) {
                throw (new ModelNotFoundException())
                    ->setModel(MarketplaceQuotation::class, [$quotationId]);
            }

            if ($quotation->runner_account_id !== $runner->id) {
                throw new DomainException(
                    'Only the quotation owner may withdraw it.'
                );
            }

            if (
                $quotation->status
                === MarketplaceQuotation::STATUS_EXPIRED
            ) {
                throw new DomainException('This quotation has expired.');
            }

            if (
                $quotation->status
                !== MarketplaceQuotation::STATUS_SUBMITTED
            ) {
                throw new DomainException(
                    'Only submitted quotations can be withdrawn.'
                );
            }

            if (
                $quotation->expires_at
                && $quotation->expires_at->lessThanOrEqualTo(now())
            ) {
                $quotation->forceFill([
                    'status' => MarketplaceQuotation::STATUS_EXPIRED,
                ])->save();

                return $quotation->refresh();
            }

            $quotation->forceFill([
                'status' => MarketplaceQuotation::STATUS_WITHDRAWN,
                'withdrawn_at' => now(),
            ])->save();

            return $quotation->refresh();
        });

        if (!$result) {
            throw new DomainException('This request has expired.');
        }

        if ($result->status === MarketplaceQuotation::STATUS_EXPIRED) {
            throw new DomainException('This quotation has expired.');
        }

        return $result;
    }

    public function awardQuotation(
        Account $customer,
        string $requestId,
        string $quotationId
    ): CoreTask {
        $this->expireDueItems();

        $result = DB::transaction(function () use (
            $customer,
            $requestId,
            $quotationId
        ): CoreTask|string|null {
            $request = MarketplaceRequest::query()
                ->whereKey($requestId)
                ->lockForUpdate()
                ->first();

            if (!$request) {
                throw (new ModelNotFoundException())
                    ->setModel(MarketplaceRequest::class, [$requestId]);
            }

            if ($request->customer_account_id !== $customer->id) {
                throw new DomainException(
                    'Only the request owner may select a quotation.'
                );
            }

            if (
                $request->status === MarketplaceRequest::STATUS_OPEN
                && $this->expireLockedRequestIfDue($request)
            ) {
                return null;
            }

            $quotation = MarketplaceQuotation::query()
                ->whereKey($quotationId)
                ->where('request_id', $request->request_id)
                ->lockForUpdate()
                ->first();

            if (!$quotation) {
                throw (new ModelNotFoundException())
                    ->setModel(MarketplaceQuotation::class, [$quotationId]);
            }

            if ($request->status === MarketplaceRequest::STATUS_AWARDED) {
                if (
                    $request->winning_quotation_id
                    !== $quotation->quotation_id
                ) {
                    throw new DomainException(
                        'Another quotation has already been awarded.'
                    );
                }

                $existingTask = CoreTask::query()
                    ->where('module_key', 'Marketplace')
                    ->where('task_type', 'MARKETPLACE_SERVICE_REQUEST')
                    ->where('source_id', $request->request_id)
                    ->first();

                if (!$existingTask) {
                    throw new DomainException(
                        'Awarded request has no registered Core Task.'
                    );
                }

                return $existingTask;
            }

            if ($request->status !== MarketplaceRequest::STATUS_OPEN) {
                throw new DomainException(
                    'This request cannot be awarded in its current state.'
                );
            }

            if (
                $quotation->status
                === MarketplaceQuotation::STATUS_EXPIRED
            ) {
                throw new DomainException('This quotation has expired.');
            }

            if (
                $quotation->status
                !== MarketplaceQuotation::STATUS_SUBMITTED
            ) {
                throw new DomainException(
                    'The selected quotation is no longer available.'
                );
            }

            if (
                $quotation->expires_at
                && $quotation->expires_at->lessThanOrEqualTo(now())
            ) {
                $quotation->forceFill([
                    'status' => MarketplaceQuotation::STATUS_EXPIRED,
                ])->save();

                return 'quotation_expired';
            }

            // Lock the runner account so eligibility is checked against
            // the current account row within this transaction.
            $runner = Account::query()
                ->whereKey($quotation->runner_account_id)
                ->lockForUpdate()
                ->first();

            if (!$runner || !$this->isEligibleRunner($runner)) {
                throw new DomainException(
                    'The quoted runner is no longer active and approved.'
                );
            }

            if (
                $request->target_runner_account_id !== null
                && $request->target_runner_account_id !== $runner->id
            ) {
                throw new DomainException(
                    'The quotation does not belong to the targeted runner.'
                );
            }

            $task = $this->coreTaskRegistry->register(
                'Marketplace',
                'MARKETPLACE_SERVICE_REQUEST',
                'marketplace_request',
                $request->request_id,
                $customer->id
            );

            $now = now();

            $task = $this->coreTaskAssigner->assign(
                $task->task_id,
                $runner->id
            );

            $request->forceFill([
                'status' => MarketplaceRequest::STATUS_AWARDED,
                'winning_quotation_id' => $quotation->quotation_id,
                'awarded_at' => $now,
            ])->save();

            $quotation->forceFill([
                'status' => MarketplaceQuotation::STATUS_ACCEPTED,
                'accepted_at' => $now,
            ])->save();

            MarketplaceQuotation::query()
                ->where('request_id', $request->request_id)
                ->where('quotation_id', '!=', $quotation->quotation_id)
                ->where('status', MarketplaceQuotation::STATUS_SUBMITTED)
                ->update([
                    'status' => MarketplaceQuotation::STATUS_NOT_SELECTED,
                    'updated_at' => $now,
                ]);

            return $task->refresh();
        });

        if ($result === null) {
            throw new DomainException('This request has expired.');
        }

        if ($result === 'quotation_expired') {
            throw new DomainException('This quotation has expired.');
        }

        return $result;
    }

    private function expireLockedRequestIfDue(
        MarketplaceRequest $request
    ): bool {
        if (
            $request->status !== MarketplaceRequest::STATUS_OPEN
            || !$request->expires_at
            || $request->expires_at->isFuture()
        ) {
            return false;
        }

        $now = now();

        $request->forceFill([
            'status' => MarketplaceRequest::STATUS_EXPIRED,
        ])->save();

        MarketplaceQuotation::query()
            ->where('request_id', $request->request_id)
            ->where(
                'status',
                MarketplaceQuotation::STATUS_SUBMITTED
            )
            ->update([
                'status' => MarketplaceQuotation::STATUS_EXPIRED,
                'updated_at' => $now,
            ]);

        return true;
    }

    private function isEligibleRunner(Account $account): bool
    {
        return $account->status === 'ACTIVE'
            && $account->runner_status === Account::RUNNER_STATUS_APPROVED;
    }
}
