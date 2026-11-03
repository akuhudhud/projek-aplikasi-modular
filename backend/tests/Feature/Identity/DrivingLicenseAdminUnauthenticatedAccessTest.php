<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseAdminUnauthenticatedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_any_admin_license_type_endpoint(): void
    {
        $typeId = (string) Str::uuid();

        $this->getJson('/api/admin/driving-license-types')
            ->assertUnauthorized();

        $this->postJson('/api/admin/driving-license-types', [
            'code' => 'PDL',
            'name' => 'Lesen Percubaan',
        ])->assertUnauthorized();

        $this->patchJson(
            '/api/admin/driving-license-types/'.$typeId,
            ['name' => 'Nama Baharu']
        )->assertUnauthorized();

        $this->deleteJson(
            '/api/admin/driving-license-types/'.$typeId
        )->assertUnauthorized();

        $this->assertDatabaseCount('driving_license_types', 0);
        $this->assertDatabaseCount('driving_license_catalog', 0);
    }

    public function test_unauthenticated_user_cannot_access_any_admin_license_catalog_endpoint(): void
    {
        $licenseId = (string) Str::uuid();

        $this->getJson('/api/admin/driving-license-catalog')
            ->assertUnauthorized();

        $this->postJson('/api/admin/driving-license-catalog', [
            'driving_license_type_id' => (string) Str::uuid(),
            'code' => 'D',
            'name' => 'Lesen D',
        ])->assertUnauthorized();

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$licenseId,
            ['name' => 'Nama Baharu']
        )->assertUnauthorized();

        $this->deleteJson(
            '/api/admin/driving-license-catalog/'.$licenseId
        )->assertUnauthorized();

        $this->assertDatabaseCount('driving_license_types', 0);
        $this->assertDatabaseCount('driving_license_catalog', 0);
    }
}
