<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\RunnerLicenseVerification;
use App\Services\AdminPermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRunnerDirectoryController extends Controller
{
    public function __construct(
        private AdminPermissionService $permissionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Account $actor */
        $actor = $request->attributes->get('account');

        if (!in_array($actor->role, ['ROOT_SUPER_ADMIN', 'SUPER_ADMIN'], true)) {
            throw new AuthorizationException('Akses pentadbir diperlukan.');
        }

        if (!$this->permissionService->allows($actor, 'runner_applications.view')) {
            throw new AuthorizationException('Tiada kebenaran melihat direktori Runner.');
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:APPROVED,SUSPENDED'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = Account::query()
            ->whereIn('runner_status', [
                Account::RUNNER_STATUS_APPROVED,
                Account::RUNNER_STATUS_SUSPENDED,
            ])
            ->whereIn('role', ['USER', 'SUPER_ADMIN']);

        if ($actor->role === 'SUPER_ADMIN') {
            $query->where('role', 'USER');
        }

        if (!empty($filters['status'])) {
            $query->where('runner_status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($builder) use ($search) {
                $builder->where('profile_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate(
                (int) ($filters['per_page'] ?? 15),
                [
                    'id',
                    'profile_name',
                    'phone',
                    'email',
                    'role',
                    'status',
                    'runner_status',
                    'created_at',
                    'updated_at',
                ],
                'page',
                (int) ($filters['page'] ?? 1)
            );

        $runners = $paginator->getCollection()->map(
            function (Account $runner): array {
                $license = RunnerLicenseVerification::query()
                    ->where('account_id', $runner->id)
                    ->where('is_current', true)
                    ->where('status', RunnerLicenseVerification::STATUS_VERIFIED)
                    ->orderByDesc('submitted_at')
                    ->first();

                return [
                    'id' => $runner->id,
                    'profile_name' => $runner->profile_name,
                    'phone' => $runner->phone,
                    'email' => $runner->email,
                    'role' => $runner->role,
                    'status' => $runner->status,
                    'runner_status' => $runner->runner_status,
                    'created_at' => $runner->created_at,
                    'updated_at' => $runner->updated_at,
                    'current_license' => $license ? [
                        'license_type' => $license->license_type,
                        'license_class' => $license->license_class,
                        'expires_at' => $license->expires_at?->toDateString(),
                        'status' => $license->status,
                    ] : null,
                ];
            }
        );

        return response()->json([
            'success' => true,
            'data' => [
                'runners' => $runners,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ]);
    }
}
