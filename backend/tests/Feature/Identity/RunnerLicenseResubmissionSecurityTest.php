<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseResubmissionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function createAccount(): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ujian Hantar Semula Lesen',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    private function verifyIdentity(Account $account): void
    {
        IdentityVerification::create([
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
    }

    private function createLicense(
        Account $account,
        string $status
    ): RunnerLicenseVerification {
        $path = 'runner-license-documents/'
            .Str::uuid().'.pdf';

        Storage::disk('local')->put($path, '%PDF-original');

        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number_encrypted' => Crypt::encryptString(
                'D123456789012'
            ),
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => $status,
            'is_current' => $status
                === RunnerLicenseVerification::STATUS_VERIFIED,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => $status === RunnerLicenseVerification::STATUS_PENDING
                ? null
                : now(),
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('%PDF-original'),
        ]);
    }

    private function asAccount(
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

    public function test_pending_license_cannot_be_resubmitted(): void
    {
        $account = $this->createAccount();
        $this->verifyIdentity($account);

        $original = $this->createLicense(
            $account,
            RunnerLicenseVerification::STATUS_PENDING
        );

        $originalPath = $original->document_path;
        $originalLicenseNumber = $original->decryptLicenseNumber();
        $originalSubmittedAt = $original->submitted_at;

        $this->asAccount($account, 'license-pending-resubmit-token')
            ->post('/api/me/runner-license-verification', [
                'license_number' => 'D987654321098',
                'license_class' => 'D',
                'expires_at' => now()->addYears(2)->toDateString(),
                'document' => UploadedFile::fake()->image(
                    'replacement-license.jpg'
                ),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $original->refresh();

        $this->assertSame(
            RunnerLicenseVerification::STATUS_PENDING,
            $original->status
        );

        $this->assertSame(
            $originalPath,
            $original->document_path
        );

        $this->assertSame(
            $originalLicenseNumber,
            $original->decryptLicenseNumber()
        );

        $this->assertTrue(
            $original->submitted_at->equalTo($originalSubmittedAt)
        );

        Storage::disk('local')->assertExists($originalPath);

        $this->assertSame(
            1,
            RunnerLicenseVerification::query()
                ->where('account_id', $account->id)
                ->count()
        );
    }

    public function test_verified_current_license_is_preserved_when_renewal_is_submitted(): void
    {
        $account = $this->createAccount();
        $this->verifyIdentity($account);

        $original = $this->createLicense(
            $account,
            RunnerLicenseVerification::STATUS_VERIFIED
        );

        $originalPath = $original->document_path;
        $originalLicenseNumber = $original->decryptLicenseNumber();

        $this->asAccount($account, 'license-verified-renewal-token')
            ->post('/api/me/runner-license-verification', [
                'license_number' => 'D987654321098',
                'license_class' => 'D',
                'expires_at' => now()->addYears(2)->toDateString(),
                'document' => UploadedFile::fake()->image(
                    'renewal-license.jpg'
                ),
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_license_verification.status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->assertJsonPath(
                'data.runner_license_verification.is_current',
                false
            );

        $original->refresh();

        $this->assertSame(
            RunnerLicenseVerification::STATUS_VERIFIED,
            $original->status
        );

        $this->assertTrue($original->is_current);

        $this->assertSame(
            $originalPath,
            $original->document_path
        );

        $this->assertSame(
            $originalLicenseNumber,
            $original->decryptLicenseNumber()
        );

        Storage::disk('local')->assertExists($originalPath);

        $this->assertSame(
            2,
            RunnerLicenseVerification::query()
                ->where('account_id', $account->id)
                ->count()
        );

        $renewal = RunnerLicenseVerification::query()
            ->where('account_id', $account->id)
            ->where('id', '!=', $original->id)
            ->firstOrFail();

        $this->assertSame(
            RunnerLicenseVerification::STATUS_PENDING,
            $renewal->status
        );

        $this->assertFalse($renewal->is_current);

        $this->assertSame(
            'D987654321098',
            $renewal->decryptLicenseNumber()
        );

        Storage::disk('local')->assertExists(
            $renewal->document_path
        );
    }
}
