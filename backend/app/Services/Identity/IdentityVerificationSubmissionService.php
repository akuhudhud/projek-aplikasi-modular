<?php

namespace App\Services\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
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
     * Submit an identity verification application or resubmit a rejected
     * application. The caller must already be authenticated as the owner.
     *
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
            if (!$file instanceof UploadedFile) {
                throw new InvalidArgumentException(
                    'Every identity document must be an uploaded file.'
                );
            }

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
                    && $verification->status !== IdentityVerification::STATUS_REJECTED
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

                    if (!is_string($storedPath) || $storedPath === '') {
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

                return $verification->fresh(['documents']);
            });

            foreach ($oldPaths as $oldPath) {
                Storage::disk('local')->delete($oldPath);
            }

            return $verification;
        } catch (Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    /**
     * Inspect actual file content and require the filename extension to
     * match it. Client-provided MIME headers are not trusted.
     *
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

        if (!is_int($size) || $size < 1 || $size > self::MAX_FILE_BYTES) {
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
                || ($imageInfo['mime'] ?? null) !== 'image/jpeg'
                || !in_array($clientExtension, ['jpg', 'jpeg'], true)
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
                || ($imageInfo['mime'] ?? null) !== 'image/png'
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
