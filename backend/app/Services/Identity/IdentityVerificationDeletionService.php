<?php

namespace App\Services\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        /*
         * Read every document before deleting anything.
         * Keep the bytes temporarily so deleted files can be restored
         * if a later file deletion or the database transaction fails.
         */
        $files = [];

        foreach ($documents as $document) {
            $diskName = $document->disk;
            $path = $document->path;

            if (
                !is_string($diskName)
                || $diskName === ''
                || !is_string($path)
                || $path === ''
            ) {
                throw new RuntimeException(
                    'Metadata fail identiti tidak sah; pemadaman dibatalkan.'
                );
            }

            try {
                $disk = Storage::disk($diskName);

                if (!$disk->exists($path)) {
                    throw new RuntimeException(
                        'Fail dokumen identiti tidak ditemui; pemadaman dibatalkan.'
                    );
                }

                $contents = $disk->get($path);

                if (!is_string($contents)) {
                    throw new RuntimeException(
                        'Fail dokumen identiti tidak dapat dibaca.'
                    );
                }

                $files[] = [
                    'disk' => $diskName,
                    'path' => $path,
                    'contents' => $contents,
                    'deleted' => false,
                ];
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'Pemeriksaan fail identiti gagal. Tiada rekod dipadam.',
                    0,
                    $exception
                );
            }
        }

        /*
         * Delete physical files one by one.
         * If any deletion fails, restore files already deleted.
         */
        try {
            foreach ($files as $index => $file) {
                $disk = Storage::disk($file['disk']);

                if (!$disk->delete($file['path'])) {
                    throw new RuntimeException(
                        'Fail dokumen identiti gagal dipadam.'
                    );
                }

                $files[$index]['deleted'] = true;
            }
        } catch (Throwable $exception) {
            $restorationFailed = $this->restoreDeletedFiles($files);

            if ($restorationFailed) {
                throw new RuntimeException(
                    'Pemadaman gagal dan pemulihan semua fail tidak dapat disahkan. '
                    .'Rekod pangkalan data dikekalkan; pemeriksaan operasi diperlukan.',
                    0,
                    $exception
                );
            }

            throw new RuntimeException(
                'Pemadaman fail gagal. Fail yang telah dipadam dipulihkan dan rekod pangkalan data dikekalkan.',
                0,
                $exception
            );
        }

        /*
         * Delete database records and write the audit event atomically.
         * Audit text is fixed so free-text input cannot accidentally put
         * MyKad details or document contents into the audit log.
         */
        try {
            DB::transaction(function () use (
                $actor,
                $target,
                $verification
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
                    'Identity verification data and document metadata deleted.'
                );
            });
        } catch (Throwable $exception) {
            $restorationFailed = $this->restoreDeletedFiles($files);

            if ($restorationFailed) {
                throw new RuntimeException(
                    'Transaksi pangkalan data gagal dan pemulihan semua fail tidak dapat disahkan. '
                    .'Pemeriksaan operasi diperlukan.',
                    0,
                    $exception
                );
            }

            throw new RuntimeException(
                'Transaksi pangkalan data gagal. Fail dokumen telah dipulihkan.',
                0,
                $exception
            );
        }
    }

    /**
     * Restore files deleted by this operation.
     *
     * Returns true if any file could not be restored.
     */
    private function restoreDeletedFiles(array $files): bool
    {
        $failed = false;

        foreach (array_reverse($files, true) as $file) {
            if (!$file['deleted']) {
                continue;
            }

            try {
                $disk = Storage::disk($file['disk']);

                if (!$disk->put($file['path'], $file['contents'])) {
                    $failed = true;
                    continue;
                }

                if (!$disk->exists($file['path'])) {
                    $failed = true;
                }
            } catch (Throwable) {
                $failed = true;
            }
        }

        return $failed;
    }
}
