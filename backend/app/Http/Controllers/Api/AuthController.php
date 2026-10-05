<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $account = Account::create([
                'id' => (string) Str::uuid(),
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => $request->password,
                'role' => 'USER',
                'status' => 'ACTIVE',
            ]);

            $sessionToken = Str::random(64);

            Session::create([
                'id' => (string) Str::uuid(),
                'account_id' => $account->id,
                'token_hash' => hash('sha256', $sessionToken),
                'created_at' => now(),
                'ended_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account registered successfully.',
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
                    'session' => [
                        'token' => $sessionToken,
                    ],
                ],
            ], 201);
        });
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $account = Account::query()
            ->when(
                $request->filled('phone'),
                fn ($query) => $query->where('phone', $request->phone)
            )
            ->when(
                $request->filled('email'),
                fn ($query) => $query->where('email', $request->email)
            )
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($account->locked_until !== null) {
            if ($account->locked_until->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is temporarily locked.',
                ], 423);
            }

            $account->failed_login_attempts = 0;
            $account->locked_until = null;
            $account->save();
        }

        if (in_array($account->status, ['DEACTIVATED', 'DELETED'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not available for login.',
            ], 403);
        }

        if (!Hash::check($request->password, $account->password)) {
            $account->failed_login_attempts++;

            if ($account->failed_login_attempts >= 5) {
                $account->locked_until = now()->addMinutes(30);
            }

            $account->save();

            if ($account->locked_until !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is temporarily locked.',
                ], 423);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $account->failed_login_attempts = 0;
        $account->locked_until = null;
        $account->save();

        Session::where('account_id', $account->id)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
            ]);

        $sessionToken = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $sessionToken),
            'created_at' => now(),
            'ended_at' => null,
        ]);

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
                'session' => [
                    'token' => $sessionToken,
                ],
            ],
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
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

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        $account->fill($request->only([
            'name',
            'phone',
            'email',
        ]));

        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
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

        Session::where('id', $session->id)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ], 200);
    }
}
