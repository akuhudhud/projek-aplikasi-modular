<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EncryptLegacyIdentityDocumentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PREFIX = 'SRIDENC1:';

    private function createLegacyDocument(
        string $contents
    ): IdentityVerificationDocument {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Legacy Encryption Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $verification = IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Nama Ujian',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $path = 'identity-documents/'.Str::uuid().'.pdf';

        Storage::disk('local')->put($path, $contents);

        return IdentityVerificationDocument::create([
            'id' => (string) Str::uuid(),
            'identity_verification_id' => $verification->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
        ]);
    }

    public function test_command_requires_force_before_modifying_files(): void
    {
        Storage::fake('local');

        $contents = '%PDF-legacy-document';
        $document = $this->createLegacyDocument($contents);

        $exitCode = Artisan::call(
            'identity:encrypt-legacy-documents'
        );

        $this->assertSame(Command::FAILURE, $exitCode);

        $this->assertSame(
            $contents,
            Storage::disk('local')->get($document->path)
        );
    }

    public function test_dry_run_does_not_change_legacy_documents(): void
    {
        Storage::fake('local');

        $contents = '%PDF-legacy-document';
        $document = $this->createLegacyDocument($contents);

        $exitCode = Artisan::call(
            'identity:encrypt-legacy-documents',
            ['--dry-run' => true]
        );

        $this->assertSame(Command::SUCCESS, $exitCode);

        $this->assertSame(
            $contents,
            Storage::disk('local')->get($document->path)
        );
    }

    public function test_command_encrypts_legacy_document_and_verifies_contents(): void
    {
        Storage::fake('local');

        $contents = '%PDF-legacy-document-to-encrypt';
        $document = $this->createLegacyDocument($contents);

        $exitCode = Artisan::call(
            'identity:encrypt-legacy-documents',
            ['--force' => true]
        );

        $this->assertSame(Command::SUCCESS, $exitCode);

        $stored = Storage::disk('local')->get($document->path);

        $this->assertIsString($stored);
        $this->assertStringStartsWith(self::PREFIX, $stored);
        $this->assertStringNotContainsString($contents, $stored);

        $decrypted = Crypt::decryptString(
            substr($stored, strlen(self::PREFIX))
        );

        $this->assertSame($contents, $decrypted);

        // File path and database metadata must remain unchanged.
        $this->assertDatabaseHas('identity_verification_documents', [
            'id' => $document->id,
            'path' => $document->path,
            'disk' => 'local',
            'size_bytes' => strlen($contents),
        ]);
    }

    public function test_command_is_idempotent_for_already_encrypted_documents(): void
    {
        Storage::fake('local');

        $contents = '%PDF-already-encrypted';
        $document = $this->createLegacyDocument($contents);

        $encrypted = self::PREFIX.Crypt::encryptString($contents);

        Storage::disk('local')->put(
            $document->path,
            $encrypted
        );

        $exitCode = Artisan::call(
            'identity:encrypt-legacy-documents',
            ['--force' => true]
        );

        $this->assertSame(Command::SUCCESS, $exitCode);

        $this->assertSame(
            $encrypted,
            Storage::disk('local')->get($document->path)
        );
    }

    public function test_command_stops_without_overwriting_corrupt_encrypted_document(): void
    {
        Storage::fake('local');

        $document = $this->createLegacyDocument(
            '%PDF-original-document'
        );

        $corruptContents = self::PREFIX.'not-a-valid-laravel-encryption-payload';

        Storage::disk('local')->put(
            $document->path,
            $corruptContents
        );

        $exitCode = Artisan::call(
            'identity:encrypt-legacy-documents',
            ['--force' => true]
        );

        $this->assertSame(Command::FAILURE, $exitCode);

        $this->assertSame(
            $corruptContents,
            Storage::disk('local')->get($document->path)
        );
    }
}
