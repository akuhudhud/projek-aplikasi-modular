<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CoreTaskLifecycle;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CoreTask;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CoreTaskController extends Controller
{
    public function __construct(
        private CoreTaskLifecycle $lifecycle
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->taskListResponse($request, false);
    }

    public function runnerIndex(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$account instanceof Account) {
            return $this->error('Unauthorized.', 401);
        }

        if (!$this->isEligibleRunner($account)) {
            return $this->error(
                'Only active and approved runners can view assigned tasks.',
                403
            );
        }

        return $this->taskListResponse($request, true);
    }

    private function taskListResponse(
        Request $request,
        bool $runnerMode
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account instanceof Account) {
            return $this->error('Unauthorized.', 401);
        }

        $query = CoreTask::query();

        if ($runnerMode) {
            $view = $request->route('view')
                ?? $request->query('view', 'active');

            if (!in_array($view, ['active', 'history'], true)) {
                return $this->error('Invalid task view.', 422);
            }

            $query->where('assigned_account_id', $account->id);

            if ($view === 'history') {
                $query->whereIn('generic_status', [
                    CoreTask::STATUS_COMPLETED,
                    CoreTask::STATUS_CANCELLED,
                ]);
            } else {
                $query->whereIn('generic_status', [
                    CoreTask::STATUS_ASSIGNED,
                    CoreTask::STATUS_IN_PROGRESS,
                ]);
            }
        } else {
            $isPrivileged = in_array($account->role, [
                'ROOT_SUPER_ADMIN',
                'SUPER_ADMIN',
            ], true);

            if (!$isPrivileged) {
                $query->where('assigned_account_id', $account->id);
            }
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
            'arrived_at',
            'completed_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ];

        if (
            !$runnerMode
            && in_array($account->role, [
                'ROOT_SUPER_ADMIN',
                'SUPER_ADMIN',
            ], true)
        ) {
            $columns[] = 'created_by_account_id';
        }

        $completedTotal = null;

        if ($runnerMode && $view === 'history') {
            $completedTotal = CoreTask::query()
                ->where('assigned_account_id', $account->id)
                ->where('generic_status', CoreTask::STATUS_COMPLETED)
                ->count();
        }

        $tasks = $query
            ->orderByDesc('created_at')
            ->orderBy('task_id')
            ->paginate(50, $columns);

        $data = [
            'tasks' => $tasks->items(),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'last_page' => $tasks->lastPage(),
            ],
        ];

        if ($completedTotal !== null) {
            $data['summary'] = [
                'completed_total' => $completedTotal,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
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

    public function arrive(
        Request $request,
        string $taskId
    ): JsonResponse {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $account = $request->attributes->get('account');

        if (!$account instanceof Account) {
            return $this->error('Unauthorized.', 401);
        }

        try {
            $task = $this->lifecycle->arrive(
                $taskId,
                $account->id,
                (float) $validated['latitude'],
                (float) $validated['longitude']
            );
        } catch (ModelNotFoundException) {
            return $this->error('Task or account not found.', 404);
        } catch (DomainException | InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->taskResponse($task);
    }
