<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CoreTask;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Account|null $admin */
        $admin = $request->attributes->get('account');

        if (
            !$admin
            || !in_array(
                $admin->role,
                ['ROOT_SUPER_ADMIN', 'SUPER_ADMIN'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Akses dashboard pentadbir tidak dibenarkan.',
            ], 403);
        }

        $runnerStatuses = [
            Account::RUNNER_STATUS_PENDING,
            Account::RUNNER_STATUS_APPROVED,
            Account::RUNNER_STATUS_REJECTED,
            Account::RUNNER_STATUS_SUSPENDED,
        ];

        $accountQuery = Account::query()
            ->where('role', 'USER');

        $runnerQuery = Account::query()
            ->where('role', 'USER')
            ->whereIn('runner_status', $runnerStatuses);

        $runnerStatusCounts = [];

        foreach ($runnerStatuses as $status) {
            $runnerStatusCounts[$status] = (clone $runnerQuery)
                ->where('runner_status', $status)
                ->count();
        }

        $taskStatuses = [
            CoreTask::STATUS_OPEN,
            CoreTask::STATUS_ASSIGNED,
            CoreTask::STATUS_IN_PROGRESS,
            CoreTask::STATUS_COMPLETED,
            CoreTask::STATUS_CANCELLED,
        ];

        $taskStatusCounts = [];

        foreach ($taskStatuses as $status) {
            $taskStatusCounts[$status] = CoreTask::query()
                ->where('generic_status', $status)
                ->count();
        }

        $today = Carbon::today();
        $rangeStart = $today->copy()->subDays(6);

        $taskCreatedRows = CoreTask::query()
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $today->copy()->addDay())
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('activity_date');

        $taskCompletedRows = CoreTask::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $rangeStart)
            ->where('completed_at', '<', $today->copy()->addDay())
            ->selectRaw('DATE(completed_at) as activity_date, COUNT(*) as total')
            ->groupByRaw('DATE(completed_at)')
            ->get()
            ->keyBy('activity_date');

        $dailyActivity = [];

        for ($day = 0; $day < 7; $day++) {
            $date = $rangeStart->copy()->addDays($day)->toDateString();

            $dailyActivity[] = [
                'date' => $date,
                'created' => (int) ($taskCreatedRows->get($date)?->total ?? 0),
                'completed' => (int) ($taskCompletedRows->get($date)?->total ?? 0),
            ];
        }

        $recentApplications = Account::query()
            ->where('role', 'USER')
            ->where(
                'runner_status',
                Account::RUNNER_STATUS_PENDING
            )
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get([
                'id',
                'profile_name',
                'phone',
                'email',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'generated_at' => now()->toISOString(),

                'accounts' => [
                    'total_users' => (clone $accountQuery)->count(),
                    'active_users' => (clone $accountQuery)
                        ->where('status', 'ACTIVE')
                        ->count(),
                    'suspended_users' => (clone $accountQuery)
                        ->where('status', 'SUSPENDED')
                        ->count(),
                ],

                'runners' => [
                    'total' => (clone $runnerQuery)->count(),
                    'statuses' => $runnerStatusCounts,
                ],

                'runner_applications' => [
                    'pending' => $runnerStatusCounts[
                        Account::RUNNER_STATUS_PENDING
                    ],
                    'recent' => $recentApplications,
                ],

                'tasks' => [
                    'total' => array_sum($taskStatusCounts),
                    'statuses' => $taskStatusCounts,
                    'daily_activity' => $dailyActivity,
                ],
            ],
        ], 200);
    }
}
