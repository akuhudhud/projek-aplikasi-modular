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
use Illuminate\Support\Facades\DB;

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

        // Luputkan permintaan dan quotation sebelum senarai dipaparkan.
        $this->marketplaceService->expireDueItems();

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

        // Luputkan permintaan dan quotation sebelum butiran dipaparkan.
        $this->marketplaceService->expireDueItems();

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

        /*
         * Ambil profil awam yang diterbitkan sahaja.
         *
         * Akaun Runner mesti masih ACTIVE dan APPROVED.
         * account_id digunakan untuk padanan dalaman sahaja
         * dan tidak dimasukkan ke dalam objek runner_profile.
         */
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
                ->where(
                    'runner_public_profiles.is_published',
                    true
                )
                ->where('accounts.status', 'ACTIVE')
                ->where(
                    'accounts.runner_status',
                    Account::RUNNER_STATUS_APPROVED
                )
                ->get([
                    'runner_public_profiles.account_id',
                    'runner_public_profiles.public_slug',
                    'runner_public_profiles.display_name',
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

        $quotationsWithProfiles = $quotations
            ->map(function ($quotation) use (
                $publicProfiles,
                $ratingSummaries
            ): array {
                $quotationData = $quotation->toArray();

                $profile = $publicProfiles->get(
                    $quotation->runner_account_id
                );

                // ID akaun tidak boleh dihantar kepada pelanggan.
                unset($quotationData['runner_account_id']);

                // Profil tidak diterbitkan atau Runner tidak layak.
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
                    'display_name' => $profile->display_name,
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
                'quotations' => $quotationsWithProfiles,
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
