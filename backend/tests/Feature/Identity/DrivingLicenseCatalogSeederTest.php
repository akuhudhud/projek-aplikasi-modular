<?php

namespace Tests\Feature\Identity;

use App\Models\DrivingLicenseCatalog;
use Database\Seeders\DrivingLicenseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrivingLicenseCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_initial_jpj_license_classes(): void
    {
        $this->seed(DrivingLicenseCatalogSeeder::class);

        foreach ([
            'A',
            'A1',
            'B2',
            'B',
            'C',
            'D',
            'DA',
            'E',
            'F',
            'G',
            'H',
            'I',
        ] as $code) {
            $this->assertDatabaseHas('driving_license_catalog', [
                'code' => $code,
                'is_active' => true,
            ]);
        }
    }

    public function test_seeder_does_not_overwrite_existing_admin_changes(): void
    {
        $this->seed(DrivingLicenseCatalogSeeder::class);

        $license = DrivingLicenseCatalog::query()
            ->where('code', 'B2')
            ->firstOrFail();

        $license->name = 'Nama Diubah Admin';
        $license->sort_order = 999;
        $license->is_active = false;
        $license->save();

        $this->seed(DrivingLicenseCatalogSeeder::class);

        $license->refresh();

        $this->assertSame('Nama Diubah Admin', $license->name);
        $this->assertSame(999, $license->sort_order);
        $this->assertFalse($license->is_active);

        $this->assertSame(
            1,
            DrivingLicenseCatalog::query()
                ->where('code', 'B2')
                ->count()
        );
    }
}
