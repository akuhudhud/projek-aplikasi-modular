<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactChangeTest extends TestCase
{
    use RefreshDatabase;

    private int $phoneCounter = 10000000;

    private function createAccountWithSession(
        string $accountId,
        string $token,
        array $overrides = []
    ): Account {
        $account = Account::create(array_merge([
            'id' => $accountId,
            'name' => 'Test User',
            'phone' => '601'.$this->phoneCounter++,
            'email' => str_replace('-', '', $accountId).'@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ], $overrides));

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $account;
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    public function test_change_phone_request_sends_otp(): void
    {
        $accountId = '11111111-1111-4111-8111-111111111111';
        $token = 'change-phone-request-token';

        $this->createAccountWithSession(
            $accountId,
            $token
        );

        $response = $this->withHeaders(
            $this->authHeaders($token)
        )->postJson('/api/me/change-phone/request', [
            'phone' => '60199999999',
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

        $this->assertDatabaseHas(
            'registration_verifications',
            [
                'account_id' => $accountId,
                'channel' => 'phone',
                'contact' => '60199999999',
                'purpose' => 'CHANGE_PHONE',
            ]
        );
    }

    public function test_change_phone_request_requires_authentication(): void
    {
        $response = $this->postJson(
            '/api/me/change-phone/request',
            [
                'phone' => '60199999999',
            ]
        );

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_change_phone_request_rejects_invalid_phone(): void
    {
        $accountId = '22222222-2222-4222-8222-222222222222';
        $token = 'change-phone-invalid-token';

        $this->createAccountWithSession(
            $accountId,
            $token
        );

        $response = $this->withHeaders(
            $this->authHeaders($token)
        )->postJson('/api/me/change-phone/request', [
            'phone' => 'invalid-phone',
        ]);

        $response->assertStatus(422);
    }

    public function test_change_phone_request_rejects_existing_phone(): void
    {
        $accountId = '33333333-3333-4333-8333-333333333333';
        $token = 'change-phone-existing-token';

        $this->createAccountWithSession(
            $accountId,
            $token
        );

        $otherId = '44444444-4444-4444-8444-444444444444';

        $this->createAccountWithSession(
            $otherId,
            'change-phone-other-token',
            [
                'phone' => '60199999999',
            ]
        );

        $response = $this->withHeaders(
            $this->authHeaders($token)
        )->postJson('/api/me/change-phone/request', [
            'phone' => '60199999999',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This contact is already in use.',
            ]);
    }

    public function test_suspended_account_cannot_request_phone_change(): void
    {
        $accountId = '12121212-1212-4121-8121-121212121212';
        $token = 'suspended-phone-token';

        $this->createAccountWithSession(
            $accountId,
            $token,
            [
                'status' => 'SUSPENDED',
            ]
        );

        $response = $this->withHeaders(
            $this->authHeaders($token)
        )->postJson('/api/me/change-phone/request', [
            'phone' => '60198888888',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is suspended.',
            ]);
    }

    public function test_change_phone_can_be_verified_and_completed(): void
    {
        $accountId = '55555555-5555-4555-8555-555555555555';
        $token = 'change-phone-complete-token';

        $this->createAccountWithSession(
            $accountId,
            $token
        );

        $request = $this->withHeaders(
            $this->authHeaders($token)
        )->postJson('/api/me/change-phone/request', [
            'phone' => '60198888888',
        ]);

        $request->assertOk();

        $verificationId = $request->json(
            'data.verification_id'
        );

        $verify = $this->withHeaders(
            $this->authHeaders($token)
        )->postJson('/api/me/change-phone/verify', [
            'verification_id' => $verificationId,
            'otp' => '123456',
        ]);

        $verify
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'OTP verified successfully.',
            ]);

        $verificationToken = $verify->json(
            'data.verification_token'
        );

        $complete = $this->withHeaders(
            $this->authHeaders($token)
        )->postJson('/api/me/change-phone/complete', [
            'verification_token' => $verificationToken,
        ]);

        $complete
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Contact information updated successfully.',
            ]);

        $this->assertDatabaseHas(
            'accounts',
            [
                'id' => $accountId,
                'phone' => '60198888888',
            ]
        );

        $this->assertNotNull(
            Account::find($accountId)->phone_verified_at
        );
    }

    public function test_change_phone_cannot_be_verified_by_another_account(): void
    {
        $ownerId = '66666666-6666-4666-8666-666666666666';
        $ownerToken = 'change-phone-owner-token';

        $otherId = '77777777-7777-4777-8777-777777777777';
        $otherToken = 'change-phone-other-token';

        $this->createAccountWithSession(
            $ownerId,
            $ownerToken
        );

        $this->createAccountWithSession(
            $otherId,
            $otherToken
        );

        $request = $this->withHeaders(
            $this->authHeaders($ownerToken)
        )->postJson('/api/me/change-phone/request', [
            'phone' => '60197777777',
        ]);

        $request->assertOk();

        $verificationId = $request->json(
            'data.verification_id'
        );

        $response = $this->withHeaders(
            $this->authHeaders($otherToken)
        )->postJson('/api/me/change-phone/verify', [
            'verification_id' => $verificationId,
            'otp' => '123456',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Verification request does not belong to the authenticated account.',
            ]);
    }

    public function test_change_phone_cannot_complete_with_token_from_another_account(): void
    {
        $ownerId = '88888888-8888-4888-8888-888888888888';
        $ownerToken = 'change-phone-complete-owner-token';

        $otherId = '99999999-9999-4999-8999-999999999999';
        $otherToken = 'change-phone-complete-other-token';

        $this->createAccountWithSession(
            $ownerId,
            $ownerToken
        );

        $this->createAccountWithSession(
            $otherId,
            $otherToken
        );

        $request = $this->withHeaders(
            $this->authHeaders($ownerToken)
        )->postJson('/api/me/change-phone/request', [
            'phone' => '60196666666',
        ]);

        $request->assertOk();

        $verificationId = $request->json(
            'data.verification_id'
        );

        $verify = $this->withHeaders(
            $this->authHeaders($ownerToken)
        )->postJson('/api/me/change-phone/verify', [
            'verification_id' => $verificationId,
            'otp' => '123456',
        ]);

        $verify->assertOk();

        $verificationToken = $verify->json(
            'data.verification_token'
        );

       
