<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountLifecycleReasonValidationTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(string $status): array
    {
        $admin = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Root Admin',
            'phone' => '601'.random_int(100000000, 999999999),
            'email' => 'root-'.Str::random(8).'@example.com',
            'password' => 'Password1',
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $target = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Target User',
            'phone' => '602'.random_int(100000000, 999999999),
            'email' => 'target-'.Str::random(8).'@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => $status,
        ]);

        $token = 'lifecycle-reason-'.Str::random(32);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$target, $token];
    }

    public function test_suspend_rejects_array_reason(): void
    {
        $this->assertInvalidReasonRejected(
            'ACTIVE',
            'suspend',
            ['invalid']
        );
    }

    public function test_suspend_rejects_reason_over_1000_characters(): void
    {
        $this->assertInvalidReasonRejected(
            'ACTIVE',
            'suspend',
            str_repeat('a', 1001)
        );
    }

    public function test_reactivate_rejects_array_reason(): void
    {
        $this->assertInvalidReasonRejected(
            'DEACTIVATED',
            'reactivate',
            ['invalid']
        );
    }

    public function test_reactivate_rejects_reason_over_1000_characters(): void
    {
        $this->assertInvalidReasonRejected(
            'DEACTIVATED',
            'reactivate',
            str_repeat('a', 1001)
        );
    }

    public function test_unsuspend_rejects_array_reason(): void
    {
        $this->assertInvalidReasonRejected(
            'SUSPENDED',
            'unsuspend',
            ['invalid']
        );
    }

    public function test_unsuspend_rejects_reason_over_1000_characters(): void
    {
        $this->assertInvalidReasonRejected(
            'SUSPENDED',
            'unsuspend',
            str_repeat('a', 1001)
        );
    }

    private function assertInvalidReasonRejected(
        string $initialStatus,
        string $action,
        mixed $reason
    ): void {
        [$target, $token] = $this->createAccount($initialStatus);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$target->id.'/'.$action,
            ['reason' => $reason]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'status' => $initialStatus,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $target->id,
            'action' => match ($action) {
                'suspend' => 'ACCOUNT_SUSPENDED',
                'reactivate' => 'ACCOUNT_REACTIVATED',
                'unsuspend' => 'ACCOUNT_UNSUSPENDED',
            },
        ]);
    }
}
