<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\IdentityVerification;
use App\Services\AdminPermissionService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountController extends Controller
{
    public function index(
        Request $request,
        AdminPermissionService $permissionService
    ): JsonResponse {
        $actor = $request->attributes->get('account');

        if (
            !$actor
            || !$permissionService->allows($actor, 'accounts.view')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view accounts.',
                'required_permission' => 'accounts.view',
            ], 403);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = Account::query();

        if ($actor->role === 'SUPER_ADMIN') {
            $query->where('role', 'USER');
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);

        $paginator = $query
            ->with([
                'identityVerification:id,account_id,verified_name,status',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(
                $perPage,
                [
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
                ],
                'page',
                $page
            );

        $paginator->getCollection()->each(
            function (Account $account): void {
                $verification = $account->identityVerification;

                $verifiedName = $verification !== null
                    && $verification->status
                        === IdentityVerification::STATUS_VERIFIED
                    ? $verification->verified_name
                    : null;

                $account->setAttribute('verified_name', $verifiedName);
                $account->unsetRelation('identityVerification');
            }
        );

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ], 200);
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
                abort(
                    422,
                    'Only appointed Super Admins can have this job title updated.'
                );
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

        $updatedTarget = $target->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Super Admin job title updated successfully.',
            'data' => [
                'account' => [
                    'id' => $updatedTarget->id,
                    'profile_name' => $updatedTarget->profile_name,
                    'job_title' => $updatedTarget->job_title,
                    'phone' => $updatedTarget->phone,
                    'email' => $updatedTarget->email,
                    'profile_picture' => $updatedTarget->profile_picture,
                    'role' => $updatedTarget->role,
                    'status' => $updatedTarget->status,
                    'created_at' => $updatedTarget->created_at,
                    'updated_at' => $updatedTarget->updated_at,
                ],
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
                    'profile_name' => $target->profile_name,
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
            'permissions' => ['present', 'array'],
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

                $changes = $permissionService->replace(
                    $lockedTarget,
                    $validated['permissions']
                );

                if (
                    $changes['granted'] !== []
                    || $changes['revoked'] !== []
                ) {
                    $details = [];

                    if ($changes['granted'] !== []) {
                        $details[] = 'Granted: '.implode(
                            ', ',
                            $changes['granted']
                        );
                    }

                    if ($changes['revoked'] !== []) {
                        $details[] = 'Revoked: '.implode(
                            ', ',
                            $changes['revoked']
                        );
                    }

                    $auditLogService->record(
                        $actor,
                        $lockedTarget,
                        'ADMIN_PERMISSIONS_UPDATED',
                        $reason.' | '.implode(' | ', $details)
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
                    'profile_name' => $target->profile_name,
                    'job_title' => $target->job_title,
                    'role' => $target->role,
                ],
                'permissions' => $permissionService->assignedTo($target),
                'changes' => $changes,
            ],
        ], 200);
    }
}
