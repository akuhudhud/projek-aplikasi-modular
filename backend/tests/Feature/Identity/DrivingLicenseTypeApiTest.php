<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\DrivingLicenseType;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseTypeApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(string $role = 'USER'): Account
    {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ujian Jenis Lesen',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticate(Account $account, string $token): void
    {
        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function grantPermission(
        Account $account,
        string $permission
    ): void {
        AccountPermission::query()->create([
            'account_id' => $account->id,
            'permission_key' => $permission,
        ]);
    }

    private function createType(
        string $code,
        string $name,
        bool $active = true,
        int $sortOrder = 0
    ): DrivingLicenseType {
        return DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => $name,
            'description' => null,
            'is_active' => $active,
            'sort_order' => $sortOrder,
        ]);
    }

    private function createRunnerLicenseVerification(
        Account $runner,
        string $licenseType
    ): RunnerLicenseVerification {
        return RunnerLicenseVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'license_number_encrypted' => Crypt::encryptString(
                'D123456789012'
            ),
            'license_type' => $licenseType,
            'license_class' => 'D',
            'previous_license_verification_id' => null,
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => 'runner-license-documents/'
                .Str::uuid()
                .'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
    }

    public function test_root_can_create_update_deactivate_and_reactivate_a_license_type(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $this->authenticate(
            $root,
            'root-license-type-lifecycle-token'
        );

        $createResponse = $this->postJson(
            '/api/admin/driving-license-types',
            [
                'code' => '  pdl  ',
                'name' => '  Lesen Percubaan  ',
                'description' => '  Jenis lesen untuk ujian  ',
                'sort_order' => 10,
            ]
        );

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type.code', 'PDL')
            ->assertJsonPath('data.type.name', 'Lesen Percubaan')
            ->assertJsonPath(
                'data.type.description',
                'Jenis lesen untuk ujian'
            )
            ->assertJsonPath('data.type.is_active', true);

        $typeId = $createResponse->json('data.type.id');

        $this->assertNotEmpty($typeId);

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $typeId,
            'code' => 'PDL',
            'name' => 'Lesen Percubaan',
            'description' => 'Jenis lesen untuk ujian',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'action' => 'DRIVING_LICENSE_TYPE_CREATED',
        ]);

        $updateResponse = $this->patchJson(
            '/api/admin/driving-license-types/'.$typeId,
            [
                'name' => 'Lesen Memandu Percubaan',
                'sort_order' => 20,
            ]
        );

        $updateResponse
            ->assertOk()
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
            'actor_account_id' => $root->id,
            'action' => 'DRIVING_LICENSE_TYPE_UPDATED',
        ]);

        $deactivateResponse = $this->deleteJson(
            '/api/admin/driving-license-types/'.$typeId
        );

        $deactivateResponse
            ->assertOk()
            ->assertJsonPath('data.type.is_active', false);

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $typeId,
            'code' => 'PDL',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'action' => 'DRIVING_LICENSE_TYPE_DEACTIVATED',
        ]);

        $reactivateResponse = $this->patchJson(
            '/api/admin/driving-license-types/'.$typeId,
            [
                'is_active' => true,
            ]
        );

        $reactivateResponse
            ->assertOk()
            ->assertJsonPath('data.type.is_active', true);

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $typeId,
            'code' => 'PDL',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'action' => 'DRIVING_LICENSE_TYPE_REACTIVATED',
        ]);
    }

    public function test_super_admin_without_management_permission_cannot_manage_license_types(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->authenticate(
            $admin,
            'license-type-no-permission-token'
        );

        $this->getJson('/api/admin/driving-license-types')
            ->assertForbidden();

        $this->postJson(
            '/api/admin/driving-license-types',
            [
                'code' => 'PDL',
                'name' => 'Lesen Percubaan',
            ]
        )->assertForbidden();

        $this->patchJson(
            '/api/admin/driving-license-types/'.(string) Str::uuid(),
            [
                'name' => 'Nama Baharu',
            ]
        )->assertForbidden();

        $this->deleteJson(
            '/api/admin/driving-license-types/'.(string) Str::uuid()
        )->assertForbidden();

        $this->assertDatabaseCount('driving_license_types', 0);
    }

    public function test_unrelated_permission_does_not_grant_license_type_management_access(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermission(
            $admin,
            'runner_profiles.manage'
        );

        $this->authenticate(
            $admin,
            'license-type-unrelated-permission-token'
        );

        $this->getJson('/api/admin/driving-license-types')
            ->assertForbidden();

        $this->postJson(
            '/api/admin/driving-license-types',
            [
                'code' => 'CDL',
                'name' => 'Lesen Kompeten',
            ]
        )->assertForbidden();

        $this->assertDatabaseCount('driving_license_types', 0);
    }

    public function test_admin_with_management_permission_can_manage_license_types(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermission(
            $admin,
            'driving_license_types.manage'
        );

        $this->authenticate(
            $admin,
            'license-type-management-permission-token'
        );

        $this->getJson('/api/admin/driving-license-types')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'types',
                ],
            ]);

        $response = $this->postJson(
            '/api/admin/driving-license-types',
            [
                'code' => 'CDL',
                'name' => 'Lesen Kompeten',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.type.code', 'CDL');

        $typeId = $response->json('data.type.id');

        $this->patchJson(
            '/api/admin/driving-license-types/'.$typeId,
            [
                'name' => 'Lesen Kompeten Dikemas Kini',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.type.name',
                'Lesen Kompeten Dikemas Kini'
            );

        $this->deleteJson(
            '/api/admin/driving-license-types/'.$typeId
        )
            ->assertOk()
            ->assertJsonPath('data.type.is_active', false);
    }

    public function test_duplicate_license_type_code_is_rejected_without_creating_another_type_or_audit_entry(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $this->authenticate(
            $root,
            'license-type-duplicate-code-token'
        );

        $this->postJson(
            '/api/admin/driving-license-types',
            [
                'code' => 'CDL',
                'name' => 'Lesen Kompeten',
            ]
        )->assertCreated();

        $auditCountBefore = DB::table('audit_logs')
            ->where('action', 'DRIVING_LICENSE_TYPE_CREATED')
            ->count();

        $this->postJson(
            '/api/admin/driving-license-types',
            [
                'code' => ' cdl ',
                'name' => 'Lesen Kompeten Duplikat',
            ]
        )->assertUnprocessable();

        $this->assertSame(
            1,
            DrivingLicenseType::query()
                ->where('code', 'CDL')
                ->count()
        );

        $this->assertSame(
            $auditCountBefore,
            DB::table('audit_logs')
                ->where('action', 'DRIVING_LICENSE_TYPE_CREATED')
                ->count()
        );
    }

    public function test_public_list_returns_only_active_types_in_sort_order(): void
    {
        $account = $this->createAccount('USER');

        $this->createType(
            'TYPE_B',
            'Lesen B',
            true,
            20
        );

        $this->createType(
            'TYPE_A',
            'Lesen A',
            true,
            10
        );

        $this->createType(
            'TYPE_C',
            'Lesen C',
            false,
            30
        );

        $this->authenticate(
            $account,
            'license-type-public-list-token'
        );

        $response = $this->getJson('/api/driving-license-types');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'types',
                ],
            ]);

        $types = $response->json('data.types');

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

    public function test_deactivation_preserves_the_license_type_record(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $type = $this->createType(
            'KEEP',
            'Jenis Kekal'
        );

        $this->authenticate(
            $root,
            'license-type-preserve-record-token'
        );

        $this->deleteJson(
            '/api/admin/driving-license-types/'.$type->id
        )
            ->assertOk()
            ->assertJsonPath('data.type.is_active', false);

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $type->id,
            'code' => 'KEEP',
            'name' => 'Jenis Kekal',
            'is_active' => false,
        ]);

        $this->assertSame(
            1,
            DrivingLicenseType::query()
                ->whereKey($type->id)
                ->count()
        );
    }

    public function test_type_code_cannot_change_when_historical_license_records_reference_it(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $type = $this->createType(
            'GENERAL',
            'Jenis Lesen Am'
        );

        $runner = $this->createAccount('USER');

        $verification = $this->createRunnerLicenseVerification(
            $runner,
            'GENERAL'
        );

        $this->authenticate(
            $root,
            'license-type-historical-reference-token'
        );

        $this->patchJson(
            '/api/admin/driving-license-types/'.$type->id,
            [
                'code' => 'COMMERCIAL',
            ]
        )->assertUnprocessable();

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $type->id,
            'code' => 'GENERAL',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verification->id,
            'account_id' => $runner->id,
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'is_current' => true,
        ]);
    }
}
