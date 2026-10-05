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

    public function test_user_can_update_phone_only(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'phone' => '60222222222',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.account.name', 'Original Name')
            ->assertJsonPath('data.account.phone', '60222222222')
            ->assertJsonPath('data.account.email', 'original@example.com');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Original Name',
            'phone' => '60222222222',
            'email' => 'original@example.com',
        ]);
    }

    public function test_user_can_update_email_only(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'email' => 'updated@example.com',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.account.name', 'Original Name')
            ->assertJsonPath('data.account.phone', '60111111111')
            ->assertJsonPath('data.account.email', 'updated@example.com');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Original Name',
            'phone' => '60111111111',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_user_can_update_multiple_profile_fields(): void
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
            ->assertJsonPath('data.account.phone', '60222222222')
            ->assertJsonPath('data.account.email', 'updated@example.com');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Name',
            'phone' => '60222222222',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_update_profile_rejects_duplicate_phone(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        Account::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'name' => 'Other User',
            'phone' => '60333333333',
            'email' => 'other@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'phone' => '60333333333',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'phone' => '60111111111',
        ]);
    }

    public function test_update_profile_rejects_duplicate_email(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        Account::create([
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'name' => 'Other User',
            'phone' => '60444444444',
            'email' => 'other@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'email' => 'other@example.com',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
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
