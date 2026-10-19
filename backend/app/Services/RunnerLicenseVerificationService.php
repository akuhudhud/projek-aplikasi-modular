<?php

namespace App\Services;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\RunnerLicenseVerification;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

        $expiry = Carbon::createFromFormat('!Y-m-d', $expiresAt);

        if (
            $expiry === false
            || $expiry->format('Y-m-d') !== $expiresAt
        ) {
            throw new InvalidArgumentException(
                'Tarikh tamat lesen tidak sah.'
            );
        }

        $expiry->startOfDay();

        if ($expiry->lt(now()->startOfDay())) {
            throw new InvalidArgumentException(
                'Lesen memandu telah tamat tempoh.'
            );
        }

        $filename = (string) Str::uuid().'.'.match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'pdf',
        };

        $storedPath = null;

        try {
            $verification = DB::transaction(function () use (
                $account,
                $licenseNumber,
                $licenseClass,
                $expiry,
                $document,
                $mimeType,
                $size,
                $filename,
                &$storedPath
            ): RunnerLicenseVerification {
                $lockedAccount = Account::query()
                    ->whereKey($account->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedAccount->status !== 'ACTIVE'
                    || !in_array(
                        $lockedAccount->role,
                        ['USER', 'SUPER_ADMIN'],
                        true
                    )
                ) {
                    throw new DomainException(
                        'Akaun tidak layak menghantar pengesahan lesen.'
                    );
                }

                $identityVerified = IdentityVerification::query()
                    ->where('account_id', $lockedAccount->id)
                    ->where(
                        'status',
                        IdentityVerification::STATUS_VERIFIED
                    )
                    ->lockForUpdate()
                    ->exists();

                if (!$identityVerified) {
                    throw new DomainException(
                        'Pengesahan identiti mesti berstatus VERIFIED sebelum lesen dihantar.'
                    );
                }

                $pendingApplication = RunnerLicenseVerification::query()
                    ->where('account_id', $lockedAccount->id)
                    ->where(
                        'status',
                        RunnerLicenseVerification::STATUS_PENDING
                    )
                    ->lockForUpdate()
                    ->exists();

                if ($pendingApplication) {
                    throw new DomainException(
                        'Masih terdapat permohonan lesen berstatus PENDING.'
                    );
                }

                $storedPath = Storage::disk('local')->putFileAs(
                    'runner-license-documents',
                    $document,
                    $filename
                );

                if (!is_string($storedPath) || $storedPath === '') {
                    throw new RuntimeException(
                        'Dokumen lesen gagal disimpan.'
                    );
                }

                return RunnerLicenseVerification::query()->create([
                    'id' => (string) Str::uuid(),
                    'account_id' => $lockedAccount->id,
                    'license_number_encrypted' =>
                        Crypt::encryptString($licenseNumber),
                    'license_class' => $licenseClass,
                    'expires_at' => $expiry->toDateString(),
                    'status' => RunnerLicenseVerification::STATUS_PENDING,
                    'is_current' => false,
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                    'reviewer_account_id' => null,
                    'rejection_reason' => null,
                    'disk' => 'local',
                    'document_path' => $storedPath,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                ]);
            });

            return $verification;
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }
}
