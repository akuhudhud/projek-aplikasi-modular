<?php

namespace App\Services\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Services\AuditLogService;
use DomainException;
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

                /*
                 * Jangan benarkan rekod identiti Runner yang masih aktif
                 * atau sedang memohon dipadam sehingga status Runner
                 * diselesaikan secara eksplisit.
                 */
                if (in_array(
                    $target->runner_status,
                    [
                        Account::RUNNER_STATUS_PENDING,
                        Account::RUNNER_STATUS_APPROVED,
                    ],
                    true
                )) {
                    throw new DomainException(
                        'Rekod pengesahan identiti tidak boleh dipadam semasa permohonan Runner masih PENDING atau status Runner APPROVED. Selesaikan status Runner terlebih dahulu.'
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

                // Baca semua fail sebelum memadam mana-mana fail.
                foreach ($documents as $document) {
                    $diskName = $document->disk;
                    $path = $document->path;

                    if ($diskName === '' || $path === '') {
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
                 * Kekalkan kunci pangkalan data semasa pemadaman fail.
                 * Semak kewujudan fail, bukan nilai pulangan delete().
                 */
                foreach ($files as $index => $file) {
                    $fileDeletionStarted = true;
                    $disk = Storage::disk($file['disk']);

                    try {
                        $disk->delete($file['path']);
                    } catch (Throwable $exception) {
                        if (!$disk->exists($file['path'])) {
                            $files[$index]['deleted'] = true;
                        }

                        $fileDeletionFailed = true;
                        throw $exception;
                    }

                    if (!$disk->exists($file['path'])) {
                        $files[$index]['deleted'] = true;
                        continue;
                    }

                    $fileDeletionFailed = true;

                    throw new RuntimeException(
                        'Fail dokumen identiti gagal dipadam.'
                    );
                }

                // Rekod pangkalan data dan audit berada dalam transaksi sama.
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
            // Transaksi sudah rollback; pulihkan fail yang telah dipadam.
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
     * @param array<int, array{
     *     disk: string,
     *     path: string,
     *     contents: string,
     *     deleted: bool
     * }> $files
     *
     * @return bool True if any file could not be restored and verified.
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
                    continue;
                }

                // Kewujudan fail sahaja tidak membuktikan pemulihan tepat.
                $restoredContents = $disk->get($file['path']);

                if (
                    !is_string($restoredContents)
                    || $restoredContents !== $file['contents']
                ) {
                    $failed = true;
                }
            } catch (Throwable) {
                $failed = true;
            }
        }

        return $failed;
    }
}
