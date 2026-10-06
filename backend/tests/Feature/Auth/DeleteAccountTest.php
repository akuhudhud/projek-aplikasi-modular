<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithSession(): array
    {
        $account = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Delete User',
            'phone' => '60111111111',
            'email' => 'delete@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'delete-account-session-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$account, $token];
    }

    public function test_user_can_delete_own_account(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/delete');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DELETED',
        ]);
    }

    public function test_deletion_ends_current_session(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/delete')
            ->assertStatus(200);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
        ]);

        $session = Session::where('account_id', $account->id)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        $this->assertNotNull($session);
        $this->assertNotNull($session->ended_at);
    }

    public function test_deleted_account_cannot_use_old_session(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/delete')
            ->assertStatus(200);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/me');

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_deleted_account_cannot_login(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/delete')
            ->assertStatus(200);

        $response = $this->postJson('/api/login', [
            'phone' => '60111111111',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is not available for login.',
            ]);
    }

    public function test_deletion_requires_session_token(): void
    {
        $response = $this->postJson('/api/me/delete');

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_deleted_account_cannot_delete_again(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/delete')
            ->assertStatus(200);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/delete');

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }
}
