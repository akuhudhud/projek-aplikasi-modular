<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\DrivingLicenseCatalog;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role = 'USER'
    ): Account {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ujian Katalog Lesen',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function asAccount(
        Account $account,
        string $token
    ): static {
        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function grantCatalogPermission(Account $account): void
    {
        AccountPermission::query()->create([
            'account_id' => $account->id,
            'permission_key' => 'driving_license_catalog.manage',
        ]);
    }

    private function createCatalogEntry(
        string $code,
        bool $active = true
    ): DrivingLicenseCatalog {
        return DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => 'Lesen '.$code,
            'description' => null,
            'is_active' => $active,
            'sort_order' => 0,
        ]);
    }

    private function createLicenseHistory(
        Account $account,
        string $licenseClass
    ): RunnerLicenseVerification {
        $path = 'runner-license-documents/'
            .Str::uuid().'.pdf';

        Storage::disk('local')->put($path, '%PDF-test');

        return RunnerLicenseVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number_encrypted' => Crypt::encryptString(
                'TEST123456789'
            ),
            'license_type' => RunnerLicenseVerification::TYPE_GENERAL,
            'license_class' => $licenseClass,
            'previous_license_verification_id' => null,
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('%PDF-test'),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_root_can_create_catalog_entry_and_code_is_normalized(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $this->asAccount($root, 'root-catalog-create-token')
            ->postJson('/api/admin/driving-license-catalog', [
                'code' => '  b2 ',
                'name' => 'Lesen B2',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.license.code', 'B2');

        $this->assertDatabaseHas('driving_license_catalog', [
            'code' => 'B2',
            'name' => 'Lesen B2',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_without_catalog_permission_is_forbidden(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->asAccount($admin, 'catalog-denied-token')
            ->getJson('/api/admin/driving-license-catalog')
            ->assertForbidden();

        $this->asAccount($admin, 'catalog-create-denied-token')
            ->postJson('/api/admin/driving-license-catalog', [
                'code' => 'D',
                'name' => 'Lesen D',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_with_catalog_permission_can_manage_entries(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantCatalogPermission($admin);

        $this->asAccount($admin, 'catalog-manage-token')
            ->postJson('/api/admin/driving-license-catalog', [
                'code' => 'D',
                'name' => 'Lesen D',
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.license.code', 'D');

        $entry = DrivingLicenseCatalog::query()
            ->where('code', 'D')
            ->firstOrFail();

        $this->asAccount($admin, 'catalog-update-token')
            ->patchJson(
                '/api/admin/driving-license-catalog/'.$entry->id,
                ['name' => 'Lesen D Dikemas Kini']
            )
            ->assertOk()
            ->assertJsonPath(
                'data.license.name',
                'Lesen D Dikemas Kini'
            );

        $this->asAccount($admin, 'catalog-deactivate-token')
            ->deleteJson(
                '/api/admin/driving-license-catalog/'.$entry->id
            )
            ->assertOk()
            ->assertJsonPath('data.license.is_active', false);
    }

    public function test_public_catalog_returns_only_active_entries(): void
    {
        $account = $this->createAccount();

        $this->createCatalogEntry('D', true);
        $this->createCatalogEntry('B2', false);

        $this->asAccount($account, 'catalog-public-token')
            ->getJson('/api/driving-license-catalog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['code' => 'D'])
            ->assertJsonMissing(['code' => 'B2']);
    }

    public function test_duplicate_code_is_rejected_after_normalization(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $this->createCatalogEntry('B2');

        $this->asAccount($root, 'catalog-duplicate-token')
            ->postJson('/api/admin/driving-license-catalog', [
                'code' => ' b2 ',
                'name' => 'Duplikasi B2',
            ])
            ->assertUnprocessable();

        $this->assertSame(
            1,
            DrivingLicenseCatalog::query()
                ->where('code', 'B2')
                ->count()
        );
    }

    public function test_code_cannot_be_changed_after_it_is_used_in_license_history(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount();
        $entry = $this->createCatalogEntry('D');

        $history = $this->createLicenseHistory($applicant, 'D');

        $this->asAccount($root, 'catalog-history-code-token')
            ->patchJson(
                '/api/admin/driving-license-catalog/'.$entry->id,
                ['code' => 'DA']
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $entry->id,
            'code' => 'D',
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $history->id,
            'license_class' => 'D',
        ]);
    }

    public function test_deactivation_preserves_catalog_and_license_history(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount();
        $entry = $this->createCatalogEntry('D');

        $history = $this->createLicenseHistory($applicant, 'D');

        $this->asAccount($root, 'catalog-history-deactivate-token')
            ->deleteJson(
                '/api/admin/driving-license-catalog/'.$entry->id
            )
            ->assertOk();

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $entry->id,
            'code' => 'D',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $history->id,
            'license_class' => 'D',
        ]);
    }
}
