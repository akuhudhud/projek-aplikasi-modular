<?php

namespace Modules\PickAndDrop\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PickAndDrop\Http\Requests\StorePickAndDropRequest;
use Modules\PickAndDrop\Models\PickAndDropRequest as PickAndDropRequestModel;
use Modules\PickAndDrop\Models\PickAndDropTask;

class PickAndDropRequestController extends Controller
{
    /**
     * Store a customer request and its corresponding task atomically.
     */
    public function store(
        StorePickAndDropRequest $request
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $payload = $request->validatedPayload();

        $pickAndDropRequest = DB::transaction(
            function () use ($account, $payload): PickAndDropRequestModel {
                $requestRecord = PickAndDropRequestModel::create([
                    'id' => (string) Str::uuid(),
                    'customer_account_id' => $account->id,
                    'category' => $payload['category'],
                    'item_type' => $payload['item_type'] ?? null,
                    'item_description' => $payload['item_description'],
                    'pickup_location_snapshot' => $payload['pickup_location_snapshot'],
                    'pickup_contact_name' => $payload['pickup_contact_name'],
                    'pickup_contact_phone' => $payload['pickup_contact_phone'],
                    'delivery_location_snapshot' => $payload['delivery_location_snapshot'],
                    'recipient_name' => $payload['recipient_name'],
                    'recipient_phone' => $payload['recipient_phone'],
                    'additional_instructions' => $payload['additional_instructions'] ?? null,
                    'status' => 'SUBMITTED',
                ]);

                PickAndDropTask::create([
                    'id' => (string) Str::uuid(),
                    'request_id' => $requestRecord->id,
                    'status' => 'AVAILABLE',
                ]);

                return $requestRecord;
            }
        );

        $pickAndDropRequest->load('task');

        return response()->json([
            'success' => true,
            'message' => 'Pick and Drop request created successfully.',
            'data' => [
                'request' => [
                    'id' => $pickAndDropRequest->id,
                    'customer_account_id' => $pickAndDropRequest->customer_account_id,
                    'category' => $pickAndDropRequest->category,
                    'item_type' => $pickAndDropRequest->item_type,
                    'item_description' => $pickAndDropRequest->item_description,
                    'pickup_location' => $pickAndDropRequest->pickup_location_snapshot,
                    'pickup_contact_name' => $pickAndDropRequest->pickup_contact_name,
                    'pickup_contact_phone' => $pickAndDropRequest->pickup_contact_phone,
                    'delivery_location' => $pickAndDropRequest->delivery_location_snapshot,
                    'recipient_name' => $pickAndDropRequest->recipient_name,
                    'recipient_phone' => $pickAndDropRequest->recipient_phone,
                    'additional_instructions' => $pickAndDropRequest->additional_instructions,
                    'status' => $pickAndDropRequest->status,
                    'created_at' => $pickAndDropRequest->created_at,
                ],
                'task' => [
                    'id' => $pickAndDropRequest->task->id,
                    'request_id' => $pickAndDropRequest->task->request_id,
                    'status' => $pickAndDropRequest->task->status,
                    'created_at' => $pickAndDropRequest->task->created_at,
                ],
            ],
        ], 201);
    }
}
