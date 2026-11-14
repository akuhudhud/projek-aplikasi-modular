<?php

namespace Tests\Feature\Notifications;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneOldNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(
        int $daysOld,
        ?string $readAt = null
    ): string {
        $id = (string) Str::uuid();
        $createdAt = now()->subDays($daysOld);

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => Account::class,
            'notifiable_id' => (string) Str::uuid(),
            'data' => json_encode([
                'title' => 'Notifikasi ujian',
                'message' => 'Ujian tempoh penyimpanan.',
            ], JSON_THROW_ON_ERROR),
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }

    public function test_command_deletes_notifications_older_than_90_days(): void
    {
        $oldNotification = $this->createNotification(91);

        $this->artisan('notifications:prune')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'id' => $oldNotification,
        ]);
    }

    public function test_command_deletes_old_read_and_unread_notifications(): void
    {
        $oldUnread = $this->createNotification(91);

        $oldRead = $this->createNotification(
            95,
            now()->subDays(94)->toDateTimeString()
        );

        $this->artisan('notifications:prune')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'id' => $oldUnread,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'id' => $oldRead,
        ]);
    }

    public function test_command_keeps_notifications_within_90_days(): void
    {
        $recentNotification = $this->createNotification(30);
        $boundaryNotification = $this->createNotification(90);

        $this->artisan('notifications:prune')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'id' => $recentNotification,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $boundaryNotification,
        ]);
    }
}
