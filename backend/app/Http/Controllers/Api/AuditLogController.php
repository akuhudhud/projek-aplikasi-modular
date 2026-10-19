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

        $auditLogs = AuditLog::query()
            ->orderByDesc('created_at')
            ->get([
                'id',
                'actor_account_id',
                'target_account_id',
                'action',
                'reason',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'audit_logs' => $auditLogs,
            ],
        ], 200);
    }
}
