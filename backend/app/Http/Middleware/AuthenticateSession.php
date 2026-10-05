<?php

namespace App\Http\Middleware;

use App\Models\Session;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSession
{
    public function handle(Request $request, Closure $next): Response
    {
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

        $request->attributes->set('session', $session);
        $request->attributes->set('account', $session->account);

        return $next($request);
    }
}
