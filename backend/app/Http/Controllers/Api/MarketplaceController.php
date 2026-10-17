<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Services\MarketplaceService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function __construct(
        private MarketplaceService $marketplaceService
    ) {
    }

    /**
     * List requests owned by the account or available to an approved runner.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Account|null $account */
        $account = $request->attributes->get('account');

        if (!$account) {
            return $this->error('Unauthorized.', 401);
        }

        $validated = $request->validate([
            'view' => ['sometimes', 'string', 'in:mine,available'],
        ]);

        $view = $validated['view'] ?? 'mine';

        $query = MarketplaceRequest::query();

        if ($view === 'mine') {
            $query->where('customer_account_id', $account->id);
        } else {
            if (
                $account->status !== 'ACTIVE'
                || $account->runner_status !== Account::RUNNER_STATUS_APPROVED
            ) {
                return $this->error(
                    'Only active and approved runners can browse available requests.',
                    403
                );
            }

            $query
                ->where('status', MarketplaceRequest::STATUS_OPEN)
                ->where(function ($builder) use ($account) {
                    $builder
                        ->whereNull('target_runner_account_id')
                        ->orWhere('target_runner_account_id', $account->id);
                })
                ->where(function ($builder) {
                    $builder
                        ->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        }

        $requests = $query
            ->orderByDesc('created_at')
            ->orderBy('request_id')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => [
                'requests' => $requests->getCollection()
                    ->map(fn (MarketplaceRequest $item) =>
                        $this->requestPayload($item)
                    )
                    ->values(),
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                    'last_page' => $requests->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Show a request to its owner or an eligible participant.
     */
    public function show(
        Request $request,
        string $requestId
    ): JsonResponse {
        /** @var Account|null $account */
        $account = $request->attributes->get('account');

        if (!$account) {
            return $this->error('Unauthorized.', 401);
        }

        $marketplaceRequest = MarketplaceRequest::query()
            ->whereKey($requestId)
            ->first();

        if (!$marketplaceRequest) {
            return $this->error('Marketplace request not found.', 404);
        }

        $isOwner = $marketplaceRequest->customer_account_id === $account->id;

        $isTargetRunner =
            $marketplaceRequest->target_runner_account_id === $account->id;

        $hasSubmittedQuotation = MarketplaceQuotation::query()
            ->where('request_id', $marketplaceRequest->request_id)
            ->where('runner_account_id', $account->id)
            ->exists();

        $canBrowseOpenRequest =
            $marketplaceRequest->status === MarketplaceRequest::STATUS_OPEN
            && $account->status === 'ACTIVE'
            && $account->runner_status === Account::RUNNER_STATUS_APPROVED
            && (
                $marketplaceRequest->target_runner_account_id === null
                || $isTargetRunner
            );

        if (
            !$isOwner
            && !$isTargetRunner
            && !$hasSubmittedQuotation
            && !$canBrowseOpenRequest
        ) {
            return $this->error('Marketplace request not found.', 404);
        }

        $payload = $this->requestPayload($marketplaceRequest);

        if ($isOwner) {
            $payload['quotations'] = $marketplaceRequest
                ->quotations()
                ->orderBy('created_at')
                ->get([
                    'quotation_id',
                    'request_id',
                    'runner_account_id',
                    'amount',
                    'currency',
                    'message',
                    'status',
                    'expires_at',
                    'accepted_at',
                    'withdrawn_at',
                    'created_at',
                ]);
        } else {
            $payload['my_quotations'] = $marketplaceRequest
                ->quotations()
                ->where('runner_account_id', $account->id)
                ->orderByDesc('created_at')
                ->get([
                    'quotation_id',
                    'amount',
                    'currency',
                    'message',
                    'status',
                    'expires_at',
                    'created_at',
                ]);
        }

        return response()->json([
            'success' => true,
            'data' => ['request' => $payload],
        ]);
    }

    /**
     * Create a new Marketplace request.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Account|null $account */
        $account = $request->attributes->get('account');

        if (!$account) {
            return $this->error('Unauthorized.', 401);
        }

        $validated = $request->validate([
            'source_type' => [
                'sometimes',
                'string',
                'in:marketplace,runner_profile_link',
            ],
            'target_runner_account_id' => [
                'nullable',
                'uuid',
            ],
            'service_type' => [
                'required',
                'string',
                'max:64',
            ],
            'description' => [
                'required',
                'string',
                'max:10000',
            ],
            'pickup_location_snapshot' => [
                'nullable',
                'array',
            ],
            'delivery_location_snapshot' => [
                'nullable',
                'array',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:now',
            ],
        ]);

        try {
            $marketplaceRequest = $this->marketplaceService
                ->createRequest($account, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Marketplace request created.',
                'data' => [
                    'request' => $this->requestPayload($marketplaceRequest),
                ],
            ], 201);
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    /**
     * Submit a quotation for a request.
     */
    public function submitQuotation(
        Request $request,
        string $requestId
    ): JsonResponse {
        /** @var Account|null $account */
        $account = $request->attributes->get('account');

        if (!$account) {
            return $this->error('Unauthorized.', 401);
        }

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'max:9999999999.99',
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
                'alpha',
            ],
            'message' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        try {
            $quotation = $this->marketplaceService->submitQuotation(
                $account,
                $requestId,
                (float) $validated['amount'],
                $validated['currency'] ?? 'MYR',
                $validated['message'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Quotation submitted.',
                'data' => [
                    'quotation' => $quotation->only([
                        'quotation_id',
                        'request_id',
                        'runner_account_id',
                        'amount',
                        'currency',
                        'message',
                        'status',
                        'created_at',
                    ]),
                ],
            ], 201);
        } catch (ModelNotFoundException) {
            return $this->error('Marketplace request not found.', 404);
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    /**
     * Select a quotation. The service performs the atomic award operation.
     */
    public function awardQuotation(
        Request $request,
        string $requestId
    ): JsonResponse {
        /** @var Account|null $account */
        $account = $request->attributes->get('account');

        if (!$account) {
            return $this->error('Unauthorized.', 401);
        }

        $validated = $request->validate([
            'quotation_id' => ['required', 'uuid'],
        ]);

        try {
            $task = $this->marketplaceService->awardQuotation(
                $account,
                $requestId,
                $validated['quotation_id']
            );

            return response()->json([
                'success' => true,
                'message' => 'Quotation awarded successfully.',
                'data' => [
                    'task' => $task->only([
                        'task_id',
                        'module_key',
                        'task_type',
                        'source_type',
                        'source_id',
                        'assigned_account_id',
                        'generic_status',
                        'assigned_at',
                        'created_at',
                    ]),
                ],
            ]);
        } catch (ModelNotFoundException) {
            return $this->error(
                'Marketplace request or quotation not found.',
                404
            );
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    private function requestPayload(
        MarketplaceRequest $marketplaceRequest
    ): array {
        return $marketplaceRequest->only([
            'request_id',
            'customer_account_id',
            'target_runner_account_id',
            'source_type',
            'service_type',
            'description',
            'pickup_location_snapshot',
            'delivery_location_snapshot',
            'status',
            'winning_quotation_id',
            'expires_at',
            'awarded_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ]);
    }

    private function error(
        string $message,
        int $status
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
