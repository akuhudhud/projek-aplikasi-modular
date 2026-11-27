<?php

namespace App\Services;

use App\Models\Account;
use App\Notifications\AdminOperationalNotification;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class AdminOperationalNotificationService
{
    public function __construct(
        private AdminPermissionService $permissionService,
        private ?AdminWebPushService $webPushService = null,
    ) {
    }

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

                // Simpan notifikasi dalam database terlebih dahulu.
                $account->notify($notification);

                $sent++;

                // Hantar Web Push jika servis tersedia.
                if ($this->webPushService === null) {
                    return;
                }

                try {
                    $this->webPushService->sendToAccount(
                        $account,
                        $notification->toArray($account)
                    );
                } catch (Throwable $exception) {
                    // Kegagalan Web Push tidak boleh menggagalkan
                    // penyimpanan notifikasi database.
                    Log::error(
                        'Admin Web Push dispatch failed.',
                        [
                            'account_id' => $account->id,
                            'event' => $notification->toArray($account)['event'],
                            'exception' => $exception->getMessage(),
                        ]
                    );
                }
            });

        return $sent;
    }
}
