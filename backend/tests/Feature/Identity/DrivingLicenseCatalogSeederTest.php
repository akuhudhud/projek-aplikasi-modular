<?php

namespace Tests\Feature\Identity;

use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use Database\Seeders\DrivingLicenseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_does_not_insert_hardcoded_license_classes(): void
    {
        $this->seed(DrivingLicenseCatalogSeeder::class);

        $this->assertDatabaseCount('driving_license_catalog', 0);
    }

    public function test_seeder_does_not_overwrite_admin_managed_catalog_entries(): void
    {
        $type = DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'GENERAL',
            'name' => 'Lesen Am',
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $license = DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
            'driving_license_type_id' => $type->id,
            'code' => 'B2',
            'name' => 'Nama Diubah Admin',
            'description' => 'Rekod yang diurus oleh admin.',
            'is_active' => false,
            'sort_order' => 999,
        ]);

        $this->seed(DrivingLicenseCatalogSeeder::class);

        $license->refresh();

        $this->assertSame('Nama Diubah Admin', $license->name);
        $this->assertSame(
            'Rekod yang diurus oleh admin.',
            $license->description
        );
        $this->assertSame(999, $license->sort_order);
        $this->assertFalse($license->is_active);
        $this->assertSame($type->id, $license->driving_license_type_id);

        $this->assertSame(
            1,
            DrivingLicenseCatalog::query()
                ->where('driving_license_type_id', $type->id)
                ->where('code', 'B2')
                ->count()
        );
    }
}
