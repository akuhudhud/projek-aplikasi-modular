
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CoreTask;
use App\Models\MarketplaceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTaskController extends Controller
{
    private const MODULE_KEY = 'Marketplace';

    private const TASK_TYPE = 'MARKETPLACE_SERVICE_REQUEST';

    private const SOURCE_TYPE = 'marketplace_request';

    public function index(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $ownedRequestIds = MarketplaceRequest::query()
            ->select('request_id')
            ->where('customer_account_id', $account->id);

        $taskQuery = CoreTask::query()
            ->where('module_key', self::MODULE_KEY)
            ->where('task_type', self::TASK_TYPE)
            ->where('source_type', self::SOURCE_TYPE)
            ->whereIn('source_id', $ownedRequestIds);

        $activeTaskTotal = (clone $taskQuery)
            ->whereIn('generic_status', [
                CoreTask::STATUS_ASSIGNED,
                CoreTask::STATUS_IN_PROGRESS,
            ])
            ->count();

        $tasks = $taskQuery
            ->orderByDesc('created_at')
            ->orderBy('task_id')
            ->paginate(25, [
                'task_id',
                'module_key',
                'task_type',
                'source_type',
                'source_id',
                'generic_status',
                'assigned_at',
                'started_at',
                'completed_at',
                'cancelled_at',
                'created_at',
                'updated_at',
            ]);

        $requests = MarketplaceRequest::query()
            ->where('customer_account_id', $account->id)
            ->whereIn(
                'request_id',
                $tasks->getCollection()->pluck('source_id')
            )
            ->get([
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
            ])
            ->keyBy('request_id');

        $items = $tasks->getCollection()->map(
            function (CoreTask $task) use ($requests): array {
                $marketplaceRequest = $requests->get(
                    $task->source_id
                );

                return [
                    'task' => $task->only([
                        'task_id',
                        'module_key',
                        'task_type',
                        'source_type',
                        'source_id',
                        'generic_status',
                        'assigned_at',
                        'started_at',
                        'completed_at',
                        'cancelled_at',
                        'created_at',
                        'updated_at',
                    ]),
                    'request' => $marketplaceRequest?->only([
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
                    ]),
                ];
            }
        )->values();

        return response()->json([
            'success' => true,
            'data' => [
                'tasks' => $items,
                'summary' => [
                    'active_total' => $activeTaskTotal,
                ],
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                    'last_page' => $tasks->lastPage(),
                ],
            ],
        ], 200);
    }

    public function show(
        Request $request,
        string $taskId
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $ownedRequestIds = MarketplaceRequest::query()
            ->select('request_id')
            ->where('customer_account_id', $account->id);

        $task = CoreTask::query()
            ->whereKey($taskId)
            ->where('module_key', self::MODULE_KEY)
            ->where('task_type', self::TASK_TYPE)
            ->where('source_type', self::SOURCE_TYPE)
            ->whereIn('source_id', $ownedRequestIds)
            ->first();

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        $marketplaceRequest = MarketplaceRequest::query()
            ->whereKey($task->source_id)
            ->where('customer_account_id', $account->id)
            ->first();

        if (!$marketplaceRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'task' => $task->only([
                    'task_id',
                    'module_key',
                    'task_type',
                    'source_type',
                    'source_id',
                    'generic_status',
                    'assigned_at',
                    'started_at',
                    'completed_at',
                    'cancelled_at',
                    'created_at',
                    'updated_at',
                ]),
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
                ]),
            ],
        ], 200);
    }
}
