<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Services\AuditLogService;
use App\Services\Identity\IdentityVerificationSubmissionService;
use DomainException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class IdentityVerificationController extends Controller
{
    private const ENCRYPTED_FILE_PREFIX = 'SRIDENC1:';

    public function submit(
        Request $request,
        IdentityVerificationSubmissionService $submissionService
    ): JsonResponse {
        $validated = $request->validate([
            'real_name' => ['required', 'string', 'max:255'],
            'mykad_number' => ['required', 'string', 'regex:/^\d{12}$/'],
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:10240',
            ],
        ]);

        /** @var Account $account */
        $account = $request->attributes->get('account');

        try {
            $verification = $submissionService->submit(
                $account,
                $validated['real_name'],
                $validated['mykad_number'],
                $validated['documents']
            );

            return response()->json([
                'success' => true,
                'message' => 'Identity verification submitted successfully.',
                'data' => [
                    'identity_verification' => [
                        'status' => $verification->status,
                        'submitted_at' => $verification->submitted_at,
                    ],
                ],
            ], 201);
        } catch (DomainException | InvalidArgumentException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                422
            );
        }
    }

    public function showMyStatus(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->attributes->get('account');

        $verification = IdentityVerification::query()
            ->where('account_id', $account->id)
            ->first();

        if ($verification === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'identity_verification' => [
                        'status' => IdentityVerification::STATUS_NOT_SUBMITTED,
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
                'identity_verification' => [
                    'status' => $verification->status,
                    'submitted_at' => $verification->submitted_at,
                    'reviewed_at' => $verification->reviewed_at,
                    'rejection_reason' =>
                        $verification->status === IdentityVerification::STATUS_REJECTED
                            ? $verification->rejection_reason
                            : null,
                ],
            ],
        ]);
    }

    public function showForReview(string $accountId): JsonResponse
    {
        $verification = IdentityVerification::query()
            ->with(['account', 'documents'])
            ->where('account_id', $accountId)
            ->first();

        if ($verification === null) {
            return $this->errorResponse(
                'Identity verification application not found.',
                404
            );
        }

        $mykadNumber = $verification->decryptMykadNumber();

        return response()->json([
            'success' => true,
            'data' => [
                'identity_verification' => [
                    'account_id' => $verification->account_id,
                    'display_name' => $verification->account->name,
                    'real_name' => $verification->real_name,
                    'status' => $verification->status,
                    'mykad_number_masked' => str_repeat('*', 8)
                        . substr($mykadNumber, -4),
                    'submitted_at' => $verification->submitted_at,
                    'reviewed_at' => $verification->reviewed_at,
                    'rejection_reason' =>
                        $verification->status === IdentityVerification::STATUS_REJECTED
                            ? $verification->rejection_reason
                            : null,
                    'documents' => $verification->documents
                        ->map(fn (IdentityVerificationDocument $document): array => [
                            'id' => $document->id,
                            'mime_type' => $document->mime_type,
                            'size_bytes' => $document->size_bytes,
                            'created_at' => $document->created_at,
                        ])
                        ->values(),
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
            $validated['decision'] === IdentityVerification::STATUS_REJECTED
            && ($rejectionReason === null || $rejectionReason === '')
        ) {
            return $this->errorResponse(
                'A brief rejection reason is required.',
                422
            );
        }

        /** @var Account $actor */
        $actor = $request->attributes->get('account');

        // Appointed Super Admin cannot decide their own identity application.
        // Root retains the owner-level exception.
        if (
            $actor->role === 'SUPER_ADMIN'
            && $actor->id === $accountId
        ) {
            return $this->errorResponse(
                'Administrators cannot review their own identity verification.',
                403
            );
        }

        try {
            $verification = DB::transaction(function () use (
                $accountId,
                $actor,
                $validated,
                $rejectionReason,
                $auditLogService
            ): IdentityVerification {
                /*
                 * Lock order is account first, then identity verification.
                 * RunnerStatusService uses the same order during approval.
                 */
                $targetAccount = Account::query()
                    ->whereKey($accountId)
                    ->lockForUpdate()
                    ->first();

                if ($targetAccount === null) {
                    throw new ModelNotFoundException(
                        'Identity verification application not found.'
                    );
                }

                $verification = IdentityVerification::query()
                    ->where('account_id', $targetAccount->id)
                    ->lockForUpdate()
                    ->first();

                if ($verification === null) {
                    throw new ModelNotFoundException(
                        'Identity verification application not found.'
                    );
                }

                if (
                    $verification->status !== IdentityVerification::STATUS_PENDING
                ) {
                    throw new DomainException(
                        'Only pending identity verification applications can be reviewed.'
                    );
                }

                /*
                 * Keep accounts.name as the user's Display Name.
                 * The verified Real Name remains in identity_verifications.real_name.
                 * Public profile logic controls whether the Real Name is exposed.
                 */
                $verification->status = $validated['decision'];
                $verification->reviewer_account_id = $actor->id;
                $verification->reviewed_at = now();
                $verification->rejection_reason =
                    $validated['decision'] === IdentityVerification::STATUS_REJECTED
                        ? $rejectionReason
                        : null;
                $verification->save();

                $auditAction = $validated['decision']
                    === IdentityVerification::STATUS_VERIFIED
                        ? 'IDENTITY_VERIFICATION_VERIFIED'
                        : 'IDENTITY_VERIFICATION_REJECTED';

                $auditReason = $validated['decision']
                    === IdentityVerification::STATUS_VERIFIED
                        ? 'Identity verification marked VERIFIED.'
                        : 'Identity verification marked REJECTED.';

                // Never put MyKad data, documents, or free-text reasons in audit logs.
                $auditLogService->record(
                    $actor,
                    $targetAccount,
                    $auditAction,
                    $auditReason
                );

                return $verification->fresh();
            });

            return response()->json([
                'success' => true,
                'message' => 'Identity verification decision saved.',
                'data' => [
                    'identity_verification' => [
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
                'Identity verification application not found.',
                404
            );
        }
    }

    public function downloadDocument(
        string $accountId,
        string $documentId
    ): Response|JsonResponse {
        $document = IdentityVerificationDocument::query()
            ->where('id', $documentId)
            ->whereHas('identityVerification', function ($query) use ($accountId): void {
                $query->where('account_id', $accountId);
            })
            ->first();

        if ($document === null) {
            return $this->errorResponse(
                'Identity document not found.',
                404
            );
        }

        $disk = Storage::disk($document->disk);

        if (!$disk->exists($document->path)) {
            return $this->errorResponse(
                'Identity document not found.',
                404
            );
        }

        $contents = $disk->get($document->path);

        if (!is_string($contents)) {
            return $this->errorResponse(
                'Identity document could not be retrieved.',
                500
            );
        }

        /*
         * New documents are encrypted at rest by
         * IdentityVerificationSubmissionService.
         *
         * Legacy documents without the prefix remain readable
         * until they are replaced or migrated.
         */
        if (str_starts_with($contents, self::ENCRYPTED_FILE_PREFIX)) {
            $encryptedPayload = substr(
                $contents,
                strlen(self::ENCRYPTED_FILE_PREFIX)
            );

            try {
                $contents = Crypt::decryptString($encryptedPayload);
            } catch (DecryptException $exception) {
                // Do not expose encrypted content or cryptographic details.
                report($exception);

                return $this->errorResponse(
                    'Identity document could not be retrieved.',
                    500
                );
            }
        }

        $extension = match ($document->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        $filename = 'identity-document-' . Str::uuid() . '.' . $extension;

        return response($contents, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
