<?php

namespace App\Services;

use App\Models\Account;
use App\Notifications\AdminOperationalNotification;
use InvalidArgumentException;

class AdminOperationalNotificationService
{
    public function __construct(
        private AdminPermissionService $permissionService,
    ) {
    }

    /**
     * Permission required for each operational notification category.
     */
    private const CATEGORY_PERMISSIONS = [
        AdminOperationalNotification::CATEGORY_REQUEST => 'marketplace_requests.view',
        AdminOperationalNotification::CATEGORY_TASK => 'marketplace_tasks.view',
        AdminOperationalNotification::CATEGORY_RUNNER_APPLICATION => 'runner_applications.view',
        AdminOperationalNotification::CATEGORY_IDENTITY_VERIFICATION => 'identity_verifications.review',
        AdminOperationalNotification::CATEGORY_LICENSE_VERIFICATION => 'runner_applications.view',
    ];

    public function notifyAdmins(
        string $event,
        string $category,
        string $title,
        string $message,
        string $severity = AdminOperationalNotification::SEVERITY_INFO,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?string $actionPath = null,
    ): int {
        if (!array_key_exists($category, self::CATEGORY_PERMISSIONS)) {
            throw new InvalidArgumentException(
                'Unsupported Admin operational notification category.'
            );
        }

        if (!in_array($severity, [
            AdminOperationalNotification::SEVERITY_INFO,
            AdminOperationalNotification::SEVERITY_WARNING,
            AdminOperationalNotification::SEVERITY_CRITICAL,
        ], true)) {
            throw new InvalidArgumentException(
                'Unsupported Admin operational notification severity.'
            );
        }

        $requiredPermission = self::CATEGORY_PERMISSIONS[$category];

        $notification = new AdminOperationalNotification(
            event: $event,
            category: $category,
            title: $title,
            message: $message,
            severity: $severity,
            resourceType: $resourceType,
            resourceId: $resourceId,
            actionPath: $actionPath,
        );

        $sent = 0;

        Account::query()
            ->whereIn('role', [
                'ROOT_SUPER_ADMIN',
                'SUPER_ADMIN',
            ])
            ->where('status', 'ACTIVE')
            ->get()
            ->each(function (Account $account) use (
                $requiredPermission,
                $notification,
                &$sent
            ): void {
                if (!$this->permissionService->allows(
                    $account,
                    $requiredPermission
                )) {
                    return;
                }

                $account->notify($notification);

                $sent++;
            });

        return $sent;
    }
}
