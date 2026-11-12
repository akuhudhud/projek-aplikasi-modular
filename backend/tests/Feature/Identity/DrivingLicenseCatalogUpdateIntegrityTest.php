<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseCatalogUpdateIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateRoot(): void
    {
        $root = Account::query()->create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Root Catalog Integrity',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $token = 'catalog-update-integrity-token';

        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $root->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function createType(string $code): DrivingLicenseType
    {
        return DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => 'Jenis '.$code,
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function createClass(
        DrivingLicenseType $type,
        string $code
    ): DrivingLicenseCatalog {
        return DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
            'driving_license_type_id' => $type->id,
            'code' => $code,
            'name' => 'Lesen '.$code,
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_class_code_cannot_be_changed_to_existing_code_under_same_type(): void
    {
        $this->authenticateRoot();

        $type = $this->createType('GENERAL');

        $existingClass = $this->createClass($type, 'D');
        $targetClass = $this->createClass($type, 'B2');

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$targetClass->id,
            ['code' => 'D']
        )->assertUnprocessable();

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $existingClass->id,
            'driving_license_type_id' => $type->id,
            'code' => 'D',
        ]);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $targetClass->id,
            'driving_license_type_id' => $type->id,
            'code' => 'B2',
        ]);

        $this->assertSame(
            1,
            DrivingLicenseCatalog::query()
                ->where('driving_license_type_id', $type->id)
                ->where('code', 'D')
                ->count()
        );
    }

    public function test_class_cannot_be_moved_to_another_type_when_code_already_exists_there(): void
    {
        $this->authenticateRoot();

        $sourceType = $this->createType('GENERAL');
        $targetType = $this->createType('COMMERCIAL');

        $sourceClass = $this->createClass($sourceType, 'D');
        $existingTargetClass = $this->createClass($targetType, 'D');

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$sourceClass->id,
            [
                'driving_license_type_id' => $targetType->id,
            ]
        )->assertUnprocessable();

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $sourceClass->id,
            'driving_license_type_id' => $sourceType->id,
            'code' => 'D',
        ]);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $existingTargetClass->id,
            'driving_license_type_id' => $targetType->id,
            'code' => 'D',
        ]);

        $this->assertSame(
            1,
            DrivingLicenseCatalog::query()
                ->where('driving_license_type_id', $targetType->id)
                ->where('code', 'D')
                ->count()
        );
    }
}
