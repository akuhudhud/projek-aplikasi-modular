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

class IdentityVerificationDeletionExceptionRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_is_restored_when_disk_throws_after_deleting_it(): void
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
        $contents = '%PDF-delete-then-throw-regression';

        Storage::disk('local')->put($path, $contents);

        $document = IdentityVerificationDocument::create([
            'id' => (string) Str::uuid(),
            'identity_verification_id' => $verification->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
        ]);

        $realDisk = Storage::disk('local');
        $disk = Mockery::mock($realDisk)->makePartial();

        $disk->shouldReceive('delete')
            ->once()
            ->andReturnUsing(
                function (string $filePath) use ($realDisk): bool {
                    $realDisk->delete($filePath);

                    throw new RuntimeException(
                        'Simulated storage failure after deletion.'
                    );
                }
            );

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
                'Ujian pemulihan fail'
            );

            $this->fail(
                'Pemadaman sepatutnya gagal apabila storan melemparkan ralat.'
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

        $this->assertDatabaseHas('identity_verification_documents', [
            'id' => $document->id,
            'identity_verification_id' => $verification->id,
            'path' => $path,
        ]);

        $this->assertTrue(
            $realDisk->exists($path),
            'Fail yang dipadam sebelum ralat mesti dipulihkan.'
        );

        $this->assertSame(
            $contents,
            $realDisk->get($path),
            'Kandungan fail yang dipulihkan mesti sama dengan kandungan asal.'
        );
    }

    private function createAccount(string $role): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Identity Exception Rollback Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }
}
