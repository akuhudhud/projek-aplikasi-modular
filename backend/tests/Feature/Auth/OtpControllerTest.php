<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_request_returns_verification_id(): void
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
                'message' => 'OTP sent successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'verification_id',
                    'expires_at',
                ],
            ]);

        $this->assertDatabaseCount(
            'registration_verifications',
            1
        );
    }

    public function test_otp_request_rejects_invalid_channel(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'whatsapp',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);

        $response->assertStatus(422);
    }

    public function test_otp_request_rejects_invalid_purpose(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'LOGIN',
        ]);

        $response->assertStatus(422);
    }

    public function test_otp_verify_returns_verification_token(): void
    {
        $request = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);

        $verificationId = $request->json(
            'data.verification_id'
        );

        $response = $this->postJson('/api/otp/verify', [
            'verification_id' => $verificationId,
            'otp' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'OTP verified successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'verification_token',
                    'expires_at',
                ],
            ]);
    }

    public function test_otp_verify_rejects_invalid_otp(): void
    {
        $request = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);

        $verificationId = $request->json(
            'data.verification_id'
        );

        $response = $this->postJson('/api/otp/verify', [
            'verification_id' => $verificationId,
            'otp' => '000000',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ]);
    }

    public function test_otp_verify_returns_not_found_for_unknown_verification(): void
    {
        $response = $this->postJson('/api/otp/verify', [
            'verification_id' => '00000000-0000-0000-0000-000000000000',
            'otp' => '123456',
        ]);

        $response
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Verification request not found.',
            ]);
    }
}
