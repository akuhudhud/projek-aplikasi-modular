<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminCreationTest extends TestCase
{
    use RefreshDatabase;

    private function createSessionForAccount(
        Account $account,
        string $token,
        string $sessionId
    ): void {
        Session::create([
            'id' => $sessionId,
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function createAccount(
        string $id,
        string $name,
        string $phone,
        string $email,
        string $role,
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => $id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function createRootSuperAdmin(): array
    {
        $root = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $token = 'root-super-admin-creation-token';

        $this->createSessionForAccount(
            $root,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        return [$root, $token];
    }

    public function test_root_super_admin_can_create_super_admin(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Super Admin appointed for platform administration.',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Super Admin created successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'action' => 'SUPER_ADMIN_CREATED',
            'reason' => 'Super Admin appointed for platform administration.',
        ]);
    }

    public function test_super_admin_cannot_create_super_admin(): void
    {
        $admin = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Super Admin',
            '60111111111',
            'admin@example.com',
            'SUPER_ADMIN'
        );

        $token = 'super-admin-creation-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'Another Super Admin',
            'phone' => '60222222222',
            'email' => 'anotheradmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Attempted unauthorized Super Admin creation.',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseMissing('accounts', [
            'email' => 'anotheradmin@example.com',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'SUPER_ADMIN_CREATED',
        ]);
    }

    public function test_regular_user_cannot_create_super_admin(): void
    {
        $user = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Regular User',
            '60111111111',
            'user@example.com',
            'USER'
        );

        $token = 'regular-user-creation-token';

        $this->createSessionForAccount(
            $user,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'Another Super Admin',
            'phone' => '60222222222',
            'email' => 'anotheradmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Attempted unauthorized Super Admin creation.',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('accounts', [
            'email' => 'anotheradmin@example.com',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'SUPER_ADMIN_CREATED',
        ]);
    }

    public function test_super_admin_creation_requires_session_token(): void
    {
        $response = $this->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Super Admin appointed for platform administration.',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_super_admin_creation_requires_reason(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Reason is required.',
            ]);

        $this->assertDatabaseMissing('accounts', [
            'email' => 'newadmin@example.com',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $root->id,
            'action' => 'SUPER_ADMIN_CREATED',
        ]);
    }

    public function test_super_admin_creation_rejects_blank_reason(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => '   ',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Reason is required.',
            ]);

        $this->assertDatabaseMissing('accounts', [
            'email' => 'newadmin@example.com',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $root->id,
            'action' => 'SUPER_ADMIN_CREATED',
        ]);
    }

    public function test_public_registration_creates_user_not_super_admin(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Public Registered User',
            'phone' => '60333333333',
            'email' => 'publicuser@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('accounts', [
            'email' => 'publicuser@example.com',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseMissing('accounts', [
            'email' => 'publicuser@example.com',
            'role' => 'SUPER_ADMIN',
        ]);
    }

    public function test_created_super_admin_response_contains_safe_fields_only(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Super Admin appointed for platform administration.',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'account' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                        'profile_picture',
                        'role',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $response->assertJsonMissing([
            'password' => 'Password1',
        ]);

        $response->assertJsonMissing([
            'reason' => 'Super Admin appointed for platform administration.',
        ]);

        $response->assertJsonMissing([
            'token_hash' => hash('sha256', 'root-super-admin-creation-token'),
        ]);
    }

    public function test_created_super_admin_has_no_session_created_automatically(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Super Admin appointed for platform administration.',
        ]);

        $response->assertStatus(201);

        $newAdmin = Account::query()
            ->where('email', 'newadmin@example.com')
            ->firstOrFail();

        $this->assertDatabaseCount('sessions', 1);

        $this->assertDatabaseHas('accounts', [
            'id' => $newAdmin->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $newAdmin->id,
            'action' => 'SUPER_ADMIN_CREATED',
        ]);
    }
}
