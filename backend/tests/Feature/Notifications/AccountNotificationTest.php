<?php

namespace Tests\Feature\Notifications;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_can_store_database_notification(): void
    {
        $account = $this->makeAccount();

        $notification = new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toDatabase(object $notifiable): array
            {
                return [
                    'title' => 'Sebutharga baharu',
                    'message' => 'Runner menghantar sebut harga.',
                    'request_id' => (string) Str::uuid(),
                    'action' => 'VIEW_REQUEST',
                ];
            }
        };

        $account->notify($notification);

        $this->assertDatabaseCount('notifications', 1);

        $this->assertDatabaseHas('notifications', [
            'type' => $notification::class,
            'notifiable_type' => Account::class,
            'notifiable_id' => $account->id,
            'read_at' => null,
        ]);

        $stored = DB::table('notifications')->first();

        $data = json_decode(
            $stored->data,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            'Sebutharga baharu',
            $data['title']
        );

        $this->assertSame(
            'VIEW_REQUEST',
            $data['action']
        );
    }

    public function test_notification_is_only_related_to_its_recipient(): void
    {
        $owner = $this->makeAccount();
        $otherAccount = $this->makeAccount();

        $notification = new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toDatabase(object $notifiable): array
            {
                return [
                    'title' => 'Notifikasi peribadi',
                    'message' => 'Maklumat untuk penerima sahaja.',
                ];
            }
        };

        $owner->notify($notification);

        $this->assertCount(1, $owner->notifications);
        $this->assertCount(0, $otherAccount->notifications);
    }

    private function makeAccount(): Account
    {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'profile_name' => 'Notification Test',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => Account::RUNNER_STATUS_NOT_RUNNER,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Account::query()->findOrFail($id);
    }
}
