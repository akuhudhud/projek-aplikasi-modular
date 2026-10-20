<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\RunnerStatusService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RunnerController extends Controller
{
    public function __construct(
        private RunnerStatusService $runnerStatusService
    ) {
    }

    public function showMyApplication(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->attributes->get('account');

        return response()->json([
            'success' => true,
            'data' => [
                'runner_application' => [
                    'account_id' => $account->id,
                    'runner_status' => $account->runner_status,
                ],
            ],
        ], 200);
    }

    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_number' => [
                'required',
                'string',
                'max:100',
            ],
            'license_type' => [
                'sometimes',
                'required',
                'string',
                'max:30',
            ],
            'license_class' => [
                'required',
                'string',
                'max:30',
            ],
            'expires_at' => [
                'required',
                'date_format:Y-m-d',
            ],
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
            $updatedAccount = $this->runnerStatusService->apply(
                $account,
                $validated['license_number'],
                $validated['license_class'],
                $validated['expires_at'],
                $validated['document'],
                $validated['license_type'] ?? null
            );

            $license = \App\Models\RunnerLicenseVerification::query()
                ->where('account_id', $updatedAccount->id)
                ->where(
                    'status',
                    \App\Models\RunnerLicenseVerification::STATUS_PENDING
                )
                ->orderByDesc('submitted_at')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan Runner dan dokumen lesen berjaya dihantar untuk semakan.',
                'data' => [
                    'runner_application' => [
                        'account_id' => $updatedAccount->id,
                        'runner_status' => $updatedAccount->runner_status,
                    ],
                    'runner_license_verification' => [
                        'id' => $license?->id,
                        'status' => $license?->status,
                        'license_class' => $license?->license_class,
                        'expires_at' => $license?->expires_at?->toDateString(),
                    ],
                ],
            ], 201);
        } catch (AuthorizationException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                403
            );
        } catch (DomainException | InvalidArgumentException $exception) {
            $message = $exception->getMessage();

            if (
                $message
                === 'Identity verification is required before applying to become a Runner.'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sila sahkan identiti dahulu sebelum memohon menjadi Runner.',
                    'data' => [
                        'next_action' => [
                            'type' => 'IDENTITY_VERIFICATION',
                            'method' => 'GET',
                            'endpoint' => '/api/me/identity-verification',
                            'submit_method' => 'POST',
                            'submit_endpoint' => '/api/me/identity-verification',
                        ],
                    ],
                ], 422);
            }

            return $this->errorResponse($message, 422);
        } catch (ModelNotFoundException $exception) {
            return $this->errorResponse(
                'Account not found.',
                404
            );
        }
    }

    public function pendingApplications(Request $request): JsonResponse
    {
        /** @var Account $admin */
        $admin = $request->attributes->get('account');

        try {
            $applications = $this->runnerStatusService
                ->pendingApplications($admin);

            return response()->json([
                'success' => true,
                'data' => [
                    'applications' => $applications,
                ],
            ], 200);
        } catch (AuthorizationException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                403
            );
        }
    }

    public function approve(
        Request $request,
        string $accountId
    ): JsonResponse {
        return $this->changeStatus(
            $request,
            $accountId,
            Account::RUNNER_STATUS_APPROVED,
            'Runner application approved successfully.'
        );
    }

    public function reject(
        Request $request,
        string $accountId
    ): JsonResponse {
        return $this->changeStatus(
            $request,
            $accountId,
            Account::RUNNER_STATUS_REJECTED,
            'Runner application rejected successfully.'
        );
    }

    public function suspend(
        Request $request,
        string $accountId
    ): JsonResponse {
        return $this->changeStatus(
            $request,
            $accountId,
            Account::RUNNER_STATUS_SUSPENDED,
            'Runner eligibility suspended successfully.'
        );
    }

    public function restore(
        Request $request,
        string $accountId
    ): JsonResponse {
        return $this->changeStatus(
            $request,
            $accountId,
            Account::RUNNER_STATUS_APPROVED,
            'Runner eligibility restored successfully.'
        );
    }

    private function changeStatus(
        Request $request,
        string $accountId,
        string $nextStatus,
        string $successMessage
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        /** @var Account $admin */
        $admin = $request->attributes->get('account');

        try {
            $updatedAccount = $this->runnerStatusService->changeStatus(
                $admin,
                $accountId,
                $nextStatus,
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'data' => [
                    'account' => [
                        'id' => $updatedAccount->id,
                        'name' => $updatedAccount->name,
                        'role' => $updatedAccount->role,
                        'status' => $updatedAccount->status,
                        'runner_status' => $updatedAccount->runner_status,
                    ],
                ],
            ], 200);
        } catch (AuthorizationException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                403
            );
        } catch (DomainException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                422
            );
        } catch (ModelNotFoundException $exception) {
            return $this->errorResponse(
                'Account not found.',
                404
            );
        }
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
