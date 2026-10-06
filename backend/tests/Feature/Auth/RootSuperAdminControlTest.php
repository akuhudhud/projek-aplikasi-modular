<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RootSuperAdminControlTest extends TestCase
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

        $token = 'root-super-admin-control-token';

        $this->createSessionForAccount(
            $root,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        return [$root, $token];
    }

    private function createSuperAdmin(): Account
    {
        return $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Super Admin',
            '60222222222',
            'superadmin@example.com',
            'SUPER_ADMIN'
        );
    }

    public function test_root_super_admin_can_suspend_super_admin(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();
        $superAdmin = $this->createSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$superAdmin->id.'/suspend', [
            'reason' => 'Administrative review required.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account suspended successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $superAdmin->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'SUSPENDED',
        ]);
    }

    public function test_super_admin_cannot_suspend_another_super_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $target = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Target Super Admin',
            '60333333333',
            'target-superadmin@example.com',
            'SUPER_ADMIN'
        );

        $token = 'regular-super-admin-control-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$target->id.'/suspend');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_super_admin_cannot_suspend_root_super_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $root = $this->createAccount(
            'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'Root Super Admin',
            '60444444444',
            'root-target@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $token = 'super-admin-root-control-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            '11111111-1111-4111-8111-111111111111'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$root->id.'/suspend');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $root->id,
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_root_super_admin_can_unsuspend_super_admin(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $superAdmin = $this->createAccount(
            '22222222-2222-4222-8222-222222222222',
            'Suspended Super Admin',
            '60555555555',
            'suspended-superadmin@example.com',
            'SUPER_ADMIN',
            'SUSPENDED'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$superAdmin->id.'/unsuspend', [
            'reason' => 'Administrative review completed.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account unsuspended successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $superAdmin->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_super_admin_cannot_unsuspend_another_super_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $target = $this->createAccount(
            '33333333-3333-4333-8333-333333333333',
            'Suspended Target Super Admin',
            '60666666666',
            'suspended-target@example.com',
            'SUPER_ADMIN',
            'SUSPENDED'
        );

        $token = 'regular-super-admin-unsuspend-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            '44444444-4444-4444-8444-444444444444'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$target->id.'/unsuspend');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'SUSPENDED',
        ]);
    }

    public function test_root_super_admin_can_reactivate_super_admin(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $superAdmin = $this->createAccount(
            '55555555-5555-4555-8555-555555555555',
            'Deactivated Super Admin',
            '60777777777',
            'deactivated-superadmin@example.com',
            'SUPER_ADMIN',
            'DEACTIVATED'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$superAdmin->id.'/reactivate', [
            'reason' => 'Reactivation approved by root administrator.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account reactivated successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $superAdmin->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_super_admin_cannot_reactivate_another_super_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $target = $this->createAccount(
            '66666666-6666-4666-8666-666666666666',
            'Deactivated Target Super Admin',
            '60888888888',
            'deactivated-target@example.com',
            'SUPER_ADMIN',
            'DEACTIVATED'
        );

        $token = 'regular-super-admin-reactivate-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            '77777777-7777-4777-8777-777777777777'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$target->id.'/reactivate');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'DEACTIVATED',
        ]);
    }
}
