<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseCatalogParentStateTest extends TestCase
{
    use RefreshDatabase;

    private function createRoot(): Account
    {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ujian Status Induk Lesen',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticate(
        Account $account,
        string $token
    ): void {
        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function createType(
        bool $active = true
    ): DrivingLicenseType {
        return DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'GENERAL',
            'name' => 'Lesen Am',
            'description' => null,
            'is_active' => $active,
            'sort_order' => 0,
        ]);
    }

    private function createClass(
        DrivingLicenseType $type,
        string $code,
        bool $active
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

    public function test_inactive_parent_hides_children_without_changing_their_status(): void
    {
        $root = $this->createRoot();
        $type = $this->createType();

        $activeClass = $this->createClass($type, 'D', true);
        $inactiveClass = $this->createClass($type, 'B2', false);

        $this->authenticate($root, 'parent-state-hide-token');

        $this->deleteJson(
            '/api/admin/driving-license-types/'.$type->id
        )
            ->assertOk()
            ->assertJsonPath('data.type.is_active', false);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $activeClass->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $inactiveClass->id,
            'is_active' => false,
        ]);

        $this->getJson('/api/driving-license-catalog')
            ->assertOk()
            ->assertJsonMissing(['code' => 'D'])
            ->assertJsonMissing(['code' => 'B2']);
    }

    public function test_reactivating_parent_restores_only_children_that_remain_active(): void
    {
        $root = $this->createRoot();
        $type = $this->createType();

        $activeClass = $this->createClass($type, 'D', true);
        $inactiveClass = $this->createClass($type, 'B2', false);

        $this->authenticate($root, 'parent-state-restore-token');

        $this->deleteJson(
            '/api/admin/driving-license-types/'.$type->id
        )->assertOk();

        $this->patchJson(
            '/api/admin/driving-license-types/'.$type->id,
            ['is_active' => true]
        )
            ->assertOk()
            ->assertJsonPath('data.type.is_active', true);

        $this->getJson('/api/driving-license-catalog')
            ->assertOk()
            ->assertJsonFragment(['code' => 'D'])
            ->assertJsonMissing(['code' => 'B2']);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $activeClass->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $inactiveClass->id,
            'is_active' => false,
        ]);
    }

    public function test_class_cannot_be_activated_when_parent_is_inactive(): void
    {
        $root = $this->createRoot();
        $type = $this->createType(active: false);
        $class = $this->createClass($type, 'D', false);

        $this->authenticate($root, 'parent-state-block-token');

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$class->id,
            ['is_active' => true]
        )
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $class->id,
            'driving_license_type_id' => $type->id,
            'is_active' => false,
        ]);

        $this->assertSame(
            0,
            DB::table('audit_logs')
                ->where('actor_account_id', $root->id)
                ->where(
                    'action',
                    'DRIVING_LICENSE_CATALOG_REACTIVATED'
                )
                ->count()
        );
    }

    public function test_class_cannot_be_moved_to_an_inactive_parent(): void
    {
        $root = $this->createRoot();
        $activeType = $this->createType();

        $inactiveType = DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'COMMERCIAL',
            'name' => 'Lesen Komersial',
            'description' => null,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $class = $this->createClass($activeType, 'D', true);

        $this->authenticate($root, 'parent-state-move-token');

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$class->id,
            ['driving_license_type_id' => $inactiveType->id]
        )
            ->assertUnprocessable();

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $class->id,
            'driving_license_type_id' => $activeType->id,
            'is_active' => true,
        ]);
    }
}
