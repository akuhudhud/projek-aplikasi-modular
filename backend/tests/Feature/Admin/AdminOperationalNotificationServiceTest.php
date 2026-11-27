<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Notifications\AdminOperationalNotification;
use App\Services\AdminOperationalNotificationService;
use App\Services\AdminPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AdminOperationalNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminOperationalNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AdminOperationalNotificationService(
            new AdminPermissionService()
        );
    }

    private function createAccount(
        string $role = 'SUPER_ADMIN',
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Notification Service Test',
            'phone' => null,
            'email' => Str::uuid().'@example.test',
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function grantPermission(
        Account $account,
        string $permission
    ): void {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => $permission,
        ]);
    }

    private function notificationCount(Account $account): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $account->id)
            ->count();
    }

    public function test_root_super_admin_receives_all_notification_categories(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $categories = [
            AdminOperationalNotification::CATEGORY_REQUEST,
            AdminOperationalNotification::CATEGORY_TASK,
            AdminOperationalNotification::CATEGORY_RUNNER_APPLICATION,
            AdminOperationalNotification::CATEGORY_IDENTITY_VERIFICATION,
            AdminOperationalNotification::CATEGORY_LICENSE_VERIFICATION,
        ];

        foreach ($categories as $category) {
            $sent = $this->service->notifyAdmins(
                event: 'test.event',
                category: $category,
                title: 'Ujian notifikasi',
                message: 'Notifikasi operasi.',
            );

            $this->assertSame(1, $sent);
        }

        $this->assertSame(5, $this->notificationCount($root));
    }

    public function test_super_admin_receives_only_categories_allowed_by_module_permissions(): void
    {
        $admin = $this->createAccount();

        $this->grantPermission(
            $admin,
            'marketplace_requests.view'
        );

        $this->grantPermission(
            $admin,
            'identity_verifications.review'
        );

        $requestSent = $this->service->notifyAdmins(
            event: 'marketplace.request_created',
            category: AdminOperationalNotification::CATEGORY_REQUEST,
            title: 'Permintaan baharu',
            message: 'Permintaan Marketplace baharu.',
        );

        $taskSent = $this->service->notifyAdmins(
            event: 'marketplace.task_started',
            category: AdminOperationalNotification::CATEGORY_TASK,
            title: 'Tugasan bermula',
            message: 'Tugasan Marketplace bermula.',
        );

        $identitySent = $this->service->notifyAdmins(
            event: 'identity.submitted',
            category: AdminOperationalNotification::CATEGORY_IDENTITY_VERIFICATION,
            title: 'Pengesahan identiti',
            message: 'Permohonan pengesahan identiti baharu.',
        );

        $this->assertSame(1, $requestSent);
        $this->assertSame(0, $taskSent);
        $this->assertSame(1, $identitySent);
        $this->assertSame(2, $this->notificationCount($admin));
    }

    public function test_regular_user_does_not_receive_admin_notifications(): void
    {
        $user = $this->createAccount('USER');

        $sent = $this->service->notifyAdmins(
            event: 'marketplace.request_created',
            category: AdminOperationalNotification::CATEGORY_REQUEST,
            title: 'Permintaan baharu',
            message: 'Permintaan Marketplace baharu.',
        );

        $this->assertSame(0, $sent);
        $this->assertSame(0, $this->notificationCount($user));
    }

    public function test_inactive_admin_does_not_receive_notifications(): void
    {
        $inactiveAdmin = $this->createAccount(
            'ROOT_SUPER_ADMIN',
            'SUSPENDED'
        );

        $sent = $this->service->notifyAdmins(
            event: 'marketplace.request_created',
            category: AdminOperationalNotification::CATEGORY_REQUEST,
            title: 'Permintaan baharu',
            message: 'Permintaan Marketplace baharu.',
        );

        $this->assertSame(0, $sent);
        $this->assertSame(
            0,
            $this->notificationCount($inactiveAdmin)
        );
    }

    public function test_unsupported_category_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->notifyAdmins(
            event: 'test.event',
            category: 'UNKNOWN_CATEGORY',
            title: 'Ujian',
            message: 'Ujian.',
        );
    }

    public function test_unsupported_severity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->notifyAdmins(
            event: 'test.event',
            category: AdminOperationalNotification::CATEGORY_REQUEST,
            title: 'Ujian',
            message: 'Ujian.',
            severity: 'UNKNOWN_SEVERITY',
        );
    }
}
