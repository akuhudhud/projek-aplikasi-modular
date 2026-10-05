<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
}
