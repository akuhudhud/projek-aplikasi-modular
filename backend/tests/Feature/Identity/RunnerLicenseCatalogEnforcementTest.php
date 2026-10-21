<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use App\Models\IdentityVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseCatalogEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private DrivingLicenseType $activeType;

    private DrivingLicenseType $inactiveType;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->activeType = $this->createLicenseType(
            'GENERAL',
            true
        );

        $this->inactiveType = $this->createLicenseType(
            'INACTIVE_TYPE',
            false
        );

        $this->createCatalogEntry(
            $this->activeType,
            'D',
            true
        );

        $this->createCatalogEntry(
            $this->activeType,
            'B2',
            false
        );

        $this->createCatalogEntry(
            $this->inactiveType,
            'D',
            true
        );
    }

    private function createLicenseType(
        string $code,
        bool $active
    ): DrivingLicenseType {
        return DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => 'Jenis Lesen '.$code,
            'description' => null,
            'is_active' => $active,
            'sort_order' => 0,
        ]);
    }

    private function createCatalogEntry(
        DrivingLicenseType $type,
        string $code,
        bool $active
    ): DrivingLicenseCatalog {
        return DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
            'driving_license_type_id' => $type->id,
            'code' => $code,
            'name' => 'Lesen '.$code,
            'description' => null,
            'is_active' => $active,
            'sort_order' => 0,
        ]);
    }

    private function createVerifiedAccount(): Account
    {
        $account = Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ujian Katalog Lesen',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        IdentityVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Nama Ujian',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $token = 'license-catalog-enforcement-token';

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

        return $account;
    }

    private function submitLicense(
        string $licenseType,
        string $licenseClass,
        ?string $expiresAt = null
    ): \Illuminate\Testing\TestResponse {
        return $this->post(
            '/api/me/runner-application',
            [
                'license_type' => $licenseType,
                'license_number' => 'D123456789012',
                'license_class' => $licenseClass,
                'expires_at' => $expiresAt
                    ?? now()->addYear()->toDateString(),
                'document' => UploadedFile::fake()->image(
                    'license.jpg'
                ),
            ],
            [
                'Accept' => 'application/json',
            ]
        );
    }

    public function test_unknown_license_class_is_rejected(): void
    {
        $account = $this->createVerifiedAccount();

        $this->submitLicense('GENERAL', 'Z99')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing(
            'runner_license_verifications',
            ['account_id' => $account->id]
        );
    }

    public function test_inactive_license_class_is_rejected(): void
    {
        $account = $this->createVerifiedAccount();

        $this->submitLicense('GENERAL', 'B2')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing(
            'runner_license_verifications',
            ['account_id' => $account->id]
        );
    }

    public function test_inactive_license_type_is_rejected(): void
    {
        $account = $this->createVerifiedAccount();

        $this->submitLicense('INACTIVE_TYPE', 'D')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing(
            'runner_license_verifications',
            ['account_id' => $account->id]
        );
    }

    public function test_valid_license_class_is_scoped_to_its_parent_type(): void
    {
        $account = $this->createVerifiedAccount();

        $this->submitLicense('GENERAL', 'D')
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                'PENDING'
            );

        $this->assertDatabaseHas(
            'runner_license_verifications',
            [
                'account_id' => $account->id,
                'license_type' => 'GENERAL',
                'license_class' => 'D',
                'status' => 'PENDING',
            ]
        );
    }

    public function test_expired_license_can_be_submitted_for_admin_review(): void
    {
        $account = $this->createVerifiedAccount();
        $expiredDate = now()->subDay()->toDateString();

        $this->submitLicense(
            'GENERAL',
            'D',
            $expiredDate
        )
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                'PENDING'
            )
            ->assertJsonPath(
                'data.runner_license_verification.expires_at',
                $expiredDate
            );

        $this->assertDatabaseHas(
            'runner_license_verifications',
            [
                'account_id' => $account->id,
                'license_type' => 'GENERAL',
                'license_class' => 'D',
                'expires_at' => $expiredDate,
                'status' => 'PENDING',
                'is_current' => false,
            ]
        );
    }
}
