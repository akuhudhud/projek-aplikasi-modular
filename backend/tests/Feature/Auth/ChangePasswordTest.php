<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithSession(): array
    {
        $account = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Password User',
            'phone' => '60111111111',
            'email' => 'password@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'change-password-session-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$account, $token];
    }

    public function test_user_can_change_password_and_current_session_is_ended(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/change-password', [
            'current_password' => 'Password1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully. Please login again.',
            ]);

        $this->assertTrue(
            Hash::check('NewPassword2', $account->fresh()->password)
        );

        $this->assertNotNull(
            Session::find('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb')->ended_at
        );

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

    public function test_user_can_login_with_new_password_after_changing_password(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/change-password', [
            'current_password' => 'Password1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ])->assertStatus(200);

        $response = $this->postJson('/api/login', [
            'phone' => '60111111111',
            'password' => 'NewPassword2',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'account',
                    'session' => [
                        'token',
                    ],
                ],
            ]);

        $newToken = $response->json('data.session.token');

        $this->assertNotEmpty($newToken);
        $this->assertNotSame($token, $newToken);

        $this->assertDatabaseHas('sessions', [
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
        ]);

        $this->assertNotNull(
            Session::find('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb')->ended_at
        );

        $this->assertDatabaseCount('sessions', 2);

        $activeSessions = Session::query()
            ->where('account_id', $account->id)
            ->whereNull('ended_at')
            ->get();

        $this->assertCount(1, $activeSessions);

        $this->withHeader(
            'Authorization',
            'Bearer '.$newToken
        )->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath(
                'data.account.id',
                $account->id
            );
    }

    public function test_change_password_rejects_incorrect_current_password(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/change-password', [
            'current_password' => 'WrongPassword1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ]);

        $this->assertTrue(
            Hash::check('Password1', $account->fresh()->password)
        );

        $this->assertNull(
            Session::find('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb')->ended_at
        );
    }

    public function test_change_password_rejects_same_password(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/change-password', [
            'current_password' => 'Password1',
            'new_password' => 'Password1',
            'new_password_confirmation' => 'Password1',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);

        $this->assertTrue(
            Hash::check('Password1', $account->fresh()->password)
        );

        $this->assertNull(
            Session::find('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb')->ended_at
        );
    }

    public function test_change_password_rejects_invalid_confirmation(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/change-password', [
            'current_password' => 'Password1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'DifferentPassword2',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'new_password_confirmation',
            ]);

        $this->assertTrue(
            Hash::check('Password1', $account->fresh()->password)
        );

        $this->assertNull(
            Session::find('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb')->ended_at
        );
    }

    public function test_change_password_requires_session_token(): void
    {
        $response = $this->postJson('/api/me/change-password', [
            'current_password' => 'Password1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }
}
