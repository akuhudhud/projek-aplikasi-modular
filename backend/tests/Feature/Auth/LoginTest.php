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

    public function test_suspended_account_can_login(): void
    {
        Account::create([
            'id' => '66666666-6666-4666-8666-666666666666',
            'name' => 'Suspended User',
            'email' => 'suspended@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'SUSPENDED',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'suspended@example.com',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.status', 'SUSPENDED');

        $this->assertNotNull($response->json('data.session.token'));
    }

    public function test_deactivated_account_cannot_login(): void
    {
        Account::create([
            'id' => '77777777-7777-4777-8777-777777777777',
            'name' => 'Deactivated User',
            'email' => 'deactivated@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'DEACTIVATED',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'deactivated@example.com',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is not available for login.',
            ]);
    }

    public function test_deleted_account_cannot_login(): void
    {
        Account::create([
            'id' => '88888888-8888-4888-8888-888888888888',
            'name' => 'Deleted User',
            'email' => 'deleted@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'DELETED',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'deleted@example.com',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is not available for login.',
            ]);
    }

    public function test_five_failed_login_attempts_lock_account_for_thirty_minutes(): void
    {
        Account::create([
            'id' => '99999999-9999-4999-8999-999999999999',
            'name' => 'Locked User',
            'email' => 'locked@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $response = $this->postJson('/api/login', [
                'email' => 'locked@example.com',
                'password' => 'WrongPass1',
            ]);

            $response->assertStatus(401);
        }

        $response = $this->postJson('/api/login', [
            'email' => 'locked@example.com',
            'password' => 'WrongPass1',
        ]);

        $response
            ->assertStatus(423)
            ->assertJson([
                'success' => false,
                'message' => 'Account is temporarily locked.',
            ]);

        $account = Account::where('email', 'locked@example.com')->firstOrFail();

        $this->assertSame(5, $account->failed_login_attempts);
        $this->assertNotNull($account->locked_until);
        $this->assertTrue($account->locked_until->isFuture());
        $this->assertTrue($account->locked_until->lte(now()->addMinutes(30)));
    }

    public function test_login_after_lock_expires_resets_login_security(): void
    {
        Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Expired Lock User',
            'email' => 'expiredlock@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
            'failed_login_attempts' => 5,
            'locked_until' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'expiredlock@example.com',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $account = Account::where('email', 'expiredlock@example.com')->firstOrFail();

        $this->assertSame(0, $account->failed_login_attempts);
        $this->assertNull($account->locked_until);
    }

    public function test_successful_login_resets_failed_login_attempts(): void
    {
        Account::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'name' => 'Reset User',
            'email' => 'reset@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
            'failed_login_attempts' => 3,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'reset@example.com',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $account = Account::where('email', 'reset@example.com')->firstOrFail();

        $this->assertSame(0, $account->failed_login_attempts);
        $this->assertNull($account->locked_until);
    }
}
