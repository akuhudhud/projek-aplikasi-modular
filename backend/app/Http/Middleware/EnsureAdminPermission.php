<?php

namespace App\Http\Middleware;

use App\Models\Account;
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

        /*
         * Appointed Super Admins must not access their own identity
         * verification details or documents.
         *
         * Root Super Admin remains unrestricted.
         */
        if (
            $permission === 'identity_verifications.review'
            && $account instanceof Account
            && $account->role === 'SUPER_ADMIN'
        ) {
            $targetAccountId = $request->route('accountId');

            if (
                is_string($targetAccountId)
                && hash_equals($account->id, $targetAccountId)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrators cannot review their own identity verification.',
                ], 403);
            }
        }

        return $next($request);
    }
}
