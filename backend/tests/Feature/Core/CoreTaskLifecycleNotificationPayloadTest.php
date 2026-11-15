<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceRequest;
use App\Notifications\MarketplaceEventNotification;
use App\Services\CoreTaskLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreTaskLifecycleNotificationPayloadTest extends TestCase
{
    use RefreshDatabase;

    private CoreTaskLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifecycle = new CoreTaskLifecycleService();
    }

    public function test_start_notification_contains_task_and_request_ids(): void
    {
        [$customer, $runner, $task] = $this->createMarketplaceTask(
            CoreTask::STATUS_ASSIGNED
        );

        $this->lifecycle->start(
            $task->task_id,
            $runner->id
        );

        $notification = $customer->notifications()
            ->where('type', MarketplaceEventNotification::class)
            ->latest()
            ->first();

        $this->assertNotNull($notification);

        $this->assertSame(
            'marketplace.task_started',
            $notification->data['event']
        );

        $this->assertSame(
            $task->task_id,
            $notification->data['task_id']
        );

        $this->assertSame(
            $task->source_id,
            $notification->data['request_id']
        );
    }

    public function test_completion_notification_contains_task_and_request_ids(): void
    {
        [$customer, $runner, $task] = $this->createMarketplaceTask(
            CoreTask::STATUS_IN_PROGRESS,
            true
        );

        $this->lifecycle->complete(
            $task->task_id,
            $runner->id
        );

        $notification = $customer->notifications()
            ->where('type', MarketplaceEventNotification::class)
            ->latest()
            ->first();

        $this->assertNotNull($notification);

        $this->assertSame(
            'marketplace.task_completed',
            $notification->data['event']
        );

        $this->assertSame(
            $task->task_id,
            $notification->data['task_id']
        );

        $this->assertSame(
            $task->source_id,
            $notification->data['request_id']
        );
    }

    /**
     * @return array{Account, Account, CoreTask}
     */
    private function createMarketplaceTask(
        string $status,
        bool $started = false
    ): array {
        $customer = $this->createAccount();
        $runner = $this->createAccount();

        $request = MarketplaceRequest::query()->create([
            'request_id' => (string) Str::uuid(),
            'customer_account_id' => $customer->id,
            'target_runner_account_id' => null,
            'source_type' => MarketplaceRequest::SOURCE_MARKETPLACE,
            'task_class' => MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY,
            'description' => 'Ujian payload notifikasi lifecycle.',
            'pickup_location_snapshot' => [
                'address' => 'Lokasi pengambilan',
            ],
            'delivery_location_snapshot' => [
                'address' => 'Lokasi penghantaran',
            ],
            'task_location_snapshot' => null,
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'expires_at' => now()->addHours(2),
            'awarded_at' => now(),
        ]);

        $task = CoreTask::query()->create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'Marketplace',
            'task_type' => 'MARKETPLACE_SERVICE_REQUEST',
            'source_type' => 'marketplace_request',
            'source_id' => $request->request_id,
            'assigned_account_id' => $runner->id,
            'generic_status' => $status,
            'assigned_at' => now(),
            'started_at' => $started ? now() : null,
        ]);

        return [$customer, $runner, $task];
    }

    private function createAccount(): Account
    {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Lifecycle Notification Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $account->runner_status = Account::RUNNER_STATUS_APPROVED;
        $account->save();

        return $account->fresh();
    }
}
