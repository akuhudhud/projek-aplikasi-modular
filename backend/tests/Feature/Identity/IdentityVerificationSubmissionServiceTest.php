<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Services\Identity\IdentityVerificationSubmissionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class IdentityVerificationSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(): Account
    {
        return Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Display Name',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_submission_stores_mykad_as_plaintext_and_document_privately(): void
    {
        Storage::fake('local');

        $account = $this->createAccount();

        $mykadNumber = '900101121234';
        $file = UploadedFile::fake()->image('mykad-front.jpg');

        $verification = app(
            IdentityVerificationSubmissionService::class
        )->submit(
            $account,
            'Real Name',
            $mykadNumber,
            [$file]
        );

        $this->assertSame(
            IdentityVerification::STATUS_PENDING,
            $verification->status
        );

        $this->assertSame('Real Name', $verification->real_name);

        $this->assertSame(
            $mykadNumber,
            $verification->mykad_number
        );

        $this->assertCount(1, $verification->documents);

        $document = $verification->documents->first();

        $this->assertSame('local', $document->disk);
        $this->assertSame('image/jpeg', $document->mime_type);
        $this->assertLessThanOrEqual(
            10 * 1024 * 1024,
            $document->size_bytes
        );

        $this->assertStringStartsWith(
            'identity-documents/',
            $document->path
        );

        $this->assertStringNotContainsString(
            'mykad-front',
            $document->path
        );

        Storage::disk('local')->assertExists($document->path);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $account->id,
            'mykad_number' => $mykadNumber,
            'status' => IdentityVerification::STATUS_PENDING,
        ]);
    }

    public function test_submission_accepts_a_pdf_with_a_pdf_signature(): void
    {
        Storage::fake('local');

        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'identity-pdf-'
        );

        $this->assertIsString($temporaryPath);

        file_put_contents(
            $temporaryPath,
            "%PDF-1.4\n% Identity verification test\n%%EOF\n"
        );

        try {
            $file = new UploadedFile(
                $temporaryPath,
                'identity-document.pdf',
                'application/pdf',
                null,
                true
            );

            $verification = app(
                IdentityVerificationSubmissionService::class
            )->submit(
                $this->createAccount(),
                'Real Name',
                '900101121234',
                [$file]
            );

            $this->assertSame(
                'application/pdf',
                $verification->documents->first()->mime_type
            );

            Storage::disk('local')->assertExists(
                $verification->documents->first()->path
            );
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function test_submission_rejects_an_unsupported_file_type(): void
    {
        Storage::fake('local');

        $this->expectException(InvalidArgumentException::class);

        app(IdentityVerificationSubmissionService::class)->submit(
            $this->createAccount(),
            'Real Name',
            '900101121234',
            [
                UploadedFile::fake()->create(
                    'identity-document.txt',
                    10,
                    'text/plain'
                ),
            ]
        );
    }

    public function test_submission_rejects_a_file_over_10_mb(): void
    {
        Storage::fake('local');

        $this->expectException(InvalidArgumentException::class);

        app(IdentityVerificationSubmissionService::class)->submit(
            $this->createAccount(),
            'Real Name',
            '900101121234',
            [
                UploadedFile::fake()->create(
                    'oversized.pdf',
                    10241,
                    'application/pdf'
                ),
            ]
        );
    }

    public function test_pending_application_cannot_be_submitted_again(): void
    {
        Storage::fake('local');

        $account = $this->createAccount();

        $service = app(
            IdentityVerificationSubmissionService::class
        );

        $verification = $service->submit(
            $account,
            'Real Name',
            '900101121234',
            [UploadedFile::fake()->image('first.jpg')]
        );

        $this->expectException(DomainException::class);

        $service->submit(
            $account,
            'Another Name',
            '900101121235',
            [UploadedFile::fake()->image('second.jpg')]
        );

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'status' => IdentityVerification::STATUS_PENDING,
        ]);
    }

    public function test_rejected_application_can_be_resubmitted_with_replacement_documents(): void
    {
        Storage::fake('local');

        $account = $this->createAccount();

        $service = app(
            IdentityVerificationSubmissionService::class
        );

        $first = $service->submit(
            $account,
            'Real Name',
            '900101121234',
            [UploadedFile::fake()->image('first.jpg')]
        );

        $oldPath = $first->documents->first()->path;

        $first->update([
            'status' => IdentityVerification::STATUS_REJECTED,
            'reviewed_at' => now(),
            'rejection_reason' => 'Please submit a clearer document.',
        ]);

        $resubmitted = $service->submit(
            $account,
            'Real Name',
            '900101121234',
            [UploadedFile::fake()->image('replacement.jpg')]
        );

        $this->assertSame($first->id, $resubmitted->id);

        $this->assertSame(
            IdentityVerification::STATUS_PENDING,
            $resubmitted->status
        );

        $this->assertSame(
            '900101121234',
            $resubmitted->mykad_number
        );

        $this->assertNull($resubmitted->reviewed_at);
        $this->assertNull($resubmitted->reviewer_account_id);
        $this->assertNull($resubmitted->rejection_reason);

        $this->assertCount(1, $resubmitted->documents);

        Storage::disk('local')->assertMissing($oldPath);

        Storage::disk('local')->assertExists(
            $resubmitted->documents->first()->path
        );
    }
}
