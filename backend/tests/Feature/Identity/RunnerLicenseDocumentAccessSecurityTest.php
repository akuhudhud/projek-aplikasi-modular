<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseDocumentAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function createAccount(string $role = 'USER'): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ujian Akses Dokumen',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function createPendingLicense(
        Account $applicant
    ): RunnerLicenseVerification {
        $path = 'runner-license-documents/'
            .Str::uuid().'.pdf';

        Storage::disk('local')->put($path, '%PDF-test-content');

        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $applicant->id,
            'license_number_encrypted' => Crypt::encryptString(
                'D123456789012'
            ),
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('%PDF-test-content'),
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

    public function test_unauthenticated_user_cannot_download_license_document(): void
    {
        $applicant = $this->createAccount();
        $this->createPendingLicense($applicant);

        $this->get(
            '/api/admin/runner-license-verifications/'
                .$applicant->id.'/document'
        )->assertUnauthorized();
    }

    public function test_admin_without_review_permission_cannot_download_license_document(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $applicant = $this->createAccount();

        $this->createPendingLicense($applicant);

        $this->authenticate($admin, 'license-document-denied-token')
            ->get(
                '/api/admin/runner-license-verifications/'
                    .$applicant->id.'/document'
            )
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
