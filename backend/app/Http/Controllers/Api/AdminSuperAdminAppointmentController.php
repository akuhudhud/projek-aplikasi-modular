<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSuperAdminAppointmentController extends Controller
{
    public function appoint(
        Request $request,
        string $accountId,
        AuditLogService $auditLogService
    ): JsonResponse {
        $actor = $request->attributes->get('account');

        if (!$actor || $actor->role !== 'ROOT_SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'job_title' => [
                'required',
                'string',
                'max:100',
            ],
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $jobTitle = trim($validated['job_title']);
        $reason = trim($validated['reason']);

        if ($jobTitle === '' || $reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Job title and reason are required.',
            ], 422);
        }

        $appointedAccount = DB::transaction(function () use (
            $accountId,
            $actor,
            $jobTitle,
            $reason,
            $auditLogService
        ): Account {
            $account = Account::query()
                ->whereKey($accountId)
                ->lockForUpdate()
                ->first();

            if (!$account) {
                abort(404, 'Account not found.');
            }

            if ($account->role !== 'USER') {
                abort(
                    422,
                    'Only existing USER accounts can be appointed as Super Admin.'
                );
            }

            if ($account->status !== 'ACTIVE') {
                abort(
                    422,
                    'Only active USER accounts can be appointed.'
                );
            }

            $account->role = 'SUPER_ADMIN';
            $account->job_title = $jobTitle;
            $account->save();

            $auditLogService->record(
                $actor,
                $account,
                'SUPER_ADMIN_APPOINTED',
                $reason
            );

            return $account;
        });

        return response()->json([
            'success' => true,
            'message' => 'Super Admin appointed successfully.',
            'data' => [
                'account' => [
                    'id' => $appointedAccount->id,
                    'profile_name' => $appointedAccount->profile_name,
                    'job_title' => $appointedAccount->job_title,
                    'phone' => $appointedAccount->phone,
                    'email' => $appointedAccount->email,
                    'profile_picture' => $appointedAccount->profile_picture,
                    'role' => $appointedAccount->role,
                    'status' => $appointedAccount->status,
                    'created_at' => $appointedAccount->created_at,
                    'updated_at' => $appointedAccount->updated_at,
                ],
            ],
        ], 200);
    }
}
