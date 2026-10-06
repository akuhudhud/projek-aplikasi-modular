<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$account || !in_array($account->role, [
            'SUPER_ADMIN',
            'ROOT_SUPER_ADMIN',
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $auditLogs = AuditLog::query()
            ->orderByDesc('created_at')
            ->get([
                'id',
                'actor_account_id',
                'target_account_id',
                'action',
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
