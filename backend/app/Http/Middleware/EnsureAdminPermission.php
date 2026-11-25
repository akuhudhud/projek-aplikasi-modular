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
            !$account instanceof Account
            || !$this->permissionService->allows($account, $permission)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'required_permission' => $permission,
            ], 403);
        }

        // Root Super Admin mempunyai akses penuh.
        if ($account->role === 'ROOT_SUPER_ADMIN') {
            return $next($request);
        }

        // Sekatan sasaran hanya untuk Super Admin.
        if (
            $account->role !== 'SUPER_ADMIN'
            || !in_array($permission, [
                'identity_verifications.review',
                'identity_verifications.delete_data',
            ], true)
        ) {
            return $next($request);
        }

        $targetAccountId = $request->route('accountId');

        // Endpoint senarai tidak mempunyai accountId.
        if (!is_string($targetAccountId)) {
            return $next($request);
        }

        // Elakkan Super Admin mengurus rekod sendiri.
        if (hash_equals($account->id, $targetAccountId)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrators cannot access their own identity verification data.',
            ], 403);
        }

        $targetAccount = Account::query()
            ->select(['id', 'role'])
            ->whereKey($targetAccountId)
            ->first();

        // Jika tiada akaun, biarkan controller mengembalikan 404.
        if ($targetAccount === null) {
            return $next($request);
        }

        // Super Admin hanya boleh mengurus akaun USER.
        if ($targetAccount->role !== 'USER') {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin can only manage identity verification data belonging to USER accounts.',
            ], 403);
        }

        return $next($request);
    }
}
