<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationOtpGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_cannot_create_account_without_otp_verification(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'phone' => '60123456789',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertNotSame(201, $response->status());

        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('sessions', 0);
    }
}
