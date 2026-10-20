<?php

namespace App\Services;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
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
        UploadedFile $document,
        ?string $licenseType = null,
        ?callable $afterCreate = null
    ): RunnerLicenseVerification {
        $licenseNumber = trim($licenseNumber);
        $licenseClass = strtoupper(trim($licenseClass));

        $licenseType = $licenseType === null
            ? ''
            : strtoupper(trim($licenseType));

        if ($licenseNumber === '' || $licenseClass === '') {
            throw new InvalidArgumentException(
                'Nombor lesen dan kelas lesen diperlukan.'
            );
        }

        if ($licenseType === '' || strlen($licenseType) > 30) {
            throw new InvalidArgumentException(
                'Jenis lesen diperlukan dan mestilah sah.'
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

        /*
         * Tarikh tamat yang sah dari segi format tetap disimpan,
         * termasuk tarikh yang sudah berlalu.
         *
         * Bagi permohonan Runner baharu, admin menyemak dokumen
         * bersama permohonan. Lesen tamat tempoh tidak boleh
         * diluluskan.
         */
        $expiry->startOfDay();

        $filename = (string) Str::uuid().'.'.match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'pdf',
        };

        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $account,
                $licenseNumber,
                $licenseClass,
                $licenseType,
                $expiry,
                $document,
                $mimeType,
                $size,
                $filename,
                $afterCreate,
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
                        'Identity verification is required before applying to become a Runner.'
                    );
                }

                /*
                 * Permohonan pertama mesti melalui RunnerStatusService.
                 * Endpoint lesen berasingan hanya untuk Runner yang
                 * telah diluluskan, contohnya bagi pembaharuan lesen.
                 */
                if (
                    $afterCreate === null
                    && $lockedAccount->runner_status
                        !== Account::RUNNER_STATUS_APPROVED
                ) {
                    throw new DomainException(
                        'Lesen tambahan hanya boleh dihantar selepas permohonan Runner diluluskan.'
                    );
                }

                /*
                 * Permohonan Runner baharu hanya boleh dihantar
                 * apabila akaun masih NOT_RUNNER.
                 */
                if (
                    $afterCreate !== null
                    && $lockedAccount->runner_status
                        !== Account::RUNNER_STATUS_NOT_RUNNER
                ) {
                    throw new DomainException(
                        'A Runner application cannot be submitted from the current status.'
                    );
                }

                $catalogEntry = DrivingLicenseCatalog::query()
                    ->where('code', $licenseClass)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($catalogEntry === null) {
                    throw new InvalidArgumentException(
                        'Kelas lesen tidak sah atau tidak aktif.'
                    );
                }

                /*
                 * Jenis lesen mesti datang daripada katalog dinamik.
                 * Tiada inferens daripada kelas lesen dan tiada
                 * fallback kepada GENERAL.
                 */
                $typeEntry = DrivingLicenseType::query()
                    ->where('code', $licenseType)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($typeEntry === null) {
                    throw new InvalidArgumentException(
                        'Jenis lesen tidak sah atau tidak aktif.'
                    );
                }

                $pendingApplication = RunnerLicenseVerification::query()
                    ->where('account_id', $lockedAccount->id)
                    ->where('license_class', $licenseClass)
                    ->where(
                        'status',
                        RunnerLicenseVerification::STATUS_PENDING
                    )
                    ->lockForUpdate()
                    ->exists();

                if ($pendingApplication) {
                    throw new DomainException(
                        'Masih terdapat permohonan PENDING untuk kelas lesen ini.'
                    );
                }

                $previous = RunnerLicenseVerification::query()
                    ->where('account_id', $lockedAccount->id)
                    ->where('license_class', $licenseClass)
                    ->where(
                        'status',
                        RunnerLicenseVerification::STATUS_VERIFIED
                    )
                    ->where('is_current', true)
                    ->orderByDesc('submitted_at')
                    ->lockForUpdate()
                    ->first();

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

                $verification = RunnerLicenseVerification::query()->create([
                    'id' => (string) Str::uuid(),
                    'account_id' => $lockedAccount->id,
                    'license_number_encrypted' =>
                        Crypt::encryptString($licenseNumber),
                    'license_type' => $typeEntry->code,
                    'license_class' => $licenseClass,
                    'previous_license_verification_id' => $previous?->id,
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

                if ($afterCreate !== null) {
                    $afterCreate($verification, $lockedAccount);
                }

                return $verification;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }
}
