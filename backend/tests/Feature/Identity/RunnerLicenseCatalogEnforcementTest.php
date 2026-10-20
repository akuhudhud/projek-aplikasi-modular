<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\IdentityVerification;
use App\Models\RunnerLicenseVerification;
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

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->createCatalogEntry('D', true);
        $this->createCatalogEntry('B2', false);
    }

    private function createCatalogEntry(
        string $code,
        bool $active
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

        $this->withHeader('Authorization', 'Bearer '.$token);

        return $account;
    }

    private function submitLicense(
        string $licenseClass
    ): \Illuminate\Testing\TestResponse {
        return $this->post(
            '/api/me/runner-license-verification',
            [
                'license_number' => 'D123456789012',
                'license_class' => $licenseClass,
                'expires_at' => now()->addYear()->toDateString(),
                'document' => UploadedFile::fake()->image(
                    'license.jpg'
                ),
            ]
        );
    }

    public function test_unknown_license_class_is_rejected(): void
    {
        $account = $this->createVerifiedAccount();

        $this->submitLicense('Z99')
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

        $this->submitLicense('B2')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing(
            'runner_license_verifications',
            ['account_id' => $account->id]
        );
    }
}
