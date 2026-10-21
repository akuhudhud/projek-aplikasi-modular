<?php

namespace App\Services\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

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

        if ($reason === '') {
            throw new \InvalidArgumentException(
                'Alasan pemadaman wajib diisi.'
            );
        }

        $verification = IdentityVerification::query()
            ->with('documents')
            ->where('account_id', $accountId)
            ->first();

        if ($verification === null) {
            throw new \RuntimeException(
                'Rekod pengesahan identiti tidak ditemui.'
            );
        }

        $target = Account::query()
            ->whereKey($verification->account_id)
            ->firstOrFail();

        $documents = $verification->documents;
        $files = [];

        foreach ($documents as $document) {
            $files[] = [
                'disk' => $document->disk,
                'path' => $document->path,
            ];
        }

        DB::transaction(function () use (
            $actor,
            $target,
            $verification,
            $reason
        ): void {
            $lockedVerification = IdentityVerification::query()
                ->whereKey($verification->id)
                ->lockForUpdate()
                ->firstOrFail();

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

        foreach ($files as $file) {
            $disk = Storage::disk($file['disk']);

            if ($disk->exists($file['path'])) {
                if (!$disk->delete($file['path'])) {
                    throw new RuntimeException(
                        'Rekod telah dipadam tetapi fail fizikal gagal dipadam. '
                        .'Disk: '.$file['disk']
                    );
                }
            }
        }
    }
}
