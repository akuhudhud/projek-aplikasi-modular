<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Services\AuditLogService;
use App\Services\Identity\IdentityVerificationDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class IdentityVerificationDeletionRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_files_and_database_records_are_restored_when_database_transaction_fails(): void
    {
        Storage::fake('local');

        $actor = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount('USER');

        $verification = IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $applicant->id,
            'real_name' => 'Nama Ujian',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $path = 'identity-documents/'.Str::uuid().'.pdf';
        $contents = '%PDF-rollback-test-content';

        Storage::disk('local')->put($path, $contents);

        $document = IdentityVerificationDocument::create([
            'id' => (string) Str::uuid(),
            'identity_verification_id' => $verification->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
        ]);

        $auditLogService = \Mockery::mock(AuditLogService::class);

        $auditLogService
            ->shouldReceive('record')
            ->once()
            ->andThrow(
                new RuntimeException('Simulated audit failure.')
            );

        $service = new IdentityVerificationDeletionService(
            $auditLogService
        );

        try {
            $service->delete(
                $actor,
                $applicant->id,
                'Ujian rollback transaksi'
            );

            $this->fail(
                'Pemadaman sepatutnya gagal apabila penulisan audit gagal.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Transaksi pangkalan data gagal. Fail dokumen telah dipulihkan.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $applicant->id,
        ]);

        $this->assertDatabaseHas('identity_verification_documents', [
            'id' => $document->id,
            'identity_verification_id' => $verification->id,
            'path' => $path,
        ]);

        Storage::disk('local')->assertExists($path);

        $this->assertSame(
            $contents,
            Storage::disk('local')->get($path)
        );
    }

    private function createAccount(string $role): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Identity Rollback Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }
}
