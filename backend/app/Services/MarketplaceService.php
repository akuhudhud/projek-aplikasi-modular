<?php

namespace App\Services;

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
        private CoreTaskRegistry $coreTaskRegistry
    ) {
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

        $serviceType = trim((string) ($data['service_type'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($serviceType === '' || mb_strlen($serviceType) > 64) {
            throw new DomainException(
                'Service type is required and must not exceed 64 characters.'
            );
        }

        if ($description === '') {
            throw new DomainException('Request description is required.');
        }

        return DB::transaction(function () use (
            $customer,
            $sourceType,
            $targetRunnerSlug,
            $serviceType,
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

            return MarketplaceRequest::query()->create([
                'request_id' => (string) Str::uuid(),
                'customer_account_id' => $customer->id,
                'target_runner_account_id' => $targetRunnerId,
                'source_type' => $sourceType,
                'service_type' => $serviceType,
                'description' => $description,
                'pickup_location_snapshot' =>
                    $data['pickup_location_snapshot'] ?? null,
                'delivery_location_snapshot' =>
                    $data['delivery_location_snapshot'] ?? null,
                'status' => MarketplaceRequest::STATUS_OPEN,
                'expires_at' => $data['expires_at'] ?? null,
            ]);
        });
    }

    public function submitQuotation(
        Account $runner,
        string $requestId,
        float $amount,
        string $currency = 'MYR',
        ?string $message = null
    ): MarketplaceQuotation {
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

        return DB::transaction(function () use (
            $runner,
            $requestId,
            $amount,
            $currency,
            $message
        ): MarketplaceQuotation {
            $request = MarketplaceRequest::query()
                ->whereKey($requestId)
                ->lockForUpdate()
                ->first();

            if (!$request) {
                throw (new ModelNotFoundException())
                    ->setModel(MarketplaceRequest::class, [$requestId]);
            }

            if ($request->status !== MarketplaceRequest::STATUS_OPEN) {
                throw new DomainException(
                    'This request is no longer accepting quotations.'
                );
            }

            if ($request->expires_at && $request->expires_at->isPast()) {
                throw new DomainException('This request has expired.');
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
    }

    public function awardQuotation(
        Account $customer,
        string $requestId,
        string $quotationId
    ): CoreTask {
        return DB::transaction(function () use (
            $customer,
            $requestId,
            $quotationId
        ): CoreTask {
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

            $quotation = MarketplaceQuotation::query()
                ->whereKey($quotationId)
                ->where('request_id', $request->request_id)
                ->lockForUpdate()
                ->first();

            if (!$quotation) {
                throw (new ModelNotFoundException())
                    ->setModel(MarketplaceQuotation::class, [$quotationId]);
            }

            /*
             * A retry of the same winning quotation returns the existing task.
             * A different quotation can never replace an awarded quotation.
             */
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

            if ($request->expires_at && $request->expires_at->isPast()) {
                throw new DomainException('This request has expired.');
            }

            if (
                $quotation->status
                !== MarketplaceQuotation::STATUS_SUBMITTED
            ) {
                throw new DomainException(
                    'The selected quotation is no longer available.'
                );
            }

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

            /*
             * All state changes and Core Task registration occur inside
             * this outer transaction.
             */
            $task = $this->coreTaskRegistry->register(
                'Marketplace',
                'MARKETPLACE_SERVICE_REQUEST',
                'marketplace_request',
                $request->request_id,
                $customer->id
            );

            $task->forceFill([
                'assigned_account_id' => $runner->id,
                'generic_status' => CoreTask::STATUS_ASSIGNED,
                'assigned_at' => now(),
            ])->save();

            $now = now();

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
    }

    private function isEligibleRunner(Account $account): bool
    {
        return $account->status === 'ACTIVE'
            && $account->runner_status === Account::RUNNER_STATUS_APPROVED;
    }
}
