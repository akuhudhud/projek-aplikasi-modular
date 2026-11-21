<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\IdentityVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAccountDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                Rule::in(['ACTIVE', 'SUSPENDED', 'DEACTIVATED', 'DELETED']),
            ],
            'role' => [
                'nullable',
                Rule::in(['USER', 'SUPER_ADMIN', 'ROOT_SUPER_ADMIN']),
            ],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $actor = $request->attributes->get('account');

        $query = Account::query()
            ->with([
                'identityVerification:id,account_id,verified_name,status',
            ]);

        if ($actor->role === 'SUPER_ADMIN') {
            $query->where('role', 'USER');
        }

        if (!empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $term = trim($validated['search']);

            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('profile_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas(
                        'identityVerification',
                        function ($verification) use ($term): void {
                            $verification
                                ->where(
                                    'status',
                                    IdentityVerification::STATUS_VERIFIED
                                )
                                ->where('verified_name', 'like', "%{$term}%");
                        }
                    );
            });
        }

        $accounts = $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 15, [
                'id',
                'profile_name',
                'job_title',
                'phone',
                'email',
                'profile_picture',
                'role',
                'status',
                'created_at',
                'updated_at',
            ]);

        $accounts->getCollection()->transform(
            function (Account $account): array {
                $verification = $account->identityVerification;

                return [
                    'id' => $account->id,
                    'profile_name' => $account->profile_name,
                    'verified_name' => $verification
                        && $verification->status === IdentityVerification::STATUS_VERIFIED
                            ? $verification->verified_name
                            : null,
                    'job_title' => $account->job_title,
                    'phone' => $account->phone,
                    'email' => $account->email,
                    'profile_picture' => $account->profile_picture,
                    'role' => $account->role,
                    'status' => $account->status,
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at,
                ];
            }
        );

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $accounts->items(),
                'pagination' => [
                    'current_page' => $accounts->currentPage(),
                    'last_page' => $accounts->lastPage(),
                    'per_page' => $accounts->perPage(),
                    'total' => $accounts->total(),
                    'from' => $accounts->firstItem(),
                    'to' => $accounts->lastItem(),
                ],
            ],
        ]);
    }

    public function show(Request $request, string $accountId): JsonResponse
    {
        $actor = $request->attributes->get('account');

        $query = Account::query()
            ->with([
                'identityVerification:id,account_id,verified_name,status,submitted_at,reviewed_at',
            ]);

        if ($actor->role === 'SUPER_ADMIN') {
            $query->where('role', 'USER');
        }

        $account = $query->whereKey($accountId)->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        $verification = $account->identityVerification;

        return response()->json([
            'success' => true,
            'data' => [
                'account' => [
                    'id' => $account->id,
                    'profile_name' => $account->profile_name,
                    'verified_name' => $verification
                        && $verification->status === IdentityVerification::STATUS_VERIFIED
                            ? $verification->verified_name
                            : null,
                    'identity_status' => $verification?->status,
                    'identity_submitted_at' => $verification?->submitted_at,
                    'identity_reviewed_at' => $verification?->reviewed_at,
                    'job_title' => $account->job_title,
                    'phone' => $account->phone,
                    'email' => $account->email,
                    'profile_picture' => $account->profile_picture,
                    'role' => $account->role,
                    'status' => $account->status,
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at,
                ],
            ],
        ]);
    }
}
