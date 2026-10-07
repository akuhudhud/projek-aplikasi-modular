<?php

namespace App\Http\Middleware;

use App\Models\Session;
use App\Services\Session\SessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSession
{
    public function __construct(
        private SessionService $sessionService
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

        if (!$session->account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (in_array(
            $session->account->status,
            ['DEACTIVATED', 'DELETED'],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($session->account->status === 'SUSPENDED') {
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
            $session->account
        );

        return $next($request);
    }
}
