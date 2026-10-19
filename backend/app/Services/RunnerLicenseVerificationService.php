<?php

namespace App\Services;

use App\Models\Account;
use App\Models\RunnerLicenseVerification;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class RunnerLicenseVerificationService
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    public function submit(
        Account $account,
        string $licenseNumber,
        string $licenseClass,
        string $expiresAt,
        UploadedFile $document
    ): RunnerLicenseVerification {
        $licenseNumber = trim($licenseNumber);
        $licenseClass = strtoupper(trim($licenseClass));

        if ($licenseNumber === '' || $licenseClass === '') {
            throw new InvalidArgumentException(
                'Nombor lesen dan kelas lesen diperlukan.'
            );
        }

        if (!$document->isValid()) {
            throw new InvalidArgumentException(
                'Dokumen lesen tidak sah.'
            );
        }

        $size = $document->getSize();

        if (!is_int($size) || $size < 1 || $size > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException(
                'Saiz dokumen lesen mestilah tidak melebihi 10 MB.'
            );
        }

        $mimeType = $document->getMimeType();
        $extension = strtolower(
            $document->getClientOriginalExtension()
        );

        $allowedTypes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'application/pdf' => ['pdf'],
        ];

        if (
            !isset($allowedTypes[$mimeType])
            || !in_array($extension, $allowedTypes[$mimeType], true)
        ) {
            throw new InvalidArgumentException(
                'Dokumen lesen mestilah fail JPEG, PNG atau PDF yang sepadan.'
            );
        }

        $expiry = \Illuminate\Support\Carbon::parse($expiresAt)
            ->startOfDay();

        if ($expiry->lt(now()->startOfDay())) {
            throw new InvalidArgumentException(
                'Lesen memandu telah tamat tempoh.'
            );
        }

        $accountIsEligibleForSubmission = Account::query()
            ->whereKey($account->id)
            ->where('status', 'ACTIVE')
            ->whereIn('role', ['USER', 'SUPER_ADMIN'])
            ->whereHas('identityVerification', function ($query): void {
                $query->where(
                    'status',
                    \App\Models\IdentityVerification::STATUS_VERIFIED
                );
            })
            ->exists();

        if (!$accountIsEligibleForSubmission) {
            throw new DomainException(
                'Pengesahan identiti mesti berstatus VERIFIED sebelum lesen dihantar.'
            );
        }

        $path = 'runner-license-documents/'.Str::uuid().'.'
            .($mimeType === 'image/jpeg'
                ? 'jpg'
                : ($mimeType === 'image/png' ? 'png' : 'pdf'));

        $storedPath = null;
        $oldPath = null;

        try {
            $verification = DB::transaction(function () use (
                $account,
                $licenseNumber,
                $licenseClass,
                $expiry,
                $document,
                $mimeType,
                $size,
                $path,
                &$storedPath,
                &$oldPath
            ): RunnerLicenseVerification {
                Account::query()
                    ->whereKey($account->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $identityVerified = $account->identityVerification()
                    ->where(
                        'status',
                        \App\Models\IdentityVerification::STATUS_VERIFIED
                    )
                    ->exists();

                if (!$identityVerified) {
                    throw new DomainException(
                        'Pengesahan identiti mesti berstatus VERIFIED sebelum lesen dihantar.'
                    );
                }

                $existing = RunnerLicenseVerification::query()
                    ->where('account_id', $account->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    $existing !== null
                    && $existing->status !== RunnerLicenseVerification::STATUS_REJECTED
                ) {
                    throw new DomainException(
                        'Permohonan lesen hanya boleh dihantar semula jika status REJECTED.'
                    );
                }

                if ($existing !== null) {
                    $oldPath = $existing->document_path;
                }

                $storedPath = Storage::disk('local')->putFileAs(
                    'runner-license-documents',
                    $document,
                    basename($path)
                );

                if (!is_string($storedPath) || $storedPath === '') {
                    throw new RuntimeException(
                        'Dokumen lesen gagal disimpan.'
                    );
                }

                $values = [
                    'license_number_encrypted' =>
                        Crypt::encryptString($licenseNumber),
                    'license_class' => $licenseClass,
                    'expires_at' => $expiry->toDateString(),
                    'status' => RunnerLicenseVerification::STATUS_PENDING,
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                    'reviewer_account_id' => null,
                    'rejection_reason' => null,
                    'disk' => 'local',
                    'document_path' => $storedPath,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                ];

                if ($existing !== null) {
                    $existing->fill($values)->save();

                    return $existing->fresh();
                }

                return RunnerLicenseVerification::query()->create([
                    'id' => (string) Str::uuid(),
                    'account_id' => $account->id,
                    ...$values,
                ]);
            });

            if ($oldPath !== null && $oldPath !== $storedPath) {
                Storage::disk('local')->delete($oldPath);
            }

            return $verification;
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }
}
