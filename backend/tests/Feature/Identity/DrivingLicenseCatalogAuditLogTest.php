<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
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

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function createLicenseType(): DrivingLicenseType
    {
        return DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'GENERAL',
            'name' => 'Lesen Am',
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function createLicenseClass(
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

    private function assertExactlyOneAudit(
        Account $root,
        string $action,
        string $reason
    ): void {
        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('audit_logs')
                ->where('actor_account_id', $root->id)
                ->where('target_account_id', $root->id)
                ->where('action', $action)
                ->where('reason', $reason)
                ->count(),
            "Expected exactly one audit record for {$action}."
        );
    }

    public function test_catalog_mutations_create_exactly_one_audit_record_per_action(): void
    {
        $root = $this->createRoot();
        $type = $this->createLicenseType();

        $this->authenticate(
            $root,
            'catalog-audit-test-token'
        );

        $createResponse = $this->postJson(
            '/api/admin/driving-license-catalog',
            [
                'driving_license_type_id' => $type->id,
                'code' => 'D',
                'name' => 'Lesen D',
            ]
        );

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true);

        $licenseId = $createResponse->json('data.license.id');

        $this->assertNotEmpty($licenseId);

        $this->assertExactlyOneAudit(
            $root,
            'DRIVING_LICENSE_CATALOG_CREATED',
            'Created driving license class D under type GENERAL.'
        );

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$licenseId,
            ['name' => 'Lesen D Dikemas Kini']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.license.name',
                'Lesen D Dikemas Kini'
            );

        $this->assertExactlyOneAudit(
            $root,
            'DRIVING_LICENSE_CATALOG_UPDATED',
            'Updated driving license class D.'
        );

        $this->deleteJson(
            '/api/admin/driving-license-catalog/'.$licenseId
        )
            ->assertOk()
            ->assertJsonPath(
                'data.license.is_active',
                false
            );

        $this->assertExactlyOneAudit(
            $root,
            'DRIVING_LICENSE_CATALOG_DEACTIVATED',
            'Deactivated driving license class D.'
        );

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $licenseId,
            'driving_license_type_id' => $type->id,
            'code' => 'D',
            'is_active' => false,
        ]);
    }

    public function test_patch_status_changes_create_one_specific_audit_each(): void
    {
        $root = $this->createRoot();
        $type = $this->createLicenseType();

        $this->authenticate(
            $root,
            'catalog-audit-status-token'
        );

        $license = $this->createLicenseClass(
            $type,
            'B2'
        );

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$license->id,
            ['is_active' => false]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.license.is_active',
                false
            );

        $this->assertExactlyOneAudit(
            $root,
            'DRIVING_LICENSE_CATALOG_DEACTIVATED',
            'Deactivated driving license class B2.'
        );

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$license->id,
            ['is_active' => true]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.license.is_active',
                true
            );

        $this->assertExactlyOneAudit(
            $root,
            'DRIVING_LICENSE_CATALOG_REACTIVATED',
            'Reactivated driving license class B2.'
        );
    }

    public function test_repeated_deactivation_does_not_create_duplicate_audit(): void
    {
        $root = $this->createRoot();
        $type = $this->createLicenseType();

        $this->authenticate(
            $root,
            'catalog-audit-repeat-token'
        );

        $license = $this->createLicenseClass(
            $type,
            'C'
        );

        $endpoint = '/api/admin/driving-license-catalog/'.$license->id;

        $this->deleteJson($endpoint)
            ->assertOk()
            ->assertJsonPath(
                'data.license.is_active',
                false
            );

        $this->deleteJson($endpoint)
            ->assertOk()
            ->assertJsonPath(
                'data.license.is_active',
                false
            );

        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('audit_logs')
                ->where('actor_account_id', $root->id)
                ->where('target_account_id', $root->id)
                ->where(
                    'action',
                    'DRIVING_LICENSE_CATALOG_DEACTIVATED'
                )
                ->where(
                    'reason',
                    'Deactivated driving license class C.'
                )
                ->count()
        );
    }
}
