<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_change_rejects_email_channel(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'email',
            'contact' => 'user@example.com',
            'purpose' => 'CHANGE_PHONE',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'channel',
            ]);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_email_change_rejects_phone_channel(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'CHANGE_EMAIL',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'channel',
            ]);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_phone_channel_rejects_invalid_contact_format(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => 'not-a-phone-number',
            'purpose' => 'REGISTRATION',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'contact',
            ]);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_email_channel_rejects_invalid_contact_format(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'email',
            'contact' => 'not-an-email-address',
            'purpose' => 'REGISTRATION',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'contact',
            ]);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_registration_accepts_valid_phone_contact(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('registration_verifications', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);
    }

    public function test_registration_accepts_valid_email_contact(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'email',
            'contact' => 'user@example.com',
            'purpose' => 'REGISTRATION',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('registration_verifications', [
            'channel' => 'email',
            'contact' => 'user@example.com',
            'purpose' => 'REGISTRATION',
        ]);
    }
}
