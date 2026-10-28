<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityVerificationDocumentDownloadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const ENCRYPTED_FILE_PREFIX = 'SRIDENC1:';

    private const DOCUMENT_CONTENT = '%PDF-test-content';

    private function createAccount(
        string $role = 'USER',
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Identity Document Security Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function grantReviewPermission(Account $account): void
    {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'identity_verifications.review',
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

    private function createVerificationWithDocument(
        Account $account,
        bool $encrypted = true
    ): IdentityVerificationDocument {
        $verification = IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Nama Ujian Sulit',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $path = 'identity-documents/'.Str::uuid().'.pdf';

        $storedContents = $encrypted
            ? self::ENCRYPTED_FILE_PREFIX
                .Crypt::encryptString(self::DOCUMENT_CONTENT)
            : self::DOCUMENT_CONTENT;

        Storage::disk('local')->put(
            $path,
            $storedContents
        );

        return IdentityVerificationDocument::create([
            'id' => (string) Str::uuid(),
            'identity_verification_id' => $verification->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen(self::DOCUMENT_CONTENT),
        ]);
    }

    public function test_admin_without_review_permission_cannot_download_identity_document(): void
    {
        Storage::fake('local');

        $admin = $this->createAccount('SUPER_ADMIN');
        $applicant = $this->createAccount();

        $document = $this->createVerificationWithDocument($applicant);

        $this->asAccount(
            $admin,
            'identity-download-no-permission-token'
        )->get(
            '/api/admin/identity-verifications/'.$applicant->id
                .'/documents/'.$document->id
        )->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_reviewer_cannot_download_document_using_another_accounts_id(): void
    {
        Storage::fake('local');

        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $owner = $this->createAccount();
        $otherAccount = $this->createAccount();

        $document = $this->createVerificationWithDocument($owner);

        $this->asAccount(
            $admin,
            'identity-download-cross-account-token'
        )->get(
            '/api/admin/identity-verifications/'.$otherAccount->id
                .'/documents/'.$document->id
        )->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Identity document not found.'
            );
    }

    public function test_reviewer_cannot_download_document_when_storage_file_is_missing(): void
    {
        Storage::fake('local');

        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();

        $document = $this->createVerificationWithDocument($applicant);

        Storage::disk('local')->delete($document->path);

        $this->asAccount(
            $admin,
            'identity-download-missing-file-token'
        )->get(
            '/api/admin/identity-verifications/'.$applicant->id
                .'/documents/'.$document->id
        )->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Identity document not found.'
            );
    }

    public function test_encrypted_document_is_decrypted_only_for_authorized_download(): void
    {
        Storage::fake('local');

        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();

        $document = $this->createVerificationWithDocument($applicant);

        $storedContents = Storage::disk('local')->get($document->path);

        $this->assertIsString($storedContents);
        $this->assertStringStartsWith(
            self::ENCRYPTED_FILE_PREFIX,
            $storedContents
        );
        $this->assertStringNotContainsString(
            self::DOCUMENT_CONTENT,
            $storedContents
        );

        $response = $this->asAccount(
            $admin,
            'identity-download-encrypted-document-token'
        )->get(
            '/api/admin/identity-verifications/'.$applicant->id
                .'/documents/'.$document->id
        );

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader(
                'Content-Length',
                (string) strlen(self::DOCUMENT_CONTENT)
            );

        $this->assertSame(
            self::DOCUMENT_CONTENT,
            $response->getContent()
        );
    }

    public function test_legacy_plaintext_document_remains_downloadable(): void
    {
        Storage::fake('local');

        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();

        $document = $this->createVerificationWithDocument(
            $applicant,
            false
        );

        $response = $this->asAccount(
            $admin,
            'identity-download-legacy-document-token'
        )->get(
            '/api/admin/identity-verifications/'.$applicant->id
                .'/documents/'.$document->id
        );

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame(
            self::DOCUMENT_CONTENT,
            $response->getContent()
        );
    }
}
