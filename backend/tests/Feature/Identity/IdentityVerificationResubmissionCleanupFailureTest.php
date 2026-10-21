<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Services\Identity\IdentityVerificationSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class IdentityVerificationResubmissionCleanupFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_file_cleanup_failure_does_not_delete_new_submission_files(): void
    {
        Storage::fake('local');

        $account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Resubmission Cleanup Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $service = app(IdentityVerificationSubmissionService::class);

        $first = $service->submit(
            $account,
            'Nama Ujian',
            '900101121234',
            [UploadedFile::fake()->image('first.jpg')]
        );

        $oldPath = $first->documents->first()->path;

        $first->update([
            'status' => IdentityVerification::STATUS_REJECTED,
            'reviewed_at' => now(),
            'rejection_reason' => 'Sila hantar dokumen yang lebih jelas.',
        ]);

        $realDisk = Storage::disk('local');

        $disk = Mockery::mock($realDisk)->makePartial();

        $disk->shouldReceive('delete')
            ->once()
            ->with($oldPath)
            ->andThrow(
                new RuntimeException(
                    'Simulated old-file cleanup failure.'
                )
            );

        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn($disk);

        $resubmitted = $service->submit(
            $account,
            'Nama Ujian',
            '900101121234',
            [UploadedFile::fake()->image('replacement.jpg')]
        );

        $resubmitted->refresh();
        $resubmitted->load('documents');

        $this->assertSame(
            IdentityVerification::STATUS_PENDING,
            $resubmitted->status
        );

        $this->assertCount(1, $resubmitted->documents);

        $newDocument = $resubmitted->documents->first();

        $this->assertNotSame($oldPath, $newDocument->path);

        $this->assertDatabaseHas('identity_verification_documents', [
            'id' => $newDocument->id,
            'identity_verification_id' => $resubmitted->id,
            'path' => $newDocument->path,
        ]);

        $this->assertTrue(
            $realDisk->exists($newDocument->path),
            'Dokumen baharu yang dirujuk oleh pangkalan data mesti kekal.'
        );

        $this->assertTrue(
            $realDisk->exists($oldPath),
            'Kegagalan membersihkan fail lama tidak boleh membatalkan penghantaran semula.'
        );
    }
}
