<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Session;
use App\Notifications\AdminOperationalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role = 'SUPER_ADMIN'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Notification Admin',
            'phone' => null,
            'email' => Str::uuid().'@example.test',
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticate(Account $account): static
    {
        $token = 'admin-notification-'.Str::random(32);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function createNotification(
        Account $account,
        string $title = 'Notifikasi ujian'
    ): string {
        $account->notify(
            new AdminOperationalNotification(
                event: 'marketplace.request_created',
                category: AdminOperationalNotification::CATEGORY_REQUEST,
                title: $title,
                message: 'Permintaan Marketplace baharu.',
                severity: AdminOperationalNotification::SEVERITY_WARNING,
                resourceType: 'marketplace_request',
                resourceId: (string) Str::uuid(),
                actionPath: '/marketplace-requests',
            )
        );

        return $account->notifications()
            ->latest()
            ->firstOrFail()
            ->id;
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/admin/notifications')
            ->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_admin_notifications(): void
    {
        $user = $this->createAccount('USER');

        $this->authenticate($user)
            ->getJson('/api/admin/notifications')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_view_paginated_notifications_and_unread_count(): void
    {
        $admin = $this->createAccount();

        $firstId = $this->createNotification(
            $admin,
            'Notifikasi pertama'
        );

        $this->createNotification(
            $admin,
            'Notifikasi kedua'
        );

        $response = $this->authenticate($admin)
            ->getJson('/api/admin/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 2)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.per_page', 20)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonStructure([
                'data' => [
                    'notifications' => [
                        '*' => [
                            'id',
                            'type',
                            'data',
                            'read_at',
                            'created_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);

        $this->assertContains(
            $firstId,
            array_column(
                $response->json('data.notifications'),
                'id'
            )
        );
    }

    public function test_admin_can_mark_own_notification_as_read(): void
    {
        $admin = $this->createAccount();

        $notificationId = $this->createNotification($admin);

        $this->authenticate($admin)
            ->patchJson(
                '/api/admin/notifications/'.$notificationId.'/read'
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $notificationId)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertNotNull(
            $admin->notifications()
                ->whereKey($notificationId)
                ->firstOrFail()
                ->read_at
        );
    }

    public function test_admin_can_mark_all_notifications_as_read(): void
    {
        $admin = $this->createAccount();

        $this->createNotification($admin, 'Notifikasi satu');
        $this->createNotification($admin, 'Notifikasi dua');
        $this->createNotification($admin, 'Notifikasi tiga');

        $this->authenticate($admin)
            ->patchJson('/api/admin/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(
            0,
            $admin->unreadNotifications()->count()
        );
    }

    public function test_admin_cannot_mark_another_accounts_notification_as_read(): void
    {
        $owner = $this->createAccount();
        $otherAdmin = $this->createAccount();

        $notificationId = $this->createNotification($owner);

        $this->authenticate($otherAdmin)
            ->patchJson(
                '/api/admin/notifications/'.$notificationId.'/read'
            )
            ->assertNotFound()
            ->assertJsonPath('success', false);

        // Notification belongs to another account and must remain unread.
        $this->assertNull(
            $owner->notifications()
                ->whereKey($notificationId)
                ->firstOrFail()
                ->read_at
        );

        $this->assertSame(
            1,
            $owner->unreadNotifications()->count()
        );
    }
}
