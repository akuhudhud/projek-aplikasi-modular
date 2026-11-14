<?php

namespace Tests\Feature\Notifications;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerNotificationWebApiTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): array
    {
        $accountId = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $accountId,
            'profile_name' => 'Notification Customer',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => Account::RUNNER_STATUS_NOT_RUNNER,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $account = Account::query()->findOrFail($accountId);
        $token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => $now,
            'last_activity_at' => $now,
            'ended_at' => null,
            'end_reason' => null,
        ]);

        return [$account, $token];
    }

    private function createNotification(
        Account $account,
        ?string $readAt = null
    ): string {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => Account::class,
            'notifiable_id' => $account->id,
            'data' => json_encode([
                'title' => 'Sebutharga baharu',
                'message' => 'Runner menghantar sebut harga.',
                'action' => 'VIEW_REQUEST',
            ], JSON_THROW_ON_ERROR),
            'read_at' => $readAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    private function authenticatedSession(string $token): static
    {
        return $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);
    }

    public function test_customer_can_view_own_notifications(): void
    {
        [$account, $token] = $this->createCustomer();

        $this->createNotification($account);
        $this->createNotification($account, now()->toDateTimeString());

        $this->authenticatedSession($token)
            ->getJson('/web/customer/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.summary.unread_total',
                1
            )
            ->assertJsonPath(
                'data.pagination.total',
                2
            )
            ->assertJsonCount(
                2,
                'data.notifications'
            );
    }

    public function test_customer_can_mark_own_notification_as_read(): void
    {
        [$account, $token] = $this->createCustomer();

        $notificationId = $this->createNotification($account);

        $this->authenticatedSession($token)
            ->patchJson(
                "/web/customer/notifications/{$notificationId}/read"
            )
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notificationId,
            'read_at' => null,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'notifiable_id' => $account->id,
        ]);
    }

    public function test_customer_cannot_mark_another_accounts_notification_as_read(): void
    {
        [, $ownerToken] = $this->createCustomer();
        [$otherAccount] = $this->createCustomer();

        $notificationId = $this->createNotification($otherAccount);

        $this->authenticatedSession($ownerToken)
            ->patchJson(
                "/web/customer/notifications/{$notificationId}/read"
            )
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'read_at' => null,
        ]);
    }

    public function test_customer_can_delete_own_notification(): void
    {
        [$account, $token] = $this->createCustomer();

        $notificationId = $this->createNotification($account);

        $this->authenticatedSession($token)
            ->deleteJson(
                "/web/customer/notifications/{$notificationId}"
            )
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notificationId,
        ]);
    }

    public function test_customer_cannot_delete_another_accounts_notification(): void
    {
        [, $ownerToken] = $this->createCustomer();
        [$otherAccount] = $this->createCustomer();

        $notificationId = $this->createNotification($otherAccount);

        $this->authenticatedSession($ownerToken)
            ->deleteJson(
                "/web/customer/notifications/{$notificationId}"
            )
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'notifiable_id' => $otherAccount->id,
        ]);
    }

    public function test_customer_can_delete_all_own_notifications_only(): void
    {
        [$account, $token] = $this->createCustomer();
        [$otherAccount] = $this->createCustomer();

        $this->createNotification($account);
        $this->createNotification($account);
        $otherNotificationId = $this->createNotification($otherAccount);

        $this->authenticatedSession($token)
            ->deleteJson('/web/customer/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_total', 2);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $account->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $otherNotificationId,
            'notifiable_id' => $otherAccount->id,
        ]);
    }

    public function test_notification_routes_require_authentication(): void
    {
        $this->getJson('/web/customer/notifications')
            ->assertUnauthorized();

        $this->patchJson(
            '/web/customer/notifications/'.Str::uuid().'/read'
        )->assertUnauthorized();

        $this->deleteJson(
            '/web/customer/notifications/'.Str::uuid()
        )->assertUnauthorized();

        $this->deleteJson('/web/customer/notifications')
            ->assertUnauthorized();
    }
}
