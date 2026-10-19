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

    public function test_pending_or_verified_license_cannot_be_resubmitted(): void
    {
        foreach ([
            RunnerLicenseVerification::STATUS_PENDING,
            RunnerLicenseVerification::STATUS_VERIFIED,
        ] as $status) {
            $account = $this->createAccount();
            $this->verifyIdentity($account);

            $original = $this->createLicense($account, $status);
            $originalPath = $original->document_path;
            $originalSubmittedAt = $original->submitted_at;

            $token = 'license-resubmit-'
                .strtolower($status).'-token';

            $this->asAccount($account, $token)
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

            $this->assertSame($status, $original->status);
            $this->assertSame(
                $originalPath,
                $original->document_path
            );
            $this->assertSame(
                'D123456789012',
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
    }
}
