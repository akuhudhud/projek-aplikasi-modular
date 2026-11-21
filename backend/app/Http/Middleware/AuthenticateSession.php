<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Session;
use App\Services\AdminPermissionService;
use App\Services\Session\SessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

class AuthenticateSession
{
    public function __construct(
        private SessionService $sessionService,
        private AdminPermissionService $adminPermissionService
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $session = Session::query()
            ->with('account')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('ended_at')
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $account = $session->account;

        if (!$account instanceof Account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (in_array(
            $account->status,
            ['DEACTIVATED', 'DELETED'],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($account->status === 'SUSPENDED') {
            return response()->json([
                'success' => false,
                'message' => 'Account is suspended.',
            ], 403);
        }

        $this->sessionService->touch($session);

        $request->attributes->set(
            'session',
            $session->fresh()
        );

        $request->attributes->set(
            'account',
            $account
        );

        $response = $next($request);

        if (
            $request->is('api/me') &&
            $response instanceof JsonResponse
        ) {
            $payload = $response->getData(true);

            if (
                isset($payload['data']['account']) &&
                is_array($payload['data']['account'])
            ) {
                $permissions = [];

                if ($account->role === 'ROOT_SUPER_ADMIN') {
                    $permissions = $this->adminPermissionService
                        ->permissionKeys();
                } elseif ($account->role === 'SUPER_ADMIN') {
                    $permissions = $this->adminPermissionService
                        ->assignedTo($account);
                }

                $payload['data']['account']['permissions'] = $permissions;

                $response->setData($payload);
            }
        }

        return $response;
    }
}
