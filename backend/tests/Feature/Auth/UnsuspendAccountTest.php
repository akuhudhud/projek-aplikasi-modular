<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnsuspendAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_unsuspend_suspended_user(): void
    {
        $admin = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'phone' => '0190000001',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $user = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Suspended User',
            'phone' => '0190000002',
            'email' => 'user@example.com',
            'password' => 'password123',
            'role' => 'USER',
            'status' => 'SUSPENDED',
        ]);

        $adminToken = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $adminToken),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->withToken($adminToken)
            ->postJson("/api/admin/accounts/{$user->id}/unsuspend");

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account unsuspended successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_regular_user_cannot_unsuspend_account(): void
    {
        $user = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Regular User',
            'phone' => '0190000011',
            'email' => 'regular@example.com',
            'password' => 'password123',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $target = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Suspended User',
            'phone' => '0190000012',
            'email' => 'suspended@example.com',
            'password' => 'password123',
            'role' => 'USER',
            'status' => 'SUSPENDED',
        ]);

        $token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->withToken($token)
            ->postJson("/api/admin/accounts/{$target->id}/unsuspend");

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_unsuspend_requires_session_token(): void
    {
        $target = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Suspended User',
            'phone' => '0190000021',
            'email' => 'suspended2@example.com',
            'password' => 'password123',
            'role' => 'USER',
            'status' => 'SUSPENDED',
        ]);

        $response = $this->postJson(
            "/api/admin/accounts/{$target->id}/unsuspend"
        );

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_active_account_cannot_be_unsuspended(): void
    {
        $admin = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'phone' => '0190000031',
            'email' => 'admin2@example.com',
            'password' => 'password123',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $target = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Active User',
            'phone' => '0190000032',
            'email' => 'active@example.com',
            'password' => 'password123',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $adminToken = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $adminToken),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->withToken($adminToken)
            ->postJson("/api/admin/accounts/{$target->id}/unsuspend");

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Only suspended accounts can be unsuspended.',
            ]);
    }

    public function test_unsuspend_nonexistent_account_returns_not_found(): void
    {
        $admin = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'phone' => '0190000041',
            'email' => 'admin3@example.com',
            'password' => 'password123',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $adminToken = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $adminToken),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->withToken($adminToken)
            ->postJson('/api/admin/accounts/non-existent-account/unsuspend');

        $response
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Account not found.',
            ]);
    }
}
