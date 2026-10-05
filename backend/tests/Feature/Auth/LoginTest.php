<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_phone(): void
    {
        Account::create([
            'id' => '33333333-3333-4333-8333-333333333333',
            'name' => 'Phone User',
            'phone' => '60123456789',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/login', [
            'phone' => '60123456789',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.name', 'Phone User')
            ->assertJsonPath('data.account.phone', '60123456789')
            ->assertJsonPath('data.account.role', 'USER')
            ->assertJsonPath('data.account.status', 'ACTIVE')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'account' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                        'profile_picture',
                        'role',
                        'status',
                    ],
                    'session' => [
                        'token',
                    ],
                ],
            ]);

        $account = Account::where('phone', '60123456789')->firstOrFail();

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'ended_at' => null,
        ]);

        $this->assertNotNull($response->json('data.session.token'));
    }

    public function test_user_can_login_with_email(): void
    {
        Account::create([
            'id' => '44444444-4444-4444-8444-444444444444',
            'name' => 'Email User',
            'email' => 'email@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'email@example.com',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.name', 'Email User')
            ->assertJsonPath('data.account.email', 'email@example.com')
            ->assertJsonPath('data.account.role', 'USER')
            ->assertJsonPath('data.account.status', 'ACTIVE');

        $account = Account::where('email', 'email@example.com')->firstOrFail();

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'ended_at' => null,
        ]);

        $this->assertNotNull($response->json('data.session.token'));
    }

    public function test_login_rejects_invalid_password(): void
    {
        Account::create([
            'id' => '55555555-5555-4555-8555-555555555555',
            'name' => 'Password User',
            'email' => 'password@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'password@example.com',
            'password' => 'WrongPass1',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'email' => 'password@example.com',
            'failed_login_attempts' => 1,
            'locked_until' => null,
        ]);
    }

    public function test_login_rejects_unknown_account(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_login_requires_phone_or_email(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'phone',
                'email',
            ]);
    }
}
