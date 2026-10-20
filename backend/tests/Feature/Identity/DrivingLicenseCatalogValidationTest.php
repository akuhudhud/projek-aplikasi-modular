<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseCatalogValidationTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(string $role): Account
    {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ujian Validasi Katalog',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
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

    public function test_public_catalog_requires_authentication(): void
    {
        $this->getJson('/api/driving-license-catalog')
            ->assertUnauthorized();
    }

    public function test_blank_name_is_rejected_when_creating_entry(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $this->authenticate($root, 'catalog-blank-create-token');

        $this->postJson('/api/admin/driving-license-catalog', [
            'code' => 'B2',
            'name' => '   ',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseMissing('driving_license_catalog', [
            'code' => 'B2',
        ]);
    }

    public function test_blank_name_is_rejected_when_updating_entry(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $entry = $this->createCatalogEntry();

        $this->authenticate($root, 'catalog-blank-update-token');

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$entry->id,
            ['name' => '   ']
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $entry->id,
            'name' => 'Lesen D',
        ]);
    }
}
