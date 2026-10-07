<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\CompleteRegistrationRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Account;
use App\Services\AuditLogService;
use App\Services\Otp\OtpService;
use App\Services\Session\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Str;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        private SessionService $sessionService
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'OTP verification is required before registration can be completed.',
        ], 422);
    }

    public function completeRegistration(
        CompleteRegistrationRequest $request
    ): JsonResponse {
        try {
            return DB::transaction(function () use ($request) {
                $verification = app(OtpService::class)
                    ->consumeVerificationToken(
                        $request->verification_token,
                        'REGISTRATION'
                    );

                $contactField = $verification->channel === 'phone'
                    ? 'phone'
                    : 'email';

                $existingAccount = Account::query()
                    ->where($contactField, $verification->contact)
                    ->first();

                if ($existingAccount) {
                    throw new RuntimeException(
                        'An account already exists for this contact.'
                    );
                }

                $accountData = [
                    'id' => (string) Str::uuid(),
                    'name' => $request->name,
                    'password' => $request->password,
                    'role' => 'USER',
                    'status' => 'ACTIVE',
                ];

                if ($verification->channel === 'phone') {
                    $accountData['phone'] = $verification->contact;
                    $accountData['phone_verified_at'] = now();
                } else {
                    $accountData['email'] = $verification->contact;
                    $accountData['email_verified_at'] = now();
                }

                $account = Account::create($accountData);

                $sessionData = $this->sessionService
                    ->createForAccount($account);

                return response()->json([
                    'success' => true,
                    'message' => 'Account registered successfully.',
                    'data' => [
                        'account' => [
                            'id' => $account->id,
                            'name' => $account->name,
                            'phone' => $account->phone,
                            'email' => $account->email,
                            'profile_picture' => $account->profile_picture,
                            'role' => $account->role,
                            'status' => $account->status,
                        ],
                        'session' => [
                            'token' => $sessionData['token'],
                        ],
                    ],
                ], 201);
            });
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $account = Account::query()
            ->when(
                $request->filled('phone'),
                fn ($query) => $query->where('phone', $request->phone)
            )
            ->when(
                $request->filled('email'),
                fn ($query) => $query->where('email', $request->email)
            )
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($account->locked_until !== null) {
            if ($account->locked_until->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is temporarily locked.',
                ], 423);
            }

            $account->failed_login_attempts = 0;
            $account->locked_until = null;
            $account->save();
        }

        if (in_array($account->status, ['DEACTIVATED', 'DELETED'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not available for login.',
            ], 403);
        }

        if (!Hash::check($request->password, $account->password)) {
            $account->failed_login_attempts++;

            if ($account->failed_login_attempts >= 5) {
                $account->locked_until = now()->addMinutes(30);
            }

            $account->save();

            if ($account->locked_until !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is temporarily locked.',
                ], 423);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $account->failed_login_attempts = 0;
        $account->locked_until = null;
        $account->save();

        $sessionData = $this->sessionService
            ->replaceActiveSessionsAndCreate($account);

        $account->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'phone' => $account->phone,
                    'email' => $account->email,
                    'profile_picture' => $account->profile_picture,
                    'role' => $account->role,
                    'status' => $account->status,
                ],
                'session' => [
                    'token' => $sessionData['token'],
                ],
            ],
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        return response()->json([
            'success' => true,
            'data' => [
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'phone' => $account->phone,
                    'email' => $account->email,
                    'profile_picture' => $account->profile_picture,
                    'role' => $account->role,
                    'status' => $account->status,
                ],
            ],
        ], 200);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        $account->fill($request->only([
            'name',
        ]));

        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'phone' => $account->phone,
                    'email' => $account->email,
                    'profile_picture' => $account->profile_picture,
                    'role' => $account->role,
                    'status' => $account->status,
                ],
            ],
        ], 200);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!Hash::check($request->current_password, $account->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $account->password = $request->new_password;
        $account->save();

        $this->sessionService->endAllForAccount(
            $account,
            SessionService::END_REASON_PASSWORD_CHANGED
        );

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. Please login again.',
        ], 200);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        $account->status = 'DEACTIVATED';
        $account->save();

        $this->sessionService->endAllForAccount(
            $account,
            SessionService::END_REASON_ACCOUNT_DEACTIVATED
        );

        return response()->json([
            'success' => true,
            'message' => 'Account deactivated successfully.',
        ], 200);
    }

    public function reactivate(Request $request, string $accountId): JsonResponse
    {
        $admin = $request->attributes->get('account');

        $account = Account::find($accountId);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        if (!$this->canManageAccount($admin, $account)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($account->status === 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Account is already active.',
            ], 422);
        }

        if ($account->status !== 'DEACTIVATED') {
            return response()->json([
                'success' => false,
                'message' => 'Only deactivated accounts can be reactivated.',
            ], 422);
        }

        $reason = trim((string) $request->input('reason'));

        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Reason is required.',
            ], 422);
        }

        $account->status = 'ACTIVE';
        $account->save();

        app(AuditLogService::class)->record(
            $admin,
            $account,
            'ACCOUNT_REACTIVATED',
            $reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Account reactivated successfully.',
        ], 200);
    }

    public function suspend(Request $request, string $accountId): JsonResponse
    {
        $admin = $request->attributes->get('account');

        $account = Account::find($accountId);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        if (!$this->canManageAccount($admin, $account)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($account->status === 'SUSPENDED') {
            return response()->json([
                'success' => false,
                'message' => 'Account is already suspended.',
            ], 422);
        }

        if ($account->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Only active accounts can be suspended.',
            ], 422);
        }

        $reason = trim((string) $request->input('reason'));

        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Reason is required.',
            ], 422);
        }

        $account->status = 'SUSPENDED';
        $account->save();

        $this->sessionService->endAllForAccount(
            $account,
            SessionService::END_REASON_ACCOUNT_SUSPENDED
        );

        app(AuditLogService::class)->record(
            $admin,
            $account,
            'ACCOUNT_SUSPENDED',
            $reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Account suspended successfully.',
        ], 200);
    }

    public function unsuspend(Request $request, string $accountId): JsonResponse
    {
        $admin = $request->attributes->get('account');

        $account = Account::find($accountId);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        if (!$this->canManageAccount($admin, $account)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($account->status !== 'SUSPENDED') {
            return response()->json([
                'success' => false,
                'message' => 'Only suspended accounts can be unsuspended.',
            ], 422);
        }

        $reason = trim((string) $request->input('reason'));

        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Reason is required.',
            ], 422);
        }

        $account->status = 'ACTIVE';
        $account->save();

        app(AuditLogService::class)->record(
            $admin,
            $account,
            'ACCOUNT_UNSUSPENDED',
            $reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Account unsuspended successfully.',
        ], 200);
    }

    private function canManageAccount(?Account $admin, Account $target): bool
    {
        if (!$admin) {
            return false;
        }

        if ($admin->role === 'ROOT_SUPER_ADMIN') {
            return $target->role !== 'ROOT_SUPER_ADMIN';
        }

        if ($admin->role === 'SUPER_ADMIN') {
            return $target->role === 'USER';
        }

        return false;
    }

    public function delete(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        $account->status = 'DELETED';
        $account->save();

        $this->sessionService->endAllForAccount(
            $account,
            SessionService::END_REASON_ACCOUNT_DELETED
        );

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $session = $request->attributes->get('session');

        $this->sessionService->end(
            $session,
            SessionService::END_REASON_LOGOUT
        );

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ], 200);
    }
}
