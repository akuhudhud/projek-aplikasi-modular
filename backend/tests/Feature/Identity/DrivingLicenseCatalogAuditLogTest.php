<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseCatalogAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function createRoot(): Account
    {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ujian Audit Katalog',
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

    public function test_catalog_mutations_are_recorded_in_audit_logs(): void
    {
        $root = $this->createRoot();

        $this->authenticate($root, 'catalog-audit-test-token');

        $createResponse = $this->postJson(
            '/api/admin/driving-license-catalog',
            [
                'code' => 'D',
                'name' => 'Lesen D',
            ]
        );

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true);

        $licenseId = $createResponse->json('data.license.id');

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $root->id,
            'action' => 'DRIVING_LICENSE_CATALOG_CREATED',
            'reason' => 'Created driving license catalog entry D.',
        ]);

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$licenseId,
            ['name' => 'Lesen D Dikemas Kini']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.license.name',
                'Lesen D Dikemas Kini'
            );

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $root->id,
            'action' => 'DRIVING_LICENSE_CATALOG_UPDATED',
            'reason' => 'Updated driving license catalog entry D.',
        ]);

        $this->deleteJson(
            '/api/admin/driving-license-catalog/'.$licenseId
        )
            ->assertOk()
            ->assertJsonPath('data.license.is_active', false);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $root->id,
            'action' => 'DRIVING_LICENSE_CATALOG_DEACTIVATED',
            'reason' => 'Deactivated driving license catalog entry D.',
        ]);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $licenseId,
            'code' => 'D',
            'is_active' => false,
        ]);
    }
}
