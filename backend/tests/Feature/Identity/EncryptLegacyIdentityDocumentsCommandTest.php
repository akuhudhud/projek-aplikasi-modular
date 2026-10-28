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
use Mockery;
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

        $corruptContents = self::PREFIX
            .'not-a-valid-laravel-encryption-payload';

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

    public function test_failed_encryption_write_restores_original_contents(): void
    {
        Storage::fake('local');

        $originalContents = '%PDF-original-content';
        $document = $this->createLegacyDocument($originalContents);

        $realDisk = Storage::disk('local');
        $writeCalls = 0;

        $disk = Mockery::mock($realDisk)->makePartial();

        $disk->shouldReceive('put')
            ->twice()
            ->andReturnUsing(
                function (
                    string $path,
                    string $contents
                ) use (
                    $realDisk,
                    &$writeCalls
                ): bool {
                    $writeCalls++;

                    if ($writeCalls === 1) {
                        return $realDisk->put(
                            $path,
                            'CORRUPTED-ENCRYPTED-CONTENT'
                        );
                    }

                    return $realDisk->put($path, $contents);
                }
            );

        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn($disk);

        $exitCode = Artisan::call(
            'identity:encrypt-legacy-documents',
            ['--force' => true]
        );

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertSame(2, $writeCalls);

        $this->assertSame(
            $originalContents,
            $realDisk->get($document->path)
        );

        $this->assertDatabaseHas('identity_verification_documents', [
            'id' => $document->id,
            'path' => $document->path,
        ]);
    }

    public function test_changed_app_key_does_not_overwrite_encrypted_document(): void
    {
        Storage::fake('local');

        $originalContents = '%PDF-encrypted-with-original-key';
        $document = $this->createLegacyDocument($originalContents);

        $encryptedContents = self::PREFIX
            .Crypt::encryptString($originalContents);

        $realDisk = Storage::disk('local');

        $realDisk->put(
            $document->path,
            $encryptedContents
        );

        $originalKey = config('app.key');

        $this->assertIsString($originalKey);

        try {
            config([
                'app.key' => 'base64:'.base64_encode(
                    random_bytes(32)
                ),
            ]);

            // Clear both cached facade and container instances.
            Crypt::clearResolvedInstance('encrypter');
            $this->app->forgetInstance('encrypter');

            $exitCode = Artisan::call(
                'identity:encrypt-legacy-documents',
                ['--force' => true]
            );

            $this->assertSame(Command::FAILURE, $exitCode);

            // A key mismatch must never cause an encrypted file to be replaced.
            $this->assertSame(
                $encryptedContents,
                $realDisk->get($document->path)
            );
        } finally {
            config(['app.key' => $originalKey]);

            // Restore the original encrypter for subsequent tests.
            Crypt::clearResolvedInstance('encrypter');
            $this->app->forgetInstance('encrypter');
        }
    }
}
