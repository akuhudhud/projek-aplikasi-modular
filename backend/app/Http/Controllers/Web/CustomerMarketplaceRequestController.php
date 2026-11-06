<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\MarketplaceRequest;
use App\Services\MarketplaceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerMarketplaceRequestController extends Controller
{
    public function __construct(
        private MarketplaceService $marketplaceService
    ) {
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
            'delivery_location_snapshot' => [
                'required_if:task_class,PICKUP_DELIVERY',
                'array',
            ],
            'task_location_snapshot' => [
                'required_if:task_class,NON_DELIVERY',
                'array',
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
}
