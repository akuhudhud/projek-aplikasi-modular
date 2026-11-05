<?php

namespace App\Http\Middleware;

use App\Models\Session;
use App\Services\Session\SessionService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWebSession
{
    /**
     * Server-side Laravel session key containing the
     * raw Super Runner custom session token.
     */
    public const SESSION_TOKEN_KEY = 'super_runner.web_session_token';

    public function __construct(
        private readonly SessionService $sessionService
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $token = $request->session()->get(
            self::SESSION_TOKEN_KEY
        );

        if (!is_string($token) || $token === '') {
            return $this->unauthenticated();
        }

        $session = Session::query()
            ->with('account')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('ended_at')
            ->first();

        if (!$session || !$session->account) {
            $request->session()->forget(
                self::SESSION_TOKEN_KEY
            );

            return $this->unauthenticated();
        }

        $account = $session->account;

        if (in_array(
            $account->status,
            ['DEACTIVATED', 'DELETED'],
            true
        )) {
            $request->session()->forget(
                self::SESSION_TOKEN_KEY
            );

            return $this->unauthenticated(
                'Account is not available.'
            );
        }

        if ($account->status === 'SUSPENDED') {
            $request->session()->forget(
                self::SESSION_TOKEN_KEY
            );

            return response()->json([
                'success' => false,
                'message' => 'Account is suspended.',
            ], 403);
        }

        if ($account->status !== 'ACTIVE') {
            $request->session()->forget(
                self::SESSION_TOKEN_KEY
            );

            return response()->json([
                'success' => false,
                'message' => 'Account is not available.',
            ], 403);
        }

        $this->sessionService->touch($session);

        $request->attributes->set('account', $account);
        $request->attributes->set('session', $session);

        return $next($request);
    }

    private function unauthenticated(
        string $message = 'Unauthenticated.'
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 401);
    }
}
