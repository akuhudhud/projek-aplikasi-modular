<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseCatalogHistoricalIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateRoot(): void
    {
        $root = Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Root Owner',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $token = 'license-catalog-history-token';

        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $root->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function createType(): DrivingLicenseType
    {
        return DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'GENERAL',
            'name' => 'Jenis Lesen Am',
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function createClass(
        DrivingLicenseType $type
    ): DrivingLicenseCatalog {
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

    private function createRunner(): Account
    {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner History Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    private function createVerification(
        Account $runner
    ): RunnerLicenseVerification {
        return RunnerLicenseVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'license_number_encrypted' => Crypt::encryptString(
                'D123456789012'
            ),
            'license_type' => 'GENERAL',
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
            'document_path' => 'runner-license-documents/history.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
    }

    public function test_class_code_cannot_change_if_historical_record_uses_it(): void
    {
        $this->authenticateRoot();

        $type = $this->createType();
        $class = $this->createClass($type);
        $runner = $this->createRunner();
        $verification = $this->createVerification($runner);

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$class->id,
            ['code' => 'DA']
        )->assertUnprocessable();

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $class->id,
            'code' => 'D',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verification->id,
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
        ]);
    }

    public function test_type_code_cannot_change_if_historical_record_uses_it(): void
    {
        $this->authenticateRoot();

        $type = $this->createType();
        $runner = $this->createRunner();
        $verification = $this->createVerification($runner);

        $this->patchJson(
            '/api/admin/driving-license-types/'.$type->id,
            ['code' => 'COMMERCIAL']
        )->assertUnprocessable();

        $this->assertDatabaseHas('driving_license_types', [
            'id' => $type->id,
            'code' => 'GENERAL',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verification->id,
            'license_type' => 'GENERAL',
            'license_class' => 'D',
        ]);
    }

    public function test_deactivating_class_preserves_historical_records(): void
    {
        $this->authenticateRoot();

        $type = $this->createType();
        $class = $this->createClass($type);
        $runner = $this->createRunner();
        $verification = $this->createVerification($runner);

        $this->deleteJson(
            '/api/admin/driving-license-catalog/'.$class->id
        )->assertOk()
            ->assertJsonPath('data.license.is_active', false);

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $class->id,
            'driving_license_type_id' => $type->id,
            'code' => 'D',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verification->id,
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
        ]);
    }

    public function test_class_cannot_move_to_another_type_if_historical_record_uses_it(): void
    {
        $this->authenticateRoot();

        $originalType = $this->createType();

        $targetType = DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'COMMERCIAL',
            'name' => 'Jenis Lesen Komersial',
            'description' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $class = $this->createClass($originalType);
        $runner = $this->createRunner();
        $verification = $this->createVerification($runner);

        $this->patchJson(
            '/api/admin/driving-license-catalog/'.$class->id,
            [
                'driving_license_type_id' => $targetType->id,
            ]
        )->assertUnprocessable();

        $this->assertDatabaseHas('driving_license_catalog', [
            'id' => $class->id,
            'driving_license_type_id' => $originalType->id,
            'code' => 'D',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $verification->id,
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
        ]);
    }
}
