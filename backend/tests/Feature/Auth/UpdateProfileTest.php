<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithSession(): array
    {
        $account = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Original Name',
            'phone' => '60111111111',
            'email' => 'original@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'update-profile-session-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$account, $token];
    }

    public function test_user_can_update_name_only(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'name' => 'Updated Name',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.name', 'Updated Name')
            ->assertJsonPath('data.account.phone', '60111111111')
            ->assertJsonPath('data.account.email', 'original@example.com');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Name',
            'phone' => '60111111111',
            'email' => 'original@example.com',
        ]);
    }

    public function test_user_cannot_update_phone_through_profile_endpoint(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'phone' => '60222222222',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Original Name',
            'phone' => '60111111111',
            'email' => 'original@example.com',
        ]);
    }

    public function test_user_cannot_update_email_through_profile_endpoint(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'email' => 'updated@example.com',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Original Name',
            'phone' => '60111111111',
            'email' => 'original@example.com',
        ]);
    }

    public function test_user_cannot_update_phone_and_email_through_profile_endpoint(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'name' => 'Updated Name',
            'phone' => '60222222222',
            'email' => 'updated@example.com',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.account.name', 'Updated Name')
            ->assertJsonPath('data.account.phone', '60111111111')
            ->assertJsonPath('data.account.email', 'original@example.com');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Name',
            'phone' => '60111111111',
            'email' => 'original@example.com',
        ]);
    }

    public function test_update_profile_requires_session_token(): void
    {
        $response = $this->patchJson('/api/me', [
            'name' => 'Updated Name',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_update_profile_rejects_invalid_session_token(): void
    {
        $response = $this->withHeader(
            'Authorization',
            'Bearer invalid-session-token'
        )->patchJson('/api/me', [
            'name' => 'Updated Name',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }
}
