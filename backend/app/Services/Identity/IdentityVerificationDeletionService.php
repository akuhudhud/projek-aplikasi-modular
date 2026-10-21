<?php

namespace App\Services\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class IdentityVerificationDeletionService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
    }

    public function delete(
        Account $actor,
        string $accountId,
        string $reason
    ): void {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException(
                'Alasan pemadaman mesti antara 5 hingga 500 aksara.'
            );
        }

        $verification = IdentityVerification::query()
            ->where('account_id', $accountId)
            ->first();

        if ($verification === null) {
            throw new ModelNotFoundException(
                'Rekod pengesahan identiti tidak ditemui.'
            );
        }

        $target = Account::query()
            ->whereKey($verification->account_id)
            ->firstOrFail();

        $documents = $verification->documents()->get();

        $files = $documents->map(fn ($document): array => [
            'disk' => $document->disk,
            'path' => $document->path,
        ])->all();

        /*
         * Validate all file references before deleting any file.
         * The disk and path come from trusted database records.
         */
        foreach ($files as $file) {
            if (
                !is_string($file['disk'])
                || $file['disk'] === ''
                || !is_string($file['path'])
                || $file['path'] === ''
            ) {
                throw new RuntimeException(
                    'Metadata fail identiti tidak sah; pemadaman dibatalkan.'
                );
            }
        }

        /*
         * Delete physical files first.
         * If a deletion fails, stop before deleting database records.
         */
        foreach ($files as $file) {
            $disk = Storage::disk($file['disk']);

            try {
                if (
                    $disk->exists($file['path'])
                    && !$disk->delete($file['path'])
                ) {
                    throw new RuntimeException(
                        'Fail dokumen identiti gagal dipadam.'
                    );
                }
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'Pemadaman fail identiti gagal. Rekod pangkalan data dikekalkan.',
                    0,
                    $exception
                );
            }
        }

        /*
         * Delete database records and write the audit event atomically.
         * The audit entry contains the reason but never MyKad data or file contents.
         */
        DB::transaction(function () use (
            $actor,
            $target,
            $verification,
            $reason
        ): void {
            $lockedVerification = IdentityVerification::query()
                ->whereKey($verification->id)
                ->lockForUpdate()
                ->first();

            if ($lockedVerification === null) {
                throw new ModelNotFoundException(
                    'Rekod pengesahan identiti tidak ditemui.'
                );
            }

            $lockedVerification->documents()->delete();
            $lockedVerification->delete();

            $this->auditLogService->record(
                $actor,
                $target,
                'IDENTITY_VERIFICATION_DATA_DELETED',
                'Identity verification data deleted. Reason: '
                    .Str::limit($reason, 500, '')
            );
        });
    }
}
