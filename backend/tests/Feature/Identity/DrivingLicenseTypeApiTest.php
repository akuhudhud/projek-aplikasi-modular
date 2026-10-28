<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DrivingLicenseTypeApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(string $role = 'ROOT_SUPER_ADMIN'): Account
    {
        return Account::factory()->create([
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function grantPermission(Account $account, string $permission): void
    {
        DB::table('account_permissions')->insert([
            'account_id' => $account->id,
            'permission' => $permission,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_root_can_create_update_deactivate_and_reactivate_a_license_type(): void
    {
        $root = $this->createAccount();

        $this->actingAs($root);

        $createResponse = $this->postJson('/api/admin/driving-license-types', [
            'code' => '  pdl  ',
            'name' => '  Lesen Percubaan  ',
            'description' => '  Jenis lesen untuk ujian  ',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $createResponse
            ->assertSuccessful()
            ->assertJsonPath('data.type.code', 'PDL')
            ->assertJsonPath('data.type.name', 'Lesen Percubaan')
            ->assertJsonPath(
                'data.type.description',
                'Jenis lesen untuk ujian'
            );

        $typeId = $createResponse->json('data.type.id');

        $this->assertNotNull($typeId);

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $typeId,
            'code' => 'PDL',
            'name' => 'Lesen Percubaan',
            'description' => 'Jenis lesen untuk ujian',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'DRIVING_LICENSE_TYPE_CREATED',
        ]);

        $updateResponse = $this->patchJson(
            "/api/admin/driving-license-types/{$typeId}",
            [
                'name' => 'Lesen Memandu Percubaan',
                'sort_order' => 20,
            ]
        );

        $updateResponse
            ->assertSuccessful()
            ->assertJsonPath(
                'data.type.name',
                'Lesen Memandu Percubaan'
            )
            ->assertJsonPath('data.type.sort_order', 20);

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $typeId,
            'name' => 'Lesen Memandu Percubaan',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'DRIVING_LICENSE_TYPE_UPDATED',
        ]);

        $deactivateResponse = $this->deleteJson(
            "/api/admin/driving-license-types/{$typeId}"
        );

        $deactivateResponse->assertSuccessful();

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $typeId,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'DRIVING_LICENSE_TYPE_DEACTIVATED',
        ]);

        $reactivateResponse = $this->patchJson(
            "/api/admin/driving-license-types/{$typeId}",
            [
                'is_active' => true,
            ]
        );

        $reactivateResponse
            ->assertSuccessful()
            ->assertJsonPath('data.type.is_active', true);

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $typeId,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'DRIVING_LICENSE_TYPE_REACTIVATED',
        ]);
    }

    public function test_super_admin_without_management_permission_cannot_manage_license_types(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->actingAs($admin);

        $this->getJson('/api/admin/driving-license-types')
            ->assertForbidden();

        $this->postJson('/api/admin/driving-license-types', [
            'code' => 'PDL',
            'name' => 'Lesen Percubaan',
        ])->assertForbidden();

        $this->patchJson('/api/admin/driving-license-types/1', [
            'name' => 'Nama Baharu',
        ])->assertForbidden();

        $this->deleteJson('/api/admin/driving-license-types/1')
            ->assertForbidden();
    }

    public function test_unrelated_permission_does_not_grant_license_type_management_access(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermission($admin, 'runner_profiles.manage');

        $this->actingAs($admin);

        $this->getJson('/api/admin/driving-license-types')
            ->assertForbidden();

        $this->postJson('/api/admin/driving-license-types', [
            'code' => 'CDL',
            'name' => 'Lesen Kompeten',
        ])->assertForbidden();
    }

    public function test_duplicate_license_type_code_is_rejected_without_creating_another_type(): void
    {
        $root = $this->createAccount();

        $this->actingAs($root);

        $firstResponse = $this->postJson('/api/admin/driving-license-types', [
            'code' => 'CDL',
            'name' => 'Lesen Kompeten',
        ]);

        $firstResponse->assertSuccessful();

        $auditCountBefore = DB::table('audit_logs')
            ->where('event', 'DRIVING_LICENSE_TYPE_CREATED')
            ->count();

        $duplicateResponse = $this->postJson(
            '/api/admin/driving-license-types',
            [
                'code' => ' cdl ',
                'name' => 'Lesen Kompeten Duplikat',
            ]
        );

        $duplicateResponse->assertUnprocessable();

        $this->assertSame(
            1,
            DB::table('driving_license_types')
                ->where('code', 'CDL')
                ->count()
        );

        $this->assertSame(
            $auditCountBefore,
            DB::table('audit_logs')
                ->where('event', 'DRIVING_LICENSE_TYPE_CREATED')
                ->count()
        );
    }

    public function test_public_list_returns_only_active_license_types_in_sort_order(): void
    {
        $root = $this->createAccount();

        $this->actingAs($root);

        $this->postJson('/api/admin/driving-license-types', [
            'code' => 'TYPE_B',
            'name' => 'Lesen B',
            'sort_order' => 20,
            'is_active' => true,
        ])->assertSuccessful();

        $this->postJson('/api/admin/driving-license-types', [
            'code' => 'TYPE_A',
            'name' => 'Lesen A',
            'sort_order' => 10,
            'is_active' => true,
        ])->assertSuccessful();

        $inactiveResponse = $this->postJson(
            '/api/admin/driving-license-types',
            [
                'code' => 'TYPE_C',
                'name' => 'Lesen C',
                'sort_order' => 30,
                'is_active' => false,
            ]
        );

        $inactiveResponse->assertSuccessful();

        $response = $this->getJson('/api/driving-license-types');

        $response->assertSuccessful();

        $types = $response->json('data');

        $this->assertIsArray($types);

        $codes = array_column($types, 'code');

        $this->assertContains('TYPE_A', $codes);
        $this->assertContains('TYPE_B', $codes);
        $this->assertNotContains('TYPE_C', $codes);

        $typeAIndex = array_search('TYPE_A', $codes, true);
        $typeBIndex = array_search('TYPE_B', $codes, true);

        $this->assertNotFalse($typeAIndex);
        $this->assertNotFalse($typeBIndex);
        $this->assertLessThan($typeBIndex, $typeAIndex);
    }

    public function test_deactivating_a_license_type_preserves_the_record(): void
    {
        $root = $this->createAccount();

        $this->actingAs($root);

        $createResponse = $this->postJson('/api/admin/driving-license-types', [
            'code' => 'KEEP',
            'name' => 'Jenis Kekal',
        ]);

        $createResponse->assertSuccessful();

        $typeId = $createResponse->json('data.type.id');

        $this->deleteJson(
            "/api/admin/driving-license-types/{$typeId}"
        )->assertSuccessful();

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $typeId,
            'code' => 'KEEP',
            'name' => 'Jenis Kekal',
            'is_active' => false,
        ]);

        $this->assertDatabaseMissing('driving_license_types', [
            'id' => $typeId,
            'deleted_at' => now(),
        ]);
    }
}
