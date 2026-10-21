<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithSession(
        string $accountId,
        string $sessionId,
        string $token,
        string $name = 'Current User'
    ): Account {
        $account = Account::create([
            'id' => $accountId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        Session::create([
            'id' => $sessionId,
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $account;
    }

    private function createIdentityVerification(
        Account $account,
        string $status,
        string $realName = 'Nama Sebenar Pengguna'
    ): IdentityVerification {
        return IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => $realName,
            'mykad_number_encrypted' => Crypt::encryptString('900101145678'),
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }

    public function test_user_can_get_current_account_with_valid_session_token(): void
    {
        $account = $this->createAccountWithSession(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'current-session-token'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer current-session-token'
        )->getJson('/api/me');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'account' => [
                        'id' => $account->id,
                        'name' => 'Current User',
                        'real_name' => null,
                        'phone' => null,
                        'email' => 'current.user@example.com',
                        'profile_picture' => null,
                        'role' => 'USER',
                        'status' => 'ACTIVE',
                    ],
                ],
            ])
            ->assertJsonMissing([
                'password' => 'Password1',
            ])
            ->assertJsonMissing([
                'token_hash' => hash(
                    'sha256',
                    'current-session-token'
                ),
            ])
            ->assertJsonMissing([
                'mykad_number' => '900101145678',
            ]);
    }

    public function test_me_displays_real_name_when_identity_is_verified(): void
    {
        $account = $this->createAccountWithSession(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'verified-identity-profile-token',
            'Verified User'
        );

        $this->createIdentityVerification(
            $account,
            IdentityVerification::STATUS_VERIFIED,
            'Nama Disahkan'
        );

        $this->withHeader(
            'Authorization',
            'Bearer verified-identity-profile-token'
        )->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath(
                'data.account.real_name',
                'Nama Disahkan'
            )
            ->assertJsonMissing([
                'mykad_number_encrypted' => '900101145678',
            ])
            ->assertJsonMissing([
                'mykad_number' => '900101145678',
            ]);
    }

    public function test_me_hides_real_name_when_identity_is_pending(): void
    {
        $account = $this->createAccountWithSession(
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'pending-identity-profile-token',
            'Pending User'
        );

        $this->createIdentityVerification(
            $account,
            IdentityVerification::STATUS_PENDING,
            'Nama Belum Disahkan'
        );

        $this->withHeader(
            'Authorization',
            'Bearer pending-identity-profile-token'
        )->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.account.real_name', null)
            ->assertJsonMissing([
                'real_name' => 'Nama Belum Disahkan',
            ]);
    }

    public function test_me_hides_real_name_when_identity_is_rejected(): void
    {
        $account = $this->createAccountWithSession(
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            'rejected-identity-profile-token',
            'Rejected User'
        );

        $this->createIdentityVerification(
            $account,
            IdentityVerification::STATUS_REJECTED,
            'Nama Ditolak'
        );

        $this->withHeader(
            'Authorization',
            'Bearer rejected-identity-profile-token'
        )->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.account.real_name', null)
            ->assertJsonMissing([
                'real_name' => 'Nama Ditolak',
            ]);
    }

    public function test_me_requires_session_token(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_me_rejects_invalid_session_token(): void
    {
        $this->withHeader(
            'Authorization',
            'Bearer invalid-session-token'
        )->getJson('/api/me')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_me_does_not_modify_account_or_session(): void
    {
        $account = $this->createAccountWithSession(
            '33333333-3333-4333-8333-333333333333',
            '44444444-4444-4444-8444-444444444444',
            'readonly-session-token',
            'Read Only User'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer readonly-session-token'
        )->getJson('/api/me');

        $response->assertStatus(200);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Read Only User',
            'email' => 'read.only.user@example.com',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => '44444444-4444-4444-8444-444444444444',
            'account_id' => $account->id,
            'ended_at' => null,
        ]);
    }
}
