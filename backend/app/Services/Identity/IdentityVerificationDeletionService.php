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

        /*
         * Lock the account first, matching the submission service's
         * lock order. This serializes deletion against resubmission.
         */
        $files = [];
        $fileDeletionStarted = false;
        $fileDeletionFailed = false;
        $databaseMutationStarted = false;

        try {
            DB::transaction(function () use (
                $actor,
                $accountId,
                &$files,
                &$fileDeletionStarted,
                &$fileDeletionFailed,
                &$databaseMutationStarted
            ): void {
                $target = Account::query()
                    ->whereKey($accountId)
                    ->lockForUpdate()
                    ->first();

                if ($target === null) {
                    throw new ModelNotFoundException(
                        'Akaun tidak ditemui.'
                    );
                }

                $verification = IdentityVerification::query()
                    ->where('account_id', $target->id)
                    ->lockForUpdate()
                    ->first();

                if ($verification === null) {
                    throw new ModelNotFoundException(
                        'Rekod pengesahan identiti tidak ditemui.'
                    );
                }

                $documents = $verification->documents()
                    ->lockForUpdate()
                    ->get();

                /*
                 * Read and retain every file before deleting any of them.
                 */
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
                 * Keep database locks while deleting physical files.
                 */
                foreach ($files as $index => $file) {
                    $fileDeletionStarted = true;

                    try {
                        $disk = Storage::disk($file['disk']);
                        $deleted = $disk->delete($file['path']);

                        if ($deleted || !$disk->exists($file['path'])) {
                            $files[$index]['deleted'] = true;
                        }

                        if (!$deleted) {
                            $fileDeletionFailed = true;

                            throw new RuntimeException(
                                'Fail dokumen identiti gagal dipadam.'
                            );
                        }
                    } catch (Throwable $exception) {
                        $fileDeletionFailed = true;
                        throw $exception;
                    }
                }

                /*
                 * Database changes and audit record are atomic.
                 */
                $databaseMutationStarted = true;

                $verification->documents()->delete();
                $verification->delete();

                $this->auditLogService->record(
                    $actor,
                    $target,
                    'IDENTITY_VERIFICATION_DATA_DELETED',
                    'Identity verification data and document metadata deleted.'
                );
            });
        } catch (Throwable $exception) {
            /*
             * The database transaction has rolled back before execution
             * reaches this handler. Restore any files already deleted.
             */
            $restorationFailed = $this->restoreDeletedFiles($files);

            if ($restorationFailed) {
                throw new RuntimeException(
                    'Pemulihan semua fail tidak dapat disahkan selepas pemadaman gagal. '
                    .'Rekod pangkalan data dikekalkan; pemeriksaan operasi diperlukan.',
                    0,
                    $exception
                );
            }

            if (
                $fileDeletionFailed
                || ($fileDeletionStarted && !$databaseMutationStarted)
            ) {
                throw new RuntimeException(
                    'Pemadaman fail gagal. Fail yang telah dipadam dipulihkan dan rekod pangkalan data dikekalkan.',
                    0,
                    $exception
                );
            }

            if ($databaseMutationStarted) {
                throw new RuntimeException(
                    'Transaksi pangkalan data gagal. Fail dokumen telah dipulihkan.',
                    0,
                    $exception
                );
            }

            throw $exception;
        }
    }

    /**
     * Restore files deleted by this operation.
     *
     * @param array<int, array{
     *     disk: string,
     *     path: string,
     *     contents: string,
     *     deleted: bool
     * }> $files
     *
     * @return bool True if any file could not be restored.
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
