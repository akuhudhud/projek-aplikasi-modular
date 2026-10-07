<?php

namespace Tests\Feature\Auth;

use App\Models\RegistrationVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompleteRegistrationTest extends TestCase
{
    use RefreshDatabase;

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
