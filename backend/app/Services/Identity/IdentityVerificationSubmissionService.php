<?php

namespace App\Services\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Services\AuditLogService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class IdentityVerificationSubmissionService
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    /**
     * @param array<int, UploadedFile> $files
     */
    public function submit(
        Account $account,
        string $realName,
        string $mykadNumber,
        array $files
    ): IdentityVerification {
        $realName = trim($realName);
        $mykadNumber = trim($mykadNumber);

        if ($realName === '' || $mykadNumber === '') {
            throw new InvalidArgumentException(
                'Real name and MyKad number are required.'
            );
        }

        if ($files === []) {
            throw new InvalidArgumentException(
                'At least one identity document is required.'
            );
        }

        $validatedFiles = [];

        foreach ($files as $file) {
            $validatedFiles[] = $this->validateFile($file);
        }

        $storedPaths = [];
        $oldPaths = [];

        try {
            $verification = DB::transaction(function () use (
                $account,
                $realName,
                $mykadNumber,
                $validatedFiles,
                &$storedPaths,
                &$oldPaths
            ): IdentityVerification {
                Account::query()
                    ->whereKey($account->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $verification = IdentityVerification::query()
                    ->where('account_id', $account->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    $verification !== null
                    && $verification->status
                        !== IdentityVerification::STATUS_REJECTED
                ) {
                    throw new DomainException(
                        'Identity verification cannot be submitted from the current status.'
                    );
                }

                if ($verification !== null) {
                    $oldPaths = $verification->documents()
                        ->pluck('path')
                        ->all();

                    $verification->documents()->delete();

                    $verification->real_name = $realName;
                    $verification->mykad_number_encrypted =
                        Crypt::encryptString($mykadNumber);
                    $verification->status =
                        IdentityVerification::STATUS_PENDING;
                    $verification->submitted_at = now();
                    $verification->reviewed_at = null;
                    $verification->reviewer_account_id = null;
                    $verification->rejection_reason = null;
                    $verification->save();
                } else {
                    $verification = IdentityVerification::create([
                        'id' => (string) Str::uuid(),
                        'account_id' => $account->id,
                        'real_name' => $realName,
                        'mykad_number_encrypted' =>
                            Crypt::encryptString($mykadNumber),
                        'status' => IdentityVerification::STATUS_PENDING,
                        'submitted_at' => now(),
                    ]);
                }

                foreach ($validatedFiles as $validatedFile) {
                    /** @var UploadedFile $file */
                    $file = $validatedFile['file'];

                    $extension = $validatedFile['extension'];
                    $path = 'identity-documents/'
                        .Str::uuid().'.'.$extension;

                    $storedPath = Storage::disk('local')->putFileAs(
                        'identity-documents',
                        $file,
                        basename($path)
                    );

                    if (
                        !is_string($storedPath)
                        || $storedPath === ''
                    ) {
                        throw new RuntimeException(
                            'Identity document could not be stored.'
                        );
                    }

                    $storedPaths[] = $storedPath;

                    IdentityVerificationDocument::create([
                        'id' => (string) Str::uuid(),
                        'identity_verification_id' => $verification->id,
                        'disk' => 'local',
                        'path' => $storedPath,
                        'mime_type' => $validatedFile['mime_type'],
                        'size_bytes' => $validatedFile['size_bytes'],
                    ]);
                }

                // The submission and its single audit event must commit
                // or roll back together. Do not include MyKad data,
                // document paths, or uploaded file contents in the audit.
                $this->auditLogService->record(
                    $account,
                    $account,
                    'IDENTITY_VERIFICATION_SUBMITTED',
                    'Identity verification application submitted or resubmitted.'
                );

                return $verification->fresh(['documents']);
            });
        } catch (Throwable $exception) {
            // This catch handles transaction/submission failures only.
            // The transaction has rolled back before new files are removed.
            foreach ($storedPaths as $storedPath) {
                try {
                    Storage::disk('local')->delete($storedPath);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        /*
         * The database transaction has committed successfully.
         * Old-file cleanup must never enter the transaction-failure
         * cleanup path, because the database now points to new files.
         */
        foreach ($oldPaths as $oldPath) {
            try {
                Storage::disk('local')->delete($oldPath);
            } catch (Throwable $cleanupException) {
                // Keep the committed submission and its new files intact.
                // A cleanup failure may leave an orphaned old file.
                report($cleanupException);
            }
        }

        return $verification;
    }

    /**
     * @return array{
     *     file: UploadedFile,
     *     mime_type: string,
     *     extension: string,
     *     size_bytes: int
     * }
     */
    private function validateFile(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new InvalidArgumentException(
                'An identity document upload is invalid.'
            );
        }

        $size = $file->getSize();

        if (
            !is_int($size)
            || $size < 1
            || $size > self::MAX_FILE_BYTES
        ) {
            throw new InvalidArgumentException(
                'Each identity document must be no larger than 10 MB.'
            );
        }

        $mimeType = $file->getMimeType();
        $clientExtension = strtolower(
            $file->getClientOriginalExtension()
        );

        if ($mimeType === 'image/jpeg') {
            $imageInfo = @getimagesize($file->getRealPath());

            if (
                !is_array($imageInfo)
                || $imageInfo['mime'] !== 'image/jpeg'
                || !in_array(
                    $clientExtension,
                    ['jpg', 'jpeg'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'An identity document has an unsupported or mismatched file type.'
                );
            }

            return [
                'file' => $file,
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'size_bytes' => $size,
            ];
        }

        if ($mimeType === 'image/png') {
            $imageInfo = @getimagesize($file->getRealPath());

            if (
                !is_array($imageInfo)
                || $imageInfo['mime'] !== 'image/png'
                || $clientExtension !== 'png'
            ) {
                throw new InvalidArgumentException(
                    'An identity document has an unsupported or mismatched file type.'
                );
            }

            return [
                'file' => $file,
                'mime_type' => 'image/png',
                'extension' => 'png',
                'size_bytes' => $size,
            ];
        }

        if ($mimeType === 'application/pdf') {
            $handle = @fopen($file->getRealPath(), 'rb');
            $signature = $handle === false
                ? false
                : fread($handle, 5);

            if (is_resource($handle)) {
                fclose($handle);
            }

            if (
                $signature !== '%PDF-'
                || $clientExtension !== 'pdf'
            ) {
                throw new InvalidArgumentException(
                    'An identity document has an unsupported or mismatched file type.'
                );
            }

            return [
                'file' => $file,
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size_bytes' => $size,
            ];
        }

        throw new InvalidArgumentException(
            'Only JPEG, PNG and PDF identity documents are accepted.'
        );
    }
}
