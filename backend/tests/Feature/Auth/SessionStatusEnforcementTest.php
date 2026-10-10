<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionStatusEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_account_can_access_authenticated_endpoint(): void
    {
        $token = 'active-account-session-token';

        $account = $this->createAccountWithSession(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            '60111111111',
            'active-session@example.com',
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'ACTIVE',
            $token
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/me');

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_suspended_account_cannot_access_authenticated_endpoint(): void
    {
        $token = 'suspended-account-session-token';

        $account = $this->createAccountWithSession(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            '60222222222',
            'suspended-session@example.com',
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'SUSPENDED',
            $token
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/me');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is suspended.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'SUSPENDED',
        ]);
    }

    public function test_deactivated_account_cannot_access_authenticated_endpoint(): void
    {
        $token = 'deactivated-account-session-token';

        $account = $this->createAccountWithSession(
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            '60333333333',
            'deactivated-session@example.com',
            'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'DEACTIVATED',
            $token
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/me');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DEACTIVATED',
        ]);
    }

    public function test_deleted_account_cannot_access_authenticated_endpoint(): void
    {
        $token = 'deleted-account-session-token';

        $account = $this->createAccountWithSession(
            '11111111-1111-4111-8111-111111111111',
            '60444444444',
            'deleted-session@example.com',
            '22222222-2222-4222-8222-222222222222',
            'DELETED',
            $token
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/me');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DELETED',
        ]);
    }

    private function createAccountWithSession(
        string $accountId,
        string $phone,
        string $email,
        string $sessionId,
        string $status,
        string $token
    ): Account {
        $account = Account::create([
            'id' => $accountId,
            'name' => 'Session Status Test',
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => $status,
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
}
