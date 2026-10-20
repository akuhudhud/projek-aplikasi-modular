<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\IdentityVerification;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseRenewalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function createAccount(
        string $name,
        string $role = 'USER'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function verifyIdentity(Account $account): void
    {
        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Nama Ujian Lesen',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);
    }

    private function grantReviewPermission(Account $account): void
    {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'runner_license_verifications.review',
        ]);
    }

    private function authenticate(
        Account $account,
        string $token
    ): static {
        Session::create([
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

    private function createLicense(
        Account $account,
        string $licenseType,
        string $licenseClass,
        string $status,
        bool $isCurrent = false
    ): RunnerLicenseVerification {
        $documentPath = 'runner-license-documents/'
            .Str::uuid().'.pdf';

        Storage::disk('local')->put(
            $documentPath,
            '%PDF-test-content'
        );

        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number_encrypted' => Crypt::encryptString(
                'TEST'.Str::random(12)
            ),
            'license_type' => $licenseType,
            'license_class' => $licenseClass,
            'previous_license_verification_id' => null,
            'expires_at' => now()->addYear()->toDateString(),
            'status' => $status,
            'is_current' => $isCurrent,
            'submitted_at' => now()->subMonth(),
            'reviewed_at' => $status === RunnerLicenseVerification::STATUS_PENDING
                ? null
                : now()->subMonth(),
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => $documentPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('%PDF-test-content'),
        ]);
    }

    public function test_pending_car_license_does_not_block_motorcycle_application(): void
    {
        $account = $this->createAccount('Pemohon Dua Jenis Lesen');
        $this->verifyIdentity($account);

        $pendingCar = $this->createLicense(
            $account,
            RunnerLicenseVerification::TYPE_CAR,
            'D',
            RunnerLicenseVerification::STATUS_PENDING
        );

        $this->authenticate(
            $account,
            'different-license-type-token'
        )->post('/api/me/runner-license-verification', [
            'license_number' => 'B212345678901',
            'license_type' => RunnerLicenseVerification::TYPE_MOTORCYCLE,
            'license_class' => 'B2',
            'expires_at' => now()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->image(
                'motorcycle-license.jpg'
            ),
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->assertJsonPath(
                'data.runner_license_verification.license_type',
                RunnerLicenseVerification::TYPE_MOTORCYCLE
            );

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $pendingCar->id,
            'license_type' => RunnerLicenseVerification::TYPE_CAR,
            'status' => RunnerLicenseVerification::STATUS_PENDING,
        ]);

        $this->assertSame(
            2,
            RunnerLicenseVerification::query()
                ->where('account_id', $account->id)
                ->where(
                    'status',
                    RunnerLicenseVerification::STATUS_PENDING
                )
                ->count()
        );
    }

    public function test_approving_car_renewal_replaces_only_matching_license_and_preserves_history(): void
    {
        $admin = $this->createAccount(
            'Admin Semakan Lesen',
            'SUPER_ADMIN'
        );
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount('Pemohon Pembaharuan');
        $this->verifyIdentity($applicant);

        $oldCar = $this->createLicense(
            $applicant,
            RunnerLicenseVerification::TYPE_CAR,
            'D',
            RunnerLicenseVerification::STATUS_VERIFIED,
            true
        );

        $currentMotorcycle = $this->createLicense(
            $applicant,
            RunnerLicenseVerification::TYPE_MOTORCYCLE,
            'B2',
            RunnerLicenseVerification::STATUS_VERIFIED,
            true
        );

        $this->authenticate(
            $applicant,
            'submit-car-renewal-token'
        )->post('/api/me/runner-license-verification', [
            'license_number' => 'D987654321098',
            'license_type' => RunnerLicenseVerification::TYPE_CAR,
            'license_class' => 'D',
            'expires_at' => now()->addYears(2)->toDateString(),
            'document' => UploadedFile::fake()->image(
                'renewed-car-license.jpg'
            ),
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.previous_license_verification_id',
                $oldCar->id
            )
            ->assertJsonPath(
                'data.runner_license_verification.is_current',
                false
            );

        $renewal = RunnerLicenseVerification::query()
            ->where('account_id', $applicant->id)
            ->where(
                'license_type',
                RunnerLicenseVerification::TYPE_CAR
            )
            ->where(
                'status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->firstOrFail();

        $this->authenticate(
            $admin,
            'approve-car-renewal-token'
        )->postJson(
            '/api/admin/runner-license-verifications/'
                .$renewal->id.'/decision',
            ['decision' => 'VERIFIED']
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                RunnerLicenseVerification::STATUS_VERIFIED
            )
            ->assertJsonPath(
                'data.runner_license_verification.is_current',
                true
            );

        $oldCar->refresh();
        $currentMotorcycle->refresh();
        $renewal->refresh();

        // Rekod lama kekal dalam sejarah, tetapi bukan lesen semasa.
        $this->assertSame(
            RunnerLicenseVerification::STATUS_VERIFIED,
            $oldCar->status
        );
        $this->assertFalse($oldCar->is_current);

        // Lesen motosikal tidak terjejas oleh pembaharuan kereta.
        $this->assertSame(
            RunnerLicenseVerification::STATUS_VERIFIED,
            $currentMotorcycle->status
        );
        $this->assertTrue($currentMotorcycle->is_current);

        // Lesen kereta baharu menjadi lesen semasa selepas kelulusan.
        $this->assertSame(
            RunnerLicenseVerification::STATUS_VERIFIED,
            $renewal->status
        );
        $this->assertTrue($renewal->is_current);
        $this->assertSame(
            $oldCar->id,
            $renewal->previous_license_verification_id
        );

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $oldCar->id,
            'is_current' => false,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $currentMotorcycle->id,
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $renewal->id,
            'is_current' => true,
        ]);

        $this->assertSame(
            3,
            RunnerLicenseVerification::query()
                ->where('account_id', $applicant->id)
                ->count()
        );
    }
}
