<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_phone(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'phone' => '60123456789',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.name', 'Test User')
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

        $this->assertDatabaseHas('accounts', [
            'phone' => '60123456789',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $account = Account::where('phone', '60123456789')->firstOrFail();

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'ended_at' => null,
        ]);

        $this->assertNotNull($response->json('data.session.token'));
    }

    public function test_user_can_register_with_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Email User',
            'email' => 'email@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.email', 'email@example.com')
            ->assertJsonPath('data.account.role', 'USER')
            ->assertJsonPath('data.account.status', 'ACTIVE');

        $this->assertDatabaseHas('accounts', [
            'email' => 'email@example.com',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $account = Account::where('email', 'email@example.com')->firstOrFail();

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'ended_at' => null,
        ]);
    }

    public function test_registration_requires_phone_or_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'No Contact User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'phone',
                'email',
            ]);
    }

    public function test_registration_rejects_duplicate_phone(): void
    {
        Account::create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Existing User',
            'phone' => '60123456789',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Duplicate User',
            'phone' => '60123456789',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        Account::create([
            'id' => '22222222-2222-4222-8222-222222222222',
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Duplicate User',
            'email' => 'existing@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_invalid_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Weak Password User',
            'email' => 'weak@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_rejects_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Mismatch User',
            'email' => 'mismatch@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password2',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password_confirmation']);
    }
}
