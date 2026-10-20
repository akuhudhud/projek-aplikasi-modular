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

class DrivingLicenseCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(string $role = 'USER'): Account
    {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ujian Katalog Lesen',
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

    private function grantCatalogPermission(Account $account): void
    {
        AccountPermission::query()->create([
            'account_id' => $account->id,
            'permission_key' => 'driving_license_catalog.manage',
        ]);
    }

    private function createType(
        string $code = 'GENERAL',
        bool $active = true
    ): DrivingLicenseType {
        return DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => 'Jenis '.$code,
            'description' => null,
            'is_active' => $active,
            'sort_order' => 0,
        ]);
    }

    private function createCatalogEntry(
        DrivingLicenseType $type,
        string $code,
        bool $active = true
    ): DrivingLicenseCatalog {
        return DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
            'driving_license_type_id' => $type->id,
            'code' => $code,
            'name' => 'Lesen '.$code,
            'description' => null,
            'is_active' => $active,
            'sort_order' => 0,
        ]);
    }

    public function test_root_can_create_class_under_a_license_type(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $type = $this->createType();

        $this->authenticate($root, 'root-create-class-token');

        $this->postJson('/api/admin/driving-license-catalog', [
            'driving_license_type_id' => $type->id,
            'code' => '  d ',
            'name' => 'Lesen D',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.license.code', 'D')
            ->assertJsonPath(
                'data.license.driving_license_type_id',
                $type->id
            );

        $this->assertDatabaseHas('driving_license_catalog', [
            'driving_license_type_id' => $type->id,
            'code' => 'D',
            'name' => 'Lesen D',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_without_permission_is_forbidden(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $type = $this->createType();

        $this->authenticate($admin, 'catalog-denied-token');

        $this->getJson('/api/admin/driving-license-catalog')
            ->assertForbidden();

        $this->postJson('/api/admin/driving-license-catalog', [
            'driving_license_type_id' => $type->id,
            'code' => 'D',
            'name' => 'Lesen D',
        ])->assertForbidden();

        $this->assertDatabaseMissing('driving_license_catalog', [
            'driving_license_type_id' => $type->id,
            'code' => 'D',
        ]);
    }

    public function test_admin_with_permission_can_create_update_and_deactivate_class(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantCatalogPermission($admin);
        $type = $this->createType();

        $this->authenticate($admin, 'catalog-create-token');

        $response = $this->postJson('/api/admin/driving-license-catalog', [
            'driving_license_type_id' => $type->id,
            'code' => 'D',
            'name' => 'Lesen D',
        ])
            ->assertCreated()
            ->assertJsonPath('data.license.code', 'D');

        $classId = $response->json('data.license.id');

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$classId,
            ['name' => 'Lesen D Dikemas Kini']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.license.name',
                'Lesen D Dikemas Kini'
            );

        $this->deleteJson(
            '/api/admin/driving-license-catalog/'.$classId
        )
            ->assertOk()
            ->assertJsonPath('data.license.is_active', false);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $classId,
            'driving_license_type_id' => $type->id,
            'name' => 'Lesen D Dikemas Kini',
            'is_active' => false,
        ]);
    }

    public function test_public_catalog_returns_only_active_classes(): void
    {
        $account = $this->createAccount();
        $type = $this->createType();

        $this->createCatalogEntry($type, 'D', true);
        $this->createCatalogEntry($type, 'B2', false);

        $this->authenticate($account, 'catalog-public-token');

        $this->getJson('/api/driving-license-catalog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['code' => 'D'])
            ->assertJsonMissing(['code' => 'B2']);
    }

    public function test_duplicate_class_code_is_rejected_within_same_type(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $type = $this->createType();

        $this->createCatalogEntry($type, 'B2');

        $this->authenticate($root, 'catalog-duplicate-token');

        $this->postJson('/api/admin/driving-license-catalog', [
            'driving_license_type_id' => $type->id,
            'code' => ' b2 ',
            'name' => 'Duplikasi B2',
        ])->assertUnprocessable();

        $this->assertSame(
            1,
            DrivingLicenseCatalog::query()
                ->where('driving_license_type_id', $type->id)
                ->where('code', 'B2')
                ->count()
        );
    }

    public function test_same_class_code_can_exist_under_different_types(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $typeA = $this->createType('TYPE_A');
        $typeB = $this->createType('TYPE_B');

        $this->createCatalogEntry($typeA, 'D');

        $this->authenticate($root, 'catalog-cross-type-token');

        $this->postJson('/api/admin/driving-license-catalog', [
            'driving_license_type_id' => $typeB->id,
            'code' => 'D',
            'name' => 'Lesen D Jenis B',
        ])->assertCreated();

        $this->assertSame(
            1,
            DrivingLicenseCatalog::query()
                ->where('driving_license_type_id', $typeA->id)
                ->where('code', 'D')
                ->count()
        );

        $this->assertSame(
            1,
            DrivingLicenseCatalog::query()
                ->where('driving_license_type_id', $typeB->id)
                ->where('code', 'D')
                ->count()
        );
    }

    public function test_class_cannot_be_created_under_nonexistent_type(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $this->authenticate($root, 'catalog-invalid-type-token');

        $this->postJson('/api/admin/driving-license-catalog', [
            'driving_license_type_id' => (string) Str::uuid(),
            'code' => 'D',
            'name' => 'Lesen D',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('driving_license_catalog', 0);
    }
}
