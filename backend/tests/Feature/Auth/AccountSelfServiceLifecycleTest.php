<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSelfServiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createDeactivationAccountWithSession(): array
    {
        $account = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Deactivate User',
            'phone' => '60111111111',
            'email' => 'deactivate@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'deactivate-session-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$account, $token];
    }

    private function createDeletionAccountWithSession(): array
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

    /*
    |--------------------------------------------------------------------------
    | Account deactivation
    |--------------------------------------------------------------------------
    */

    public function test_user_can_deactivate_own_account(): void
    {
        [$account, $token] = $this->createDeactivationAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/deactivate');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deactivated successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DEACTIVATED',
        ]);
    }

    public function test_deactivation_ends_current_session(): void
    {
        [$account, $token] = $this->createDeactivationAccountWithSession();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/deactivate')
            ->assertStatus(200);

        $session = Session::find(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $this->assertNotNull($session);
        $this->assertNotNull($session->ended_at);
    }

    public function test_deactivated_account_cannot_use_old_session(): void
    {
        [$account, $token] = $this->createDeactivationAccountWithSession();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/deactivate')
            ->assertStatus(200);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/me')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_deactivated_account_cannot_login(): void
    {
        [$account, $token] = $this->createDeactivationAccountWithSession();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/deactivate')
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

    public function test_deactivation_requires_session_token(): void
    {
        $response = $this->postJson('/api/me/deactivate');

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Account deletion
    |--------------------------------------------------------------------------
    */

    public function test_user_can_delete_own_account(): void
    {
        [$account, $token] = $this->createDeletionAccountWithSession();

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
        [$account, $token] = $this->createDeletionAccountWithSession();

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
        [$account, $token] = $this->createDeletionAccountWithSession();

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
        [$account, $token] = $this->createDeletionAccountWithSession();

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
        [$account, $token] = $this->createDeletionAccountWithSession();

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
