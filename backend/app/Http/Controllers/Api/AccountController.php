<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\AdminPermissionService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AccountController extends Controller
{
    public function index(
        Request $request,
        AdminPermissionService $permissionService
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (
            !$account
            || !$permissionService->allows($account, 'accounts.view')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view accounts.',
                'required_permission' => 'accounts.view',
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
                'job_title',
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

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:100'],
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
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $reason = trim((string) $request->input('reason'));

        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Reason is required.',
            ], 422);
        }

        $newSuperAdmin = DB::transaction(function () use (
            $request,
            $actor,
            $reason,
            $auditLogService
        ): Account {
            $account = Account::create([
                'id' => (string) Str::uuid(),
                'name' => $request->input('name'),
                'job_title' => $request->input('job_title'),
                'phone' => $request->input('phone'),
                'email' => $request->input('email'),
                'profile_picture' => null,
                'password' => $request->input('password'),
                'role' => 'SUPER_ADMIN',
                'status' => 'ACTIVE',
            ]);

            $auditLogService->record(
                $actor,
                $account,
                'SUPER_ADMIN_CREATED',
                $reason
            );

            return $account;
        });

        return response()->json([
            'success' => true,
            'message' => 'Super Admin created successfully.',
            'data' => [
                'account' => $newSuperAdmin,
            ],
        ], 201);
    }

    public function updateSuperAdminJobTitle(
        Request $request,
        string $accountId,
        AuditLogService $auditLogService
    ): JsonResponse {
        $actor = $request->attributes->get('account');

        if (!$actor || $actor->role !== 'ROOT_SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'job_title' => [
                'present',
                'nullable',
                'string',
                'max:100',
            ],
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $reason = trim($validated['reason']);

        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Reason is required.',
            ], 422);
        }

        $target = Account::query()->find($accountId);

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        if ($target->role !== 'SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Only appointed Super Admins can have this job title updated.',
            ], 422);
        }

        DB::transaction(function () use (
            $target,
            $validated,
            $actor,
            $reason,
            $auditLogService
        ): void {
            $lockedTarget = Account::query()
                ->whereKey($target->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTarget->role !== 'SUPER_ADMIN') {
                abort(422, 'Only appointed Super Admins can have this job title updated.');
            }

            $lockedTarget->job_title = $validated['job_title'];
            $lockedTarget->save();

            $auditLogService->record(
                $actor,
                $lockedTarget,
                'SUPER_ADMIN_JOB_TITLE_UPDATED',
                $reason
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Super Admin job title updated successfully.',
            'data' => [
                'account' => $target->fresh(),
            ],
        ], 200);
    }

    public function showSuperAdminPermissions(
        Request $request,
        string $accountId,
        AdminPermissionService $permissionService
    ): JsonResponse {
        $actor = $request->attributes->get('account');

        if (!$actor || $actor->role !== 'ROOT_SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $target = Account::query()->find($accountId);

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        if ($target->role !== 'SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Permissions can only be managed for appointed Super Admins.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'account' => [
                    'id' => $target->id,
                    'name' => $target->name,
                    'job_title' => $target->job_title,
                    'role' => $target->role,
                ],
                'catalog' => $permissionService->catalog(),
                'permissions' => $permissionService->assignedTo($target),
            ],
        ], 200);
    }

    public function updateSuperAdminPermissions(
        Request $request,
        string $accountId,
        AdminPermissionService $permissionService,
        AuditLogService $auditLogService
    ): JsonResponse {
        $actor = $request->attributes->get('account');

        if (!$actor || $actor->role !== 'ROOT_SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'required',
                'string',
                'distinct',
                'max:100',
            ],
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $reason = trim($validated['reason']);

        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Reason is required.',
            ], 422);
        }

        $target = Account::query()->find($accountId);

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        if ($target->role !== 'SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Permissions can only be managed for appointed Super Admins.',
            ], 422);
        }

        try {
            $changes = DB::transaction(function () use (
                $target,
                $validated,
                $actor,
                $reason,
                $permissionService,
                $auditLogService
            ): array {
                $lockedTarget = Account::query()
                    ->whereKey($target->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedTarget->role !== 'SUPER_ADMIN') {
                    throw new InvalidArgumentException(
                        'Permissions can only be assigned to appointed Super Admin accounts.'
                    );
                }

                // Permission changes and their audit records share
                // the same outer transaction.
                $changes = $permissionService->replace(
                    $lockedTarget,
                    $validated['permissions']
                );

                foreach ($changes['granted'] as $permission) {
                    $auditLogService->record(
                        $actor,
                        $lockedTarget,
                        'ADMIN_PERMISSION_GRANTED',
                        $reason.' Permission: '.$permission
                    );
                }

                foreach ($changes['revoked'] as $permission) {
                    $auditLogService->record(
                        $actor,
                        $lockedTarget,
                        'ADMIN_PERMISSION_REVOKED',
                        $reason.' Permission: '.$permission
                    );
                }

                return $changes;
            });
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Super Admin permissions updated successfully.',
            'data' => [
                'account' => [
                    'id' => $target->id,
                    'name' => $target->name,
                    'job_title' => $target->job_title,
                    'role' => $target->role,
                ],
                'permissions' => $permissionService->assignedTo($target),
                'changes' => $changes,
            ],
        ], 200);
    }
}
