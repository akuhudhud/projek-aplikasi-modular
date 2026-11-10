<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RunnerLicenseVerification;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RunnerLicenseVerificationController extends Controller
{
    public function showMyStatus(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        $verification = RunnerLicenseVerification::query()
            ->where('account_id', $account->id)
            ->orderByDesc('submitted_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'runner_license_verification' => $verification === null
                    ? $this->emptyStatusPayload()
                    : $this->statusPayload($verification),
            ],
        ]);
    }

    public function showForReview(
        Request $request,
        string $accountId,
        AuditLogService $auditLogService
    ): JsonResponse {
        $verification = $this->findVerification($accountId);

        if (
            $verification === null
            || $verification->account === null
        ) {
            return $this->errorResponse(
                'Permohonan pengesahan lesen tidak ditemui.',
                404
            );
        }

        $actor = $request->attributes->get('account');
        $targetAccount = $verification->account;

        $auditLogService->record(
            $actor,
            $targetAccount,
            'RUNNER_LICENSE_VERIFICATION_VIEWED',
            'Runner license verification record viewed by authorised reviewer.'
        );

        $licenseNumber = $verification->license_number;

        return response()->json([
            'success' => true,
            'data' => [
                'runner_license_verification' => [
                    'id' => $verification->id,
                    'account_id' => $verification->account_id,
                    'display_name' => $targetAccount->name,
                    'license_number_masked' => str_repeat(
                        '*',
                        max(0, strlen($licenseNumber) - 4)
                    ).substr($licenseNumber, -4),
                    'license_type' => $verification->license_type,
                    'license_class' => $verification->license_class,
                    'expires_at' => $verification->expires_at
                        ->toDateString(),
                    'status' => $verification->status,
                    'is_current' => $verification->is_current,
                    'mime_type' => $verification->mime_type,
                    'size_bytes' => $verification->size_bytes,
                    'submitted_at' => $verification->submitted_at,
                    'reviewed_at' => $verification->reviewed_at,
                    'rejection_reason' =>
                        $verification->status
                            === RunnerLicenseVerification::STATUS_REJECTED
                            ? $verification->rejection_reason
                            : null,
                ],
            ],
        ]);
    }

    public function downloadDocument(
        Request $request,
        string $accountId,
        AuditLogService $auditLogService
    ): Response|JsonResponse {
        $verification = $this->findVerification($accountId);

        if (
            $verification === null
            || $verification->account === null
        ) {
            return $this->errorResponse(
                'Dokumen lesen tidak ditemui.',
                404
            );
        }

        $disk = Storage::disk($verification->disk);

        if (!$disk->exists($verification->document_path)) {
            return $this->errorResponse(
                'Dokumen lesen tidak ditemui.',
                404
            );
        }

        $contents = $disk->get($verification->document_path);

        if (!is_string($contents)) {
            return $this->errorResponse(
                'Dokumen lesen tidak dapat diperoleh.',
                500
            );
        }

        $actor = $request->attributes->get('account');
        $targetAccount = $verification->account;

        $auditLogService->record(
            $actor,
            $targetAccount,
            'RUNNER_LICENSE_DOCUMENT_DOWNLOADED',
            'Runner license document downloaded by authorised reviewer.'
        );

        $extension = match ($verification->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        $filename = 'runner-license-'.Str::uuid().'.'.$extension;

        return response($contents, 200, [
            'Content-Type' => $verification->mime_type,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function findVerification(
        string $identifier
    ): ?RunnerLicenseVerification {
        $byId = RunnerLicenseVerification::query()
            ->with('account')
            ->whereKey($identifier)
            ->first();

        if ($byId !== null) {
            return $byId;
        }

        return RunnerLicenseVerification::query()
            ->with('account')
            ->where('account_id', $identifier)
            ->orderByDesc('submitted_at')
            ->first();
    }

    private function statusPayload(
        RunnerLicenseVerification $verification
    ): array {
        return [
            'id' => $verification->id,
            'status' => $verification->status,
            'is_current' => $verification->is_current,
            'license_type' => $verification->license_type,
            'license_class' => $verification->license_class,
            'expires_at' => $verification->expires_at->toDateString(),
            'submitted_at' => $verification->submitted_at,
            'reviewed_at' => $verification->reviewed_at,
            'rejection_reason' =>
                $verification->status
                    === RunnerLicenseVerification::STATUS_REJECTED
                    ? $verification->rejection_reason
                    : null,
        ];
    }

    private function emptyStatusPayload(): array
    {
        return [
            'id' => null,
            'status' => 'NOT_SUBMITTED',
            'is_current' => false,
            'license_type' => null,
            'license_class' => null,
            'expires_at' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ];
    }

    private function errorResponse(
        string $message,
        int $status
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
