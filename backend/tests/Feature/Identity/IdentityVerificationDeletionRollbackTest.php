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
use Mockery;
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

        [$verification, $documents] = $this->createVerificationWithDocuments(
            $applicant,
            ['%PDF-rollback-test-content']
        );

        $auditLogService = Mockery::mock(AuditLogService::class);

        $auditLogService
            ->shouldReceive('record')
            ->once()
            ->andThrow(new RuntimeException('Simulated audit failure.'));

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

        foreach ($documents as $document) {
            $this->assertDatabaseHas('identity_verification_documents', [
                'id' => $document['record']->id,
                'identity_verification_id' => $verification->id,
                'path' => $document['path'],
            ]);

            Storage::disk('local')->assertExists($document['path']);

            $this->assertSame(
                $document['contents'],
                Storage::disk('local')->get($document['path'])
            );
        }
    }

    public function test_previous_files_are_restored_when_a_later_file_deletion_fails(): void
    {
        Storage::fake('local');

        $actor = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount('USER');

        [$verification, $documents] = $this->createVerificationWithDocuments(
            $applicant,
            [
                '%PDF-first-document',
                '%PDF-second-document',
            ]
        );

        $realDisk = Storage::disk('local');
        $disk = Mockery::mock($realDisk)->makePartial();

        /*
         * The first deletion succeeds and the second fails.
         * Unmocked methods, including get, exists and put,
         * continue to use the fake filesystem.
         */
        $disk->shouldReceive('delete')
            ->twice()
            ->andReturn(true, false);

        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn($disk);

        $auditLogService = Mockery::mock(AuditLogService::class);

        $auditLogService->shouldNotReceive('record');

        $service = new IdentityVerificationDeletionService(
            $auditLogService
        );

        try {
            $service->delete(
                $actor,
                $applicant->id,
                'Ujian kegagalan fail'
            );

            $this->fail(
                'Pemadaman sepatutnya gagal apabila satu fail gagal dipadam.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Pemadaman fail gagal. Fail yang telah dipadam dipulihkan dan rekod pangkalan data dikekalkan.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $applicant->id,
        ]);

        foreach ($documents as $document) {
            $this->assertDatabaseHas('identity_verification_documents', [
                'id' => $document['record']->id,
                'identity_verification_id' => $verification->id,
                'path' => $document['path'],
            ]);

            $this->assertTrue(
                $realDisk->exists($document['path']),
                'Fail sepatutnya wujud semula: '.$document['path']
            );

            $this->assertSame(
                $document['contents'],
                $realDisk->get($document['path'])
            );
        }
    }

    private function createVerificationWithDocuments(
        Account $applicant,
        array $contentsList
    ): array {
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

        $documents = [];

        foreach ($contentsList as $contents) {
            $path = 'identity-documents/'.Str::uuid().'.pdf';

            Storage::disk('local')->put($path, $contents);

            $record = IdentityVerificationDocument::create([
                'id' => (string) Str::uuid(),
                'identity_verification_id' => $verification->id,
                'disk' => 'local',
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($contents),
            ]);

            $documents[] = [
                'record' => $record,
                'path' => $path,
                'contents' => $contents,
            ];
        }

        return [$verification, $documents];
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
