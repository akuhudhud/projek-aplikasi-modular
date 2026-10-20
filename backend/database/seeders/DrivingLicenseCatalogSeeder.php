<?php

namespace Database\Seeders;

use App\Models\DrivingLicenseCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DrivingLicenseCatalogSeeder extends Seeder
{
    /**
     * Initial driving license classes based on JPJ references.
     *
     * This is bootstrap data, not a hardcoded validation allowlist.
     * Existing catalog entries are never overwritten.
     */
    public function run(): void
    {
        $classes = [
            ['code' => 'A', 'name' => 'Kelas A', 'sort_order' => 10],
            ['code' => 'A1', 'name' => 'Kelas A1', 'sort_order' => 20],
            ['code' => 'B2', 'name' => 'Kelas B2', 'sort_order' => 30],
            ['code' => 'B', 'name' => 'Kelas B', 'sort_order' => 40],
            ['code' => 'C', 'name' => 'Kelas C', 'sort_order' => 50],
            ['code' => 'D', 'name' => 'Kelas D', 'sort_order' => 60],
            ['code' => 'DA', 'name' => 'Kelas DA', 'sort_order' => 70],
            ['code' => 'E', 'name' => 'Kelas E', 'sort_order' => 80],
            ['code' => 'F', 'name' => 'Kelas F', 'sort_order' => 90],
            ['code' => 'G', 'name' => 'Kelas G', 'sort_order' => 100],
            ['code' => 'H', 'name' => 'Kelas H', 'sort_order' => 110],
            ['code' => 'I', 'name' => 'Kelas I', 'sort_order' => 120],
        ];

        foreach ($classes as $class) {
            DrivingLicenseCatalog::query()->firstOrCreate(
                ['code' => $class['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $class['name'],
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => $class['sort_order'],
                ]
            );
        }
    }
}
