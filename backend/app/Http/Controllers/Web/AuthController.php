<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateWebSession;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Account;
use App\Services\Auth\AuthenticationService;
use App\Services\Session\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly SessionService $sessionService
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authenticationService->authenticate(
            $request->input('phone'),
            $request->input('email'),
            $request->input('password')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
        }

        /** @var Account $account */
        $account = $result['account'];

        $sessionData = $result['session'];

        $request->session()->regenerate();

        $request->session()->put(
            AuthenticateWebSession::SESSION_TOKEN_KEY,
            $sessionData['token']
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'phone' => $account->phone,
                    'email' => $account->email,
                    'profile_picture' => $account->profile_picture,
                    'role' => $account->role,
                    'status' => $account->status,
                ],
            ],
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->attributes->get('account');

        return response()->json([
            'success' => true,
            'data' => [
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'phone' => $account->phone,
                    'email' => $account->email,
                    'profile_picture' => $account->profile_picture,
                    'role' => $account->role,
                    'status' => $account->status,
                ],
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $session = $request->attributes->get('session');

        $this->sessionService->end(
            $session,
            SessionService::END_REASON_LOGOUT
        );

        $request->session()->forget(
            AuthenticateWebSession::SESSION_TOKEN_KEY
        );

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ], 200);
    }
}
