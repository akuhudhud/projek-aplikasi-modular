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
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                        'previous_license_verification_id' =>
                            $verification->previous_license_verification_id,
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
        /** @var Account $account */
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

    public function submit(
        Request $request,
        RunnerLicenseVerificationService $service,
        AuditLogService $auditLogService
    ): JsonResponse {
        $validated = $request->validate([
            'license_number' => ['required', 'string', 'max:100'],
            'license_type' => [
                'required',
                'string',
                'max:30',
            ],
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

        if (
            $account->runner_status
            !== Account::RUNNER_STATUS_APPROVED
        ) {
            return $this->errorResponse(
                'Lesen tambahan hanya boleh dihantar selepas permohonan Runner diluluskan.',
                422
            );
        }

        try {
            $verification = $service->submit(
                $account,
                $validated['license_number'],
                $validated['license_class'],
                $validated['expires_at'],
                $validated['document'],
                $validated['license_type'],
                function (
                    RunnerLicenseVerification $verification,
                    Account $lockedAccount
                ) use ($auditLogService): void {
                    $auditLogService->record(
                        $lockedAccount,
                        $lockedAccount,
                        'RUNNER_LICENSE_VERIFICATION_SUBMITTED',
                        'Driving license verification submitted.'
                    );
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Pengesahan lesen memandu berjaya dihantar.',
                'data' => [
                    'runner_license_verification' => [
                        'id' => $verification->id,
                        'status' => $verification->status,
                        'is_current' => $verification->is_current,
                        'license_type' => $verification->license_type,
                        'license_class' => $verification->license_class,
                        'previous_license_verification_id' =>
                            $verification->previous_license_verification_id,
                        'expires_at' => $verification->expires_at
                            ->toDateString(),
                        'submitted_at' => $verification->submitted_at,
                    ],
                ],
            ], 201);
        } catch (
            DomainException | InvalidArgumentException $exception
        ) {
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
        $verification = $this->findVerification($accountId);

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
                    'id' => $verification->id,
                    'account_id' => $verification->account_id,
                    'display_name' => $verification->account?->name,
                    'license_number_masked' => str_repeat(
                        '*',
                        max(0, strlen($licenseNumber) - 4)
                    ).substr($licenseNumber, -4),
                    'license_type' => $verification->license_type,
                    'license_class' => $verification->license_class,
                    'previous_license_verification_id' =>
                        $verification->previous_license_verification_id,
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
                /*
                 * Resolve the identifier first. It may be a verification
                 * ID or an account ID. This initial read is not locked.
                 */
                $found = $this->findVerification($accountId, true);

                if ($found === null) {
                    throw new ModelNotFoundException(
                        'Permohonan pengesahan lesen tidak ditemui.'
                    );
                }

                /*
                 * Lock the account first. Submission and review operations
                 * for this account must follow the same lock order.
                 */
                $targetAccount = Account::query()
                    ->whereKey($found->account_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Re-read and lock the exact verification after acquiring
                 * the account lock, then validate its current ownership
                 * and status again.
                 */
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
                    RunnerLicenseVerification::query()
                        ->where(
                            'account_id',
                            $verification->account_id
                        )
                        ->where(
                            'license_type',
                            $verification->license_type
                        )
                        ->where(
                            'license_class',
                            $verification->license_class
                        )
                        ->where('is_current', true)
                        ->where(
                            'status',
                            RunnerLicenseVerification::STATUS_VERIFIED
                        )
                        ->where('id', '!=', $verification->id)
                        ->lockForUpdate()
                        ->update(['is_current' => false]);

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
                        'previous_license_verification_id' =>
                            $verification->previous_license_verification_id,
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
        string $accountId
    ): StreamedResponse|JsonResponse {
        $verification = $this->findVerification($accountId);

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
            'runner-license-'.Str::uuid().'.'.$extension,
            [
                'Content-Type' => $verification->mime_type,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
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
            'previous_license_verification_id' =>
                $verification->previous_license_verification_id,
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
            'previous_license_verification_id' => null,
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
