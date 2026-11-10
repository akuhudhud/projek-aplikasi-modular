<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RunnerLicenseVerification;
use App\Services\AuditLogService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RunnerLicenseVerificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'sometimes',
                'required',
                'string',
                'in:PENDING,VERIFIED,REJECTED',
            ],
            'license_type' => [
                'sometimes',
                'required',
                'string',
                'max:30',
            ],
            'account_id' => ['sometimes', 'required', 'uuid'],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = RunnerLicenseVerification::query()
            ->with('account:id,name')
            ->where('status', $validated['status'] ?? 'PENDING');

        if (isset($validated['license_type'])) {
            $query->where(
                'license_type',
                strtoupper(trim($validated['license_type']))
            );
        }

        if (isset($validated['account_id'])) {
            $query->where('account_id', $validated['account_id']);
        }

        $applications = $query
            ->orderByDesc('submitted_at')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => [
                'applications' => $applications->getCollection()
                    ->map(fn (
                        RunnerLicenseVerification $verification
                    ): array => [
                        'id' => $verification->id,
                        'account_id' => $verification->account_id,
                        'display_name' => $verification->account?->name,
                        'license_type' => $verification->license_type,
                        'license_class' => $verification->license_class,
                        'expires_at' => $verification->expires_at
                            ->toDateString(),
                        'status' => $verification->status,
                        'is_current' => $verification->is_current,
                        'submitted_at' => $verification->submitted_at,
                        'reviewed_at' => $verification->reviewed_at,
                    ])
                    ->values(),
                'pagination' => [
                    'current_page' => $applications->currentPage(),
                    'last_page' => $applications->lastPage(),
                    'per_page' => $applications->perPage(),
                    'total' => $applications->total(),
                ],
            ],
        ]);
    }

    public function showMyStatus(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        $currentLicenses = RunnerLicenseVerification::query()
            ->where('account_id', $account->id)
            ->where('is_current', true)
            ->where(
                'status',
                RunnerLicenseVerification::STATUS_VERIFIED
            )
            ->orderByDesc('submitted_at')
            ->get();

        $pendingApplications = RunnerLicenseVerification::query()
            ->where('account_id', $account->id)
            ->where(
                'status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->orderByDesc('submitted_at')
            ->get();

        $latestApplications = RunnerLicenseVerification::query()
            ->where('account_id', $account->id)
            ->orderByDesc('submitted_at')
            ->get();

        $currentLicense = $currentLicenses->first();
        $pendingApplication = $pendingApplications->first();
        $latestApplication = $latestApplications->first();

        $displayedApplication = $pendingApplication
            ?? $currentLicense
            ?? $latestApplication;

        return response()->json([
            'success' => true,
            'data' => [
                'runner_license_verification' => $displayedApplication === null
                    ? $this->emptyStatusPayload()
                    : $this->statusPayload($displayedApplication),

                'current_license' => $currentLicense === null
                    ? null
                    : $this->statusPayload($currentLicense),

                'pending_application' => $pendingApplication === null
                    ? null
                    : $this->statusPayload($pendingApplication),

                'current_licenses' => $currentLicenses
                    ->map(fn (
                        RunnerLicenseVerification $verification
                    ): array => $this->statusPayload($verification))
                    ->values(),

                'pending_applications' => $pendingApplications
                    ->map(fn (
                        RunnerLicenseVerification $verification
                    ): array => $this->statusPayload($verification))
                    ->values(),

                'latest_applications' => $latestApplications
                    ->map(fn (
                        RunnerLicenseVerification $verification
                    ): array => $this->statusPayload($verification))
                    ->values(),
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
            $validated['decision']
                === RunnerLicenseVerification::STATUS_REJECTED
            && ($rejectionReason === null || $rejectionReason === '')
        ) {
            return $this->errorResponse(
                'Sebab penolakan diperlukan.',
                422
            );
        }

        $actor = $request->attributes->get('account');

        try {
            $verification = DB::transaction(function () use (
                $accountId,
                $actor,
                $validated,
                $rejectionReason,
                $auditLogService
            ): RunnerLicenseVerification {
                $found = $this->findVerification($accountId, true);

                if ($found === null) {
                    throw new ModelNotFoundException(
                        'Permohonan pengesahan lesen tidak ditemui.'
                    );
                }

                $targetAccount = \App\Models\Account::query()
                    ->whereKey($found->account_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $targetAccount->runner_status
                    === \App\Models\Account::RUNNER_STATUS_PENDING
                ) {
                    throw new DomainException(
                        'Lesen bagi permohonan awal Runner mesti disemak melalui keputusan permohonan Runner.'
                    );
                }

                $verification = RunnerLicenseVerification::query()
                    ->whereKey($found->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    $verification === null
                    || $verification->account_id !== $targetAccount->id
                ) {
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
                    && $verification->expires_at
                        ->lt(now()->startOfDay())
                ) {
                    throw new DomainException(
                        'Lesen telah tamat tempoh dan tidak boleh disahkan.'
                    );
                }

                if (
                    $validated['decision']
                    === RunnerLicenseVerification::STATUS_VERIFIED
                ) {
                    $verification->is_current = true;
                } else {
                    $verification->is_current = false;
                }

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
                        'id' => $verification->id,
                        'account_id' => $verification->account_id,
                        'license_type' => $verification->license_type,
                        'license_class' => $verification->license_class,
                        'status' => $verification->status,
                        'is_current' => $verification->is_current,
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
        string $identifier,
        bool $pendingFirst = false
    ): ?RunnerLicenseVerification {
        $byId = RunnerLicenseVerification::query()
            ->with('account')
            ->whereKey($identifier)
            ->first();

        if ($byId !== null) {
            return $byId;
        }

        $query = RunnerLicenseVerification::query()
            ->with('account')
            ->where('account_id', $identifier);

        if ($pendingFirst) {
            $query->orderByRaw(
                'CASE WHEN status = ? THEN 0 ELSE 1 END',
                [RunnerLicenseVerification::STATUS_PENDING]
            );
        } else {
            $query->orderByRaw(
                'CASE WHEN status = ? THEN 0 WHEN is_current = 1 THEN 1 ELSE 2 END',
                [RunnerLicenseVerification::STATUS_PENDING]
            );
        }

        return $query
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
