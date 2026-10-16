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

class RunnerController extends Controller
{
    public function __construct(
        private RunnerStatusService $runnerStatusService
    ) {
    }

    /**
     * Return the authenticated account's Runner application status.
     */
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

    /**
     * Submit a Runner application for the authenticated account.
     */
    public function apply(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->attributes->get('account');

        try {
            $updatedAccount = $this->runnerStatusService->apply($account);

            return response()->json([
                'success' => true,
                'message' => 'Runner application submitted successfully.',
                'data' => [
                    'runner_application' => [
                        'account_id' => $updatedAccount->id,
                        'runner_status' => $updatedAccount->runner_status,
                    ],
                ],
            ], 201);
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

    /**
     * List pending Runner applications visible to the administrator.
     */
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
