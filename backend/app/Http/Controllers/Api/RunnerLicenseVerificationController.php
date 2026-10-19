<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\RunnerLicenseVerification;
use App\Services\AuditLogService;
use App\Services\RunnerLicenseVerificationService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunnerLicenseVerificationController extends Controller
{
    public function showMyStatus(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->attributes->get('account');

        $verification = RunnerLicenseVerification::query()
            ->where('account_id', $account->id)
            ->first();

        if ($verification === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'runner_license_verification' => [
                        'status' => 'NOT_SUBMITTED',
                        'license_class' => null,
                        'expires_at' => null,
                        'submitted_at' => null,
                        'reviewed_at' => null,
                        'rejection_reason' => null,
                    ],
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'runner_license_verification' => [
                    'status' => $verification->status,
                    'license_class' => $verification->license_class,
                    'expires_at' => $verification->expires_at?->toDateString(),
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

    public function submit(
        Request $request,
        RunnerLicenseVerificationService $service,
        AuditLogService $auditLogService
    ): JsonResponse {
        $validated = $request->validate([
            'license_number' => ['required', 'string', 'max:100'],
            'license_class' => ['required', 'string', 'max:30'],
            'expires_at' => ['required', 'date_format:Y-m-d'],
            'document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:10240',
            ],
        ]);

        /** @var Account $account */
        $account = $request->attributes->get('account');

        try {
            $verification = $service->submit(
                $account,
                $validated['license_number'],
                $validated['license_class'],
                $validated['expires_at'],
                $validated['document']
            );

            $auditLogService->record(
                $account,
                $account,
                'RUNNER_LICENSE_VERIFICATION_SUBMITTED',
                'Driving license verification submitted or resubmitted.'
            );

            return response()->json([
                'success' => true,
                'message' => 'Pengesahan lesen memandu berjaya dihantar.',
                'data' => [
                    'runner_license_verification' => [
                        'status' => $verification->status,
                        'license_class' => $verification->license_class,
                        'expires_at' => $verification->expires_at->toDateString(),
                        'submitted_at' => $verification->submitted_at,
                    ],
                ],
            ], 201);
        } catch (DomainException | InvalidArgumentException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                422
            );
        } catch (ModelNotFoundException $exception) {
            return $this->errorResponse(
                'Akaun tidak ditemui.',
                404
            );
        }
    }

    public function showForReview(string $accountId): JsonResponse
    {
        $verification = RunnerLicenseVerification::query()
            ->with('account')
            ->where('account_id', $accountId)
            ->first();

        if ($verification === null) {
            return $this->errorResponse(
                'Permohonan pengesahan lesen tidak ditemui.',
                404
            );
        }

        $licenseNumber = $verification->decryptLicenseNumber();

        return response()->json([
            'success' => true,
            'data' => [
                'runner_license_verification' => [
                    'account_id' => $verification->account_id,
                    'display_name' => $verification->account->name,
                    'license_number_masked' => str_repeat(
                        '*',
                        max(0, strlen($licenseNumber) - 4)
                    ).substr($licenseNumber, -4),
                    'license_class' => $verification->license_class,
                    'expires_at' => $verification->expires_at->toDateString(),
                    'status' => $verification->status,
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

    public function decide(
        Request $request,
        string $accountId,
        AuditLogService $auditLogService
    ): JsonResponse {
        $validated = $request->validate([
            'decision' => [
                'required',
                'string',
                'in:VERIFIED,REJECTED',
            ],
            'rejection_reason' => [
                'required_if:decision,REJECTED',
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $rejectionReason = isset($validated['rejection_reason'])
            ? trim($validated['rejection_reason'])
            : null;

        if (
            $validated['decision'] === RunnerLicenseVerification::STATUS_REJECTED
            && ($rejectionReason === null || $rejectionReason === '')
        ) {
            return $this->errorResponse(
                'Sebab penolakan diperlukan.',
                422
            );
        }

        /** @var Account $actor */
        $actor = $request->attributes->get('account');

        try {
            $verification = DB::transaction(function () use (
                $accountId,
                $actor,
                $validated,
                $rejectionReason,
                $auditLogService
            ): RunnerLicenseVerification {
                $verification = RunnerLicenseVerification::query()
                    ->where('account_id', $accountId)
                    ->lockForUpdate()
                    ->first();

                if ($verification === null) {
                    throw new ModelNotFoundException(
                        'Permohonan pengesahan lesen tidak ditemui.'
                    );
                }

                if (
                    $verification->status
                    !== RunnerLicenseVerification::STATUS_PENDING
                ) {
                    throw new DomainException(
                        'Hanya permohonan lesen berstatus PENDING boleh disemak.'
                    );
                }

                if (
                    $validated['decision']
                        === RunnerLicenseVerification::STATUS_VERIFIED
                    && $verification->expires_at->lt(now()->startOfDay())
                ) {
                    throw new DomainException(
                        'Lesen telah tamat tempoh dan tidak boleh disahkan.'
                    );
                }

                $targetAccount = Account::query()
                    ->whereKey($verification->account_id)
                    ->firstOrFail();

                $verification->status = $validated['decision'];
                $verification->reviewer_account_id = $actor->id;
                $verification->reviewed_at = now();
                $verification->rejection_reason =
                    $validated['decision']
                        === RunnerLicenseVerification::STATUS_REJECTED
                            ? $rejectionReason
                            : null;
                $verification->save();

                $action = $validated['decision']
                    === RunnerLicenseVerification::STATUS_VERIFIED
                        ? 'RUNNER_LICENSE_VERIFICATION_VERIFIED'
                        : 'RUNNER_LICENSE_VERIFICATION_REJECTED';

                $reason = $validated['decision']
                    === RunnerLicenseVerification::STATUS_VERIFIED
                        ? 'Driving license verification marked VERIFIED.'
                        : 'Driving license verification marked REJECTED.';

                // Never place license numbers, document paths, or free-text
                // rejection reasons in audit logs.
                $auditLogService->record(
                    $actor,
                    $targetAccount,
                    $action,
                    $reason
                );

                return $verification->fresh();
            });

            return response()->json([
                'success' => true,
                'message' => 'Keputusan pengesahan lesen disimpan.',
                'data' => [
                    'runner_license_verification' => [
                        'account_id' => $verification->account_id,
                        'status' => $verification->status,
                        'reviewed_at' => $verification->reviewed_at,
                    ],
                ],
            ]);
        } catch (DomainException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                422
            );
        } catch (ModelNotFoundException $exception) {
            return $this->errorResponse(
                'Permohonan pengesahan lesen tidak ditemui.',
                404
            );
        }
    }

    public function downloadDocument(
        string $accountId
    ): StreamedResponse|JsonResponse {
        $verification = RunnerLicenseVerification::query()
            ->where('account_id', $accountId)
            ->first();

        if ($verification === null) {
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

        $extension = match ($verification->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        return $disk->download(
            $verification->document_path,
            'runner-license-'.\Illuminate\Support\Str::uuid().'.'.$extension,
            [
                'Content-Type' => $verification->mime_type,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
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
