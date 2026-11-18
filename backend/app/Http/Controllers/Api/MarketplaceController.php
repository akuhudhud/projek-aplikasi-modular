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
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    public function __construct(
        private MarketplaceService $marketplaceService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
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
            if (!$this->isEligibleRunner($account)) {
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
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now());
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
                        $this->requestPayload(
                            $item,
                            $view === 'mine'
                        )
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

    public function show(Request $request, string $requestId): JsonResponse
    {
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

        $isOwner =
            $marketplaceRequest->customer_account_id === $account->id;

        if (!$isOwner) {
            if (!$this->isEligibleRunner($account)) {
                return $this->error('Marketplace request not found.', 404);
            }

            $isTargetRunner =
                $marketplaceRequest->target_runner_account_id
                === $account->id;

            $canViewOpenRequest =
                $marketplaceRequest->status
                    === MarketplaceRequest::STATUS_OPEN
                && (
                    $marketplaceRequest->target_runner_account_id === null
                    || $isTargetRunner
                )
                && $marketplaceRequest->expires_at !== null
                && $marketplaceRequest->expires_at->isFuture();

            $canViewAwardedRequest = false;

            if (
                $marketplaceRequest->status
                    === MarketplaceRequest::STATUS_AWARDED
                && $marketplaceRequest->winning_quotation_id !== null
            ) {
                $canViewAwardedRequest = MarketplaceQuotation::query()
                    ->where(
                        'quotation_id',
                        $marketplaceRequest->winning_quotation_id
                    )
                    ->where(
                        'request_id',
                        $marketplaceRequest->request_id
                    )
                    ->where(
                        'runner_account_id',
                        $account->id
                    )
                    ->where(
                        'status',
                        MarketplaceQuotation::STATUS_ACCEPTED
                    )
                    ->exists();
            }

            if (!$canViewOpenRequest && !$canViewAwardedRequest) {
                return $this->error(
                    'Marketplace request not found.',
                    404
                );
            }
        }

        $payload = $this->requestPayload(
            $marketplaceRequest,
            $isOwner
        );

        if ($isOwner) {
            $quotations = $marketplaceRequest
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

            $runnerAccountIds = $quotations
                ->pluck('runner_account_id')
                ->unique()
                ->values();

            $publicProfiles = collect();
            $ratingSummaries = collect();

            if ($runnerAccountIds->isNotEmpty()) {
                $publicProfiles = DB::table('runner_public_profiles')
                    ->join(
                        'accounts',
                        'accounts.id',
                        '=',
                        'runner_public_profiles.account_id'
                    )
                    ->whereIn(
                        'runner_public_profiles.account_id',
                        $runnerAccountIds
                    )
                    ->where('runner_public_profiles.is_published', true)
                    ->where('accounts.status', 'ACTIVE')
                    ->where(
                        'accounts.runner_status',
                        Account::RUNNER_STATUS_APPROVED
                    )
                    ->get([
                        'runner_public_profiles.account_id',
                        'runner_public_profiles.public_slug',
                        'accounts.profile_name',
                        'runner_public_profiles.bio',
                        'runner_public_profiles.base_location',
                        'runner_public_profiles.service_area',
                        'runner_public_profiles.services',
                    ])
                    ->keyBy('account_id');

                $ratingSummaries = DB::table('runner_task_ratings')
                    ->whereIn('runner_account_id', $runnerAccountIds)
                    ->selectRaw(
                        'runner_account_id, COUNT(*) as rating_count, AVG(rating) as rating_average'
                    )
                    ->groupBy('runner_account_id')
                    ->get()
                    ->keyBy('runner_account_id');
            }

            $payload['quotations'] = $quotations
                ->map(function ($quotation) use (
                    $publicProfiles,
                    $ratingSummaries
                ): array {
                    $quotationData = $quotation->toArray();

                    $profile = $publicProfiles->get(
                        $quotation->runner_account_id
                    );

                    unset($quotationData['runner_account_id']);

                    if ($profile === null) {
                        $quotationData['runner_profile'] = null;

                        return $quotationData;
                    }

                    $rating = $ratingSummaries->get(
                        $quotation->runner_account_id
                    );

                    $ratingCount = (int) ($rating->rating_count ?? 0);

                    $services = $profile->services;

                    if (is_string($services)) {
                        $decodedServices = json_decode($services, true);

                        $services = is_array($decodedServices)
                            ? $decodedServices
                            : [];
                    }

                    $quotationData['runner_profile'] = [
                        'public_slug' => $profile->public_slug,
                        'profile_name' => $profile->profile_name,
                        'bio' => $profile->bio,
                        'base_location' => $profile->base_location,
                        'service_area' => $profile->service_area,
                        'services' => is_array($services)
                            ? $services
                            : [],
                        'rating_average' => $ratingCount > 0
                            ? round(
                                (float) $rating->rating_average,
                                1
                            )
                            : null,
                        'rating_count' => $ratingCount,
                    ];

                    return $quotationData;
                })
                ->values();
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

    public function store(Request $request): JsonResponse
    {
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
            'target_runner_slug' => [
                'nullable',
                'string',
                'alpha_dash',
                'min:3',
                'max:50',
            ],
            'task_class' => [
                'required',
                'string',
                'in:PICKUP_DELIVERY,NON_DELIVERY',
            ],
            'description' => [
                'required',
                'string',
                'max:10000',
            ],
            'pickup_location_snapshot' => [
                'required_if:task_class,PICKUP_DELIVERY',
                'array',
            ],
            'pickup_location_snapshot.address' => [
                'required_if:task_class,PICKUP_DELIVERY',
                'string',
                'max:2000',
            ],
            'pickup_location_snapshot.contact_name' => [
                'nullable',
                'string',
                'max:150',
            ],
            'pickup_location_snapshot.phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'delivery_location_snapshot' => [
                'required_if:task_class,PICKUP_DELIVERY',
                'array',
            ],
            'delivery_location_snapshot.address' => [
                'required_if:task_class,PICKUP_DELIVERY',
                'string',
                'max:2000',
            ],
            'delivery_location_snapshot.contact_name' => [
                'nullable',
                'string',
                'max:150',
            ],
            'delivery_location_snapshot.phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'task_location_snapshot' => [
                'required_if:task_class,NON_DELIVERY',
                'array',
            ],
            'task_location_snapshot.address' => [
                'required_if:task_class,NON_DELIVERY',
                'string',
                'max:2000',
            ],
            'task_location_snapshot.contact_name' => [
                'nullable',
                'string',
                'max:150',
            ],
            'task_location_snapshot.phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'expires_at' => [
                'required',
                'date',
                'after:now',
                'before_or_equal:'.now()->addHours(24)->toDateTimeString(),
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

    public function submitQuotation(
        Request $request,
        string $requestId
    ): JsonResponse {
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

    public function awardQuotation(
        Request $request,
        string $requestId
    ): JsonResponse {
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

    private function isEligibleRunner(Account $account): bool
    {
        return $account->status === 'ACTIVE'
            && $account->runner_status === Account::RUNNER_STATUS_APPROVED;
    }

    private function requestPayload(
        MarketplaceRequest $marketplaceRequest,
        bool $includeInternalReferences = true
    ): array {
        $payload = $marketplaceRequest->only([
            'request_id',
            'customer_account_id',
            'target_runner_account_id',
            'source_type',
            'task_class',
            'description',
            'pickup_location_snapshot',
            'delivery_location_snapshot',
            'task_location_snapshot',
            'status',
            'winning_quotation_id',
            'expires_at',
            'awarded_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ]);

        if (!$includeInternalReferences) {
            unset(
                $payload['customer_account_id'],
                $payload['target_runner_account_id'],
                $payload['winning_quotation_id']
            );
        }

        return $payload;
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
