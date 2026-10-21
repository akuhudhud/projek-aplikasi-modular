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

class IdentityVerificationRestoreIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_reports_failure_when_written_contents_do_not_match_original(): void
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
        $originalContents = '%PDF-original-content';
        $incorrectContents = '%PDF-incorrect-content';

        Storage::disk('local')->put($path, $originalContents);

        $document = IdentityVerificationDocument::create([
            'id' => (string) Str::uuid(),
            'identity_verification_id' => $verification->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($originalContents),
        ]);

        $realDisk = Storage::disk('local');
        $disk = Mockery::mock($realDisk)->makePartial();

        $disk->shouldReceive('put')
            ->once()
            ->with($path, $originalContents)
            ->andReturnUsing(
                function () use (
                    $realDisk,
                    $path,
                    $incorrectContents
                ): bool {
                    $realDisk->put($path, $incorrectContents);

                    return true;
                }
            );

        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn($disk);

        $auditLogService = Mockery::mock(AuditLogService::class);
        $auditLogService
            ->shouldReceive('record')
            ->once()
            ->andThrow(
                new RuntimeException(
                    'Simulated audit recording failure.'
                )
            );

        $service = new IdentityVerificationDeletionService(
            $auditLogService
        );

        try {
            $service->delete(
                $actor,
                $applicant->id,
                'Ujian integriti pemulihan'
            );

            $this->fail(
                'Pemulihan dengan kandungan salah mesti dilaporkan sebagai gagal.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Pemulihan semua fail tidak dapat disahkan selepas pemadaman gagal. Rekod pangkalan data dikekalkan; pemeriksaan operasi diperlukan.',
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

        $this->assertTrue($realDisk->exists($path));

        $this->assertSame(
            $incorrectContents,
            $realDisk->get($path)
        );

        $this->assertNotSame(
            $originalContents,
            $realDisk->get($path)
        );
    }

    private function createAccount(string $role): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Identity Restore Integrity Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }
}
