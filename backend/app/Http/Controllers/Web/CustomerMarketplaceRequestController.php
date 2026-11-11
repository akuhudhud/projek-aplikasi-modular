<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\MarketplaceRequest;
use App\Services\MarketplaceService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerMarketplaceRequestController extends Controller
{
    public function __construct(
        private MarketplaceService $marketplaceService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$account instanceof Account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $requests = MarketplaceRequest::query()
            ->where('customer_account_id', $account->id)
            ->orderByDesc('created_at')
            ->orderBy('request_id')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => [
                'requests' => $requests->getCollection()
                    ->map(fn (MarketplaceRequest $item) => $item->only([
                        'request_id',
                        'task_class',
                        'description',
                        'pickup_location_snapshot',
                        'delivery_location_snapshot',
                        'task_location_snapshot',
                        'status',
                        'expires_at',
                        'awarded_at',
                        'cancelled_at',
                        'created_at',
                        'updated_at',
                    ]))
                    ->values(),
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                    'last_page' => $requests->lastPage(),
                ],
            ],
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$account instanceof Account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
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
                    'request' => $marketplaceRequest->only([
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
                        'expires_at',
                        'created_at',
                    ]),
                ],
            ], 201);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function show(
        Request $request,
        string $requestId
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account instanceof Account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $marketplaceRequest = MarketplaceRequest::query()
            ->where('request_id', $requestId)
            ->where('customer_account_id', $account->id)
            ->first();

        if (!$marketplaceRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Marketplace request not found.',
            ], 404);
        }

        $quotations = $marketplaceRequest->quotations()
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

        return response()->json([
            'success' => true,
            'data' => [
                'request' => $marketplaceRequest->only([
                    'request_id',
                    'task_class',
                    'description',
                    'pickup_location_snapshot',
                    'delivery_location_snapshot',
                    'task_location_snapshot',
                    'status',
                    'expires_at',
                    'awarded_at',
                    'cancelled_at',
                    'winning_quotation_id',
                    'created_at',
                    'updated_at',
                ]),
                'quotations' => $quotations,
            ],
        ], 200);
    }

    public function awardQuotation(
        Request $request,
        string $requestId
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account instanceof Account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $validated = $request->validate([
            'quotation_id' => [
                'required',
                'uuid',
            ],
        ]);

        try {
            $task = $this->marketplaceService->awardQuotation(
                $account,
                $requestId,
                $validated['quotation_id']
            );

            $marketplaceRequest = MarketplaceRequest::query()
                ->whereKey($requestId)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Quotation awarded successfully.',
                'data' => [
                    'request' => $marketplaceRequest->only([
                        'request_id',
                        'status',
                        'winning_quotation_id',
                        'awarded_at',
                        'updated_at',
                    ]),
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
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Marketplace request or quotation not found.',
            ], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function cancel(
        Request $request,
        string $requestId
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account instanceof Account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {
            $marketplaceRequest = $this->marketplaceService
                ->cancelRequest($account, $requestId);

            return response()->json([
                'success' => true,
                'message' => 'Marketplace request cancelled.',
                'data' => [
                    'request' => $marketplaceRequest->only([
                        'request_id',
                        'status',
                        'cancelled_at',
                        'updated_at',
                    ]),
                ],
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Marketplace request not found.',
            ], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
