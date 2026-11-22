<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AdminPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(
        Request $request,
        AdminPermissionService $permissionService
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (
            !$account
            || !$permissionService->allows($account, 'audit_logs.view')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view audit logs.',
                'required_permission' => 'audit_logs.view',
            ], 403);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);
        $page = (int) ($validated['page'] ?? 1);

        $paginator = AuditLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                $perPage,
                [
                    'id',
                    'actor_account_id',
                    'target_account_id',
                    'action',
                    'reason',
                    'created_at',
                ],
                'page',
                $page
            );

        return response()->json([
            'success' => true,
            'data' => [
                'audit_logs' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ], 200);
    }
}
