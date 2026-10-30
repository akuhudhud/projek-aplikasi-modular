<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\RegistrationVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registration_with_phone_requires_otp_verification(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'phone' => '60123456789',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'OTP verification is required before registration can be completed.',
            ]);

        $this->assertDatabaseMissing('accounts', [
            'phone' => '60123456789',
        ]);

        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_user_registration_with_email_requires_otp_verification(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Email User',
            'email' => 'email@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'OTP verification is required before registration can be completed.',
            ]);

        $this->assertDatabaseMissing('accounts', [
            'email' => 'email@example.com',
        ]);

        $this->assertDatabaseCount('sessions', 0);
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

    public function test_registration_completion_requires_verification_token(): void
    {
        $response = $this->postJson('/api/register/complete', [
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_registration_completion_creates_active_user_and_session(): void
    {
        $verificationToken = str_repeat('a', 64);

        RegistrationVerification::create([
            'id' => (string) Str::uuid(),
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', $verificationToken),
            'token_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/register/complete', [
            'verification_token' => $verificationToken,
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'account',
                    'session' => [
                        'token',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'name' => 'Test User',
            'phone' => '60123456789',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $verification = RegistrationVerification::query()
            ->where('purpose', 'REGISTRATION')
            ->first();

        $this->assertNotNull($verification?->consumed_at);

        $this->assertDatabaseCount('sessions', 1);
    }

    public function test_registration_completion_cannot_reuse_verification_token(): void
    {
        $verificationToken = str_repeat('b', 64);

        RegistrationVerification::create([
            'id' => (string) Str::uuid(),
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', $verificationToken),
            'token_expires_at' => now()->addMinutes(10),
            'consumed_at' => now(),
        ]);

        $response = $this->postJson('/api/register/complete', [
            'verification_token' => $verificationToken,
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_registration_completion_rejects_expired_verification_token(): void
    {
        $verificationToken = str_repeat('c', 64);

        RegistrationVerification::create([
            'id' => (string) Str::uuid(),
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', $verificationToken),
            'token_expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/register/complete', [
            'verification_token' => $verificationToken,
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('sessions', 0);
    }
}
