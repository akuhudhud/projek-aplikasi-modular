<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoreTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoreTaskController extends Controller
{
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

        $tasks = $query
            ->orderByDesc('created_at')
            ->orderBy('task_id')
            ->paginate(50, [
                'task_id',
                'module_key',
                'task_type',
                'source_type',
                'source_id',
                'created_by_account_id',
                'assigned_account_id',
                'generic_status',
                'assigned_at',
                'started_at',
                'completed_at',
                'cancelled_at',
                'created_at',
                'updated_at',
            ]);

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
}
