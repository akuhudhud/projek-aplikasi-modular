<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerChangePasswordWebApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithSessions(): array
    {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Password Customer',
            'phone' => '60123456789',
            'email' => 'password-customer@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $tokens = [];

        foreach (range(1, 2) as $index) {
            $token = Str::random(64);

            Session::create([
                'id' => (string) Str::uuid(),
                'account_id' => $account->id,
                'token_hash' => hash('sha256', $token),
                'created_at' => now(),
                'last_activity_at' => now(),
                'ended_at' => null,
                'end_reason' => null,
            ]);

            $tokens[] = $token;
        }

        return [$account, $tokens];
    }

    public function test_customer_can_change_password_through_web_session(): void
    {
        [$account, $tokens] = $this->createAccountWithSessions();

        $response = $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $tokens[0],
        ])->postJson('/web/auth/change-password', [
            'current_password' => 'Password1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Password changed successfully. Please login again.',
            );

        $this->assertTrue(
            Hash::check(
                'NewPassword2',
                $account->fresh()->password,
            )
        );

        $sessions = Session::query()
            ->where('account_id', $account->id)
            ->get();

        $this->assertCount(2, $sessions);

        foreach ($sessions as $session) {
            $this->assertNotNull($session->ended_at);
        }
    }

    public function test_web_password_change_rejects_incorrect_current_password(): void
    {
        [$account, $tokens] = $this->createAccountWithSessions();

        $response = $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $tokens[0],
        ])->postJson('/web/auth/change-password', [
            'current_password' => 'WrongPassword1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Current password is incorrect.',
            );

        $this->assertTrue(
            Hash::check(
                'Password1',
                $account->fresh()->password,
            )
        );

        $this->assertSame(
            0,
            Session::query()
                ->where('account_id', $account->id)
                ->whereNotNull('ended_at')
                ->count(),
        );
    }

    public function test_web_password_change_requires_authentication(): void
    {
        $this->postJson('/web/auth/change-password', [
            'current_password' => 'Password1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ])->assertUnauthorized();
    }
}
