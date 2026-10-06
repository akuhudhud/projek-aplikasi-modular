<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    public function createSuperAdmin(
        Request $request,
        AuditLogService $auditLogService
    ): JsonResponse {
        $actor = $request->attributes->get('account');

        if (!$actor || $actor->role !== 'ROOT_SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $reason = trim((string) $request->input('reason'));

        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Reason is required.',
            ], 422);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                'required_without:email',
                'unique:accounts,phone',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                'required_without:phone',
                'unique:accounts,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:12',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
            ],
            'password_confirmation' => [
                'required',
                'same:password',
            ],
            'reason' => [
                'required',
                'string',
            ],
        ]);

        $newSuperAdmin = Account::create([
            'id' => (string) Str::uuid(),
            'name' => $request->input('name'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $auditLogService->record(
            $actor,
            $newSuperAdmin,
            'SUPER_ADMIN_CREATED',
            $reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Super Admin created successfully.',
            'data' => [
                'account' => $newSuperAdmin,
            ],
        ], 201);
    }
}
