<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CoreTaskLifecycle;
use App\Http\Controllers\Controller;
use App\Models\CoreTask;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CoreTaskController extends Controller
{
    public function __construct(
        private CoreTaskLifecycle $lifecycle
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $isPrivileged = in_array($account->role, [
            'ROOT_SUPER_ADMIN',
            'SUPER_ADMIN',
        ], true);

        $query = CoreTask::query();

        if (!$isPrivileged) {
            $query->where(
                'assigned_account_id',
                $account->id
            );
        }

        $columns = [
            'task_id',
            'module_key',
            'task_type',
            'source_type',
            'source_id',
            'assigned_account_id',
            'generic_status',
            'assigned_at',
            'started_at',
            'completed_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ];

        // Internal creator reference is available to privileged admins only.
        if ($isPrivileged) {
            $columns[] = 'created_by_account_id';
        }

        $tasks = $query
            ->orderByDesc('created_at')
            ->orderBy('task_id')
            ->paginate(50, $columns);

        return response()->json([
            'success' => true,
            'data' => [
                'tasks' => $tasks->items(),
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                    'last_page' => $tasks->lastPage(),
                ],
            ],
        ], 200);
    }

    public function start(
        Request $request,
        string $taskId
    ): JsonResponse {
        return $this->performLifecycleAction(
            $request,
            $taskId,
            'start'
        );
    }

    public function complete(
        Request $request,
        string $taskId
    ): JsonResponse {
        return $this->performLifecycleAction(
            $request,
            $taskId,
            'complete'
        );
    }

    private function performLifecycleAction(
        Request $request,
        string $taskId,
        string $action
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {
            $task = $this->lifecycle->{$action}(
                $taskId,
                $account->id
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Task or account not found.',
            ], 404);
        } catch (DomainException | InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
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
                    'assigned_account_id',
                    'generic_status',
                    'assigned_at',
                    'started_at',
                    'completed_at',
                    'cancelled_at',
                    'created_at',
                    'updated_at',
                ]),
            ],
        ], 200);
    }
}
