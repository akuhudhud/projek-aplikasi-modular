<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerProfileWebApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Original Customer',
            'phone' => '60123456789',
            'email' => 'customer@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticatedRequest(Account $account): static
    {
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

        return $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);
    }

    public function test_authenticated_customer_can_update_profile_name(): void
    {
        $account = $this->createAccount();

        $response = $this->authenticatedRequest($account)
            ->patchJson('/web/auth/profile', [
                'profile_name' => 'Updated Customer',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.profile_name', 'Updated Customer')
            ->assertJsonPath('data.account.id', $account->id);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'profile_name' => 'Updated Customer',
        ]);
    }

    public function test_profile_update_requires_web_authentication(): void
    {
        $this->patchJson('/web/auth/profile', [
            'profile_name' => 'Updated Customer',
        ])->assertUnauthorized();
    }

    public function test_profile_update_rejects_name_longer_than_255_characters(): void
    {
        $account = $this->createAccount();

        $response = $this->authenticatedRequest($account)
            ->patchJson('/web/auth/profile', [
                'profile_name' => str_repeat('A', 256),
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_name']);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'profile_name' => 'Original Customer',
        ]);
    }

    public function test_profile_update_rejects_non_string_name(): void
    {
        $account = $this->createAccount();

        $response = $this->authenticatedRequest($account)
            ->patchJson('/web/auth/profile', [
                'profile_name' => ['Invalid'],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_name']);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'profile_name' => 'Original Customer',
        ]);
    }
}
