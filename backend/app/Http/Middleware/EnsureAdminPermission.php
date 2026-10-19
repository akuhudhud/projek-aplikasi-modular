<?php

namespace App\Http\Middleware;

use App\Services\AdminPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
{
    public function __construct(
        private AdminPermissionService $permissionService
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {
        $account = $request->attributes->get('account');

        if (
            !$account
            || !$this->permissionService->allows($account, $permission)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
