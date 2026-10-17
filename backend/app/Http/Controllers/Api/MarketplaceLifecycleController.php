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

class MarketplaceLifecycleController extends Controller
{
    public function __construct(
        private MarketplaceService $marketplaceService
    ) {
    }

    public function cancelRequest(
        Request $request,
        string $requestId
    ): JsonResponse {
        /** @var Account|null $account */
        $account = $request->attributes->get('account');

        if (!$account) {
            return $this->error('Unauthorized.', 401);
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
                        'customer_account_id',
                        'source_type',
                        'service_type',
                        'status',
                        'cancelled_at',
                        'updated_at',
                    ]),
                ],
            ]);
        } catch (ModelNotFoundException) {
            return $this->error(
                'Marketplace request not found.',
                404
            );
        } catch (DomainException $exception) {
            return $this->error(
                $exception->getMessage(),
                422
            );
        }
    }

    public function withdrawQuotation(
        Request $request,
        string $requestId,
        string $quotationId
    ): JsonResponse {
        /** @var Account|null $account */
        $account = $request->attributes->get('account');

        if (!$account) {
            return $this->error('Unauthorized.', 401);
        }

        try {
            $quotation = $this->marketplaceService
                ->withdrawQuotation(
                    $account,
                    $requestId,
                    $quotationId
                );

            return response()->json([
                'success' => true,
                'message' => 'Quotation withdrawn.',
                'data' => [
                    'quotation' => $quotation->only([
                        'quotation_id',
                        'request_id',
                        'runner_account_id',
                        'amount',
                        'currency',
                        'status',
                        'withdrawn_at',
                        'updated_at',
                    ]),
                ],
            ]);
        } catch (ModelNotFoundException) {
            return $this->error(
                'Marketplace request or quotation not found.',
                404
            );
        } catch (DomainException $exception) {
            return $this->error(
                $exception->getMessage(),
                422
            );
        }
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
