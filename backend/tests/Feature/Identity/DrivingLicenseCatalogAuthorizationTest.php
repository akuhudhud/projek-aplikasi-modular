<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseCatalogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(string $role): Account
    {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ujian Akses Katalog Lesen',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticate(Account $account, string $token): void
    {
        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function createCatalogEntry(): DrivingLicenseCatalog
    {
        $type = DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'GENERAL',
            'name' => 'Lesen Am',
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
            'driving_license_type_id' => $type->id,
            'code' => 'D',
            'name' => 'Lesen D',
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function assertAllAdminEndpointsForbidden(
        Account $account,
        string $token
    ): void {
        $entry = $this->createCatalogEntry();
        $this->authenticate($account, $token);

        $this->getJson('/api/admin/driving-license-catalog')
            ->assertForbidden();

        $this->postJson('/api/admin/driving-license-catalog', [
            'code' => 'B2',
            'name' => 'Lesen B2',
        ])->assertForbidden();

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$entry->id,
            ['name' => 'Nama Diubah']
        )->assertForbidden();

        $this->deleteJson(
            '/api/admin/driving-license-catalog/'.$entry->id
        )->assertForbidden();

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $entry->id,
            'code' => 'D',
            'name' => 'Lesen D',
            'is_active' => true,
        ]);

        $this->assertDatabaseMissing('driving_license_catalog', [
            'code' => 'B2',
        ]);
    }

    public function test_regular_user_cannot_access_any_admin_catalog_endpoint(): void
    {
        $user = $this->createAccount('USER');

        $this->assertAllAdminEndpointsForbidden(
            $user,
            'regular-user-catalog-token'
        );
    }

    public function test_super_admin_without_permission_cannot_access_any_admin_catalog_endpoint(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->assertAllAdminEndpointsForbidden(
            $admin,
            'unprivileged-admin-catalog-token'
        );

        $this->assertDatabaseMissing('account_permissions', [
            'account_id' => $admin->id,
            'permission_key' => 'driving_license_catalog.manage',
        ]);
    }

    public function test_catalog_permission_is_the_specific_permission_required(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        AccountPermission::query()->create([
            'account_id' => $admin->id,
            'permission_key' => 'accounts.view',
        ]);

        $this->assertAllAdminEndpointsForbidden(
            $admin,
            'wrong-permission-catalog-token'
        );
    }
}
