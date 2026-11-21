<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CoreTask;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->attributes->get('account');

        if (
            !$admin instanceof Account ||
            !in_array(
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

        $users = Account::query()->where('role', 'USER');

        $runners = Account::query()
            ->where('role', 'USER')
            ->whereIn('runner_status', $runnerStatuses);

        $runnerStatusCounts = [];

        foreach ($runnerStatuses as $status) {
            $runnerStatusCounts[$status] = (clone $runners)
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
        $rangeEnd = $today->copy()->addDay();

        $createdRows = CoreTask::query()
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $rangeEnd)
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('activity_date');

        $completedRows = CoreTask::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $rangeStart)
            ->where('completed_at', '<', $rangeEnd)
            ->selectRaw('DATE(completed_at) as activity_date, COUNT(*) as total')
            ->groupByRaw('DATE(completed_at)')
            ->get()
            ->keyBy('activity_date');

        $dailyActivity = [];

        for ($day = 0; $day < 7; $day++) {
            $date = $rangeStart->copy()
                ->addDays($day)
                ->toDateString();

            $dailyActivity[] = [
                'date' => $date,
                'created' => (int) ($createdRows->get($date)?->total ?? 0),
                'completed' => (int) ($completedRows->get($date)?->total ?? 0),
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
                    'total_users' => (clone $users)->count(),
                    'active_users' => (clone $users)
                        ->where('status', 'ACTIVE')
                        ->count(),
                    'suspended_users' => (clone $users)
                        ->where('status', 'SUSPENDED')
                        ->count(),
                ],

                'runners' => [
                    'total' => (clone $runners)->count(),
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
        ]);
    }
}
