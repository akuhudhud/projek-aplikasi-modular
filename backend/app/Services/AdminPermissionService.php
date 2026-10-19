<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountPermission;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminPermissionService
{
    /**
     * The canonical permission catalog.
     * Job titles do not grant permissions.
     */
    public const CATALOG = [
        'accounts.view' => 'Melihat direktori akaun',
        'accounts.suspend' => 'Menggantung akaun',
        'accounts.unsuspend' => 'Memulihkan akaun yang digantung',
        'accounts.reactivate' => 'Mengaktifkan semula akaun',
        'runner_applications.view' => 'Melihat permohonan Runner',
        'runner_applications.approve' => 'Meluluskan permohonan Runner',
        'runner_applications.reject' => 'Menolak permohonan Runner',
        'runners.suspend' => 'Menggantung kelayakan Runner',
        'runners.restore' => 'Memulihkan kelayakan Runner',
        'identity_verifications.review' => 'Menyemak pengesahan identiti',
        'audit_logs.view' => 'Melihat rekod audit',
    ];

    public function catalog(): array
    {
        return collect(self::CATALOG)
            ->map(
                fn (string $label, string $key): array => [
                    'key' => $key,
                    'label' => $label,
                ]
            )
            ->values()
            ->all();
    }

    public function permissionKeys(): array
    {
        return array_keys(self::CATALOG);
    }

    public function allows(
        Account $account,
        string $permission
    ): bool {
        // Root is the owner and bypasses internal permission restrictions.
        if ($account->role === 'ROOT_SUPER_ADMIN') {
            return true;
        }

        if ($account->role !== 'SUPER_ADMIN') {
            return false;
        }

        if (!array_key_exists($permission, self::CATALOG)) {
            return false;
        }

        return AccountPermission::query()
            ->where('account_id', $account->id)
            ->where('permission_key', $permission)
            ->exists();
    }

    public function assignedTo(Account $account): array
    {
        return AccountPermission::query()
            ->where('account_id', $account->id)
            ->orderBy('permission_key')
            ->pluck('permission_key')
            ->all();
    }

    /**
     * Replace the complete permission set for one appointed Super Admin.
     *
     * @return array{granted: array<int, string>, revoked: array<int, string>}
     */
    public function replace(
        Account $account,
        array $requestedPermissions
    ): array {
        if ($account->role !== 'SUPER_ADMIN') {
            throw new InvalidArgumentException(
                'Permissions can only be assigned to appointed Super Admin accounts.'
            );
        }

        $requestedPermissions = array_values(
            array_unique($requestedPermissions)
        );

        $unknownPermissions = array_diff(
            $requestedPermissions,
            $this->permissionKeys()
        );

        if ($unknownPermissions !== []) {
            throw new InvalidArgumentException(
                'One or more permission keys are not recognised.'
            );
        }

        return DB::transaction(function () use (
            $account,
            $requestedPermissions
        ): array {
            $currentPermissions = $this->assignedTo($account);

            $granted = array_values(array_diff(
                $requestedPermissions,
                $currentPermissions
            ));

            $revoked = array_values(array_diff(
                $currentPermissions,
                $requestedPermissions
            ));

            foreach ($granted as $permission) {
                AccountPermission::create([
                    'account_id' => $account->id,
                    'permission_key' => $permission,
                ]);
            }

            if ($revoked !== []) {
                AccountPermission::query()
                    ->where('account_id', $account->id)
                    ->whereIn('permission_key', $revoked)
                    ->delete();
            }

            return [
                'granted' => $granted,
                'revoked' => $revoked,
            ];
        });
    }
}
