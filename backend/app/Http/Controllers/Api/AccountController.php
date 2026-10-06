<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$account || !in_array($account->role, [
            'SUPER_ADMIN',
            'ROOT_SUPER_ADMIN',
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $query = Account::query();

        if ($account->role === 'SUPER_ADMIN') {
            $query->where('role', 'USER');
        }

        $accounts = $query
            ->orderBy('created_at')
            ->get([
                'id',
                'name',
                'phone',
                'email',
                'profile_picture',
                'role',
                'status',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $accounts,
            ],
        ], 200);
    }
}
