<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $account = null;

        $session = null;

        \DB::transaction(function () use ($request, &$account, &$session) {
            $account = Account::create([
                'id' => (string) Str::uuid(),
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => $request->password,
                'role' => 'USER',
                'status' => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);

            $token = Str::random(64);

            $session = Session::create([
                'id' => (string) Str::uuid(),
                'account_id' => $account->id,
                'token_hash' => hash('sha256', $token),
                'created_at' => now(),
                'ended_at' => null,
            ]);

            $session->plain_token = $token;
        });

        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'data' => [
                'account' => $account,
                'session' => [
                    'id' => $session->id,
                    'token' => $session->plain_token,
                ],
            ],
        ], 201);
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

        if (! $account || ! Hash::check($request->password, $account->password)) {
            if ($account) {
                $account->failed_login_attempts++;

                if ($account->failed_login_attempts >= 5) {
                    $account->locked_until = now()->addMinutes(30);
                }

                $account->save();
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (in_array($account->status, ['DEACTIVATED', 'DELETED'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not available for login.',
            ], 403);
        }

        if (
            $account->locked_until !== null
            && $account->locked_until->isFuture()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Account is temporarily locked.',
            ], 423);
        }

        if (
            $account->locked_until !== null
            && $account->locked_until->isPast()
        ) {
            $account->failed_login_attempts = 0;
            $account->locked_until = null;
        }

        Session::where('account_id', $account->id)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
            ]);

        $token = Str::random(64);

        $session = Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $account->failed_login_attempts = 0;
        $account->locked_until = null;
        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'account' => $account,
                'session' => [
                    'id' => $session->id,
                    'token' => $token,
                ],
            ],
        ], 200);
    }

    public function me(): JsonResponse
    {
        $account = request()->attributes->get('account');

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
        $account = request()->attributes->get('account');

        if ($request->has('name')) {
            $account->name = $request->name;
        }

        if ($request->has('phone')) {
            $account->phone = $request->phone;
        }

        if ($request->has('email')) {
            $account->email = $request->email;
        }

        if ($request->has('profile_picture')) {
            $account->profile_picture = $request->profile_picture;
        }

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

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (! Hash::check($request->current_password, $account->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $account->password = $request->new_password;
        $account->save();

        Session::where('account_id', $account->id)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. Please login again.',
        ], 200);
    }

    public function deactivate(): JsonResponse
    {
        $account = request()->attributes->get('account');

        $account->status = 'DEACTIVATED';
        $account->save();

        Session::where('account_id', $account->id)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Account deactivated successfully.',
        ], 200);
    }

    public function logout(): JsonResponse
    {
        $session
