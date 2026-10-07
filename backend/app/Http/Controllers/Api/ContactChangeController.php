<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Account\ChangeEmailRequest;
use App\Http\Requests\Account\ChangePhoneRequest;
use App\Http\Requests\Account\CompleteContactChangeRequest;
use App\Models\RegistrationVerification;
use App\Services\Contact\ContactChangeService;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ContactChangeController extends Controller
{
    public function __construct(
        private readonly ContactChangeService $contactChangeService,
        private readonly OtpService $otpService
    ) {
    }

    public function requestPhone(
        ChangePhoneRequest $request
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $verification = $this->contactChangeService->request(
                $account,
                'phone',
                $request->phone
            );

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully.',
                'data' => [
                    'verification_id' => $verification->id,
                    'expires_at' => $verification->expires_at,
                ],
            ], 200);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function verifyPhone(
        VerifyOtpRequest $request
    ): JsonResponse {
        return $this->verify(
            $request,
            'phone',
            'CHANGE_PHONE'
        );
    }

    public function completePhone(
        CompleteContactChangeRequest $request
    ): JsonResponse {
        return $this->complete(
            $request,
            'phone'
        );
    }

    public function requestEmail(
        ChangeEmailRequest $request
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $verification = $this->contactChangeService->request(
                $account,
                'email',
                $request->email
            );

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully.',
                'data' => [
                    'verification_id' => $verification->id,
                    'expires_at' => $verification->expires_at,
                ],
            ], 200);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function verifyEmail(
        VerifyOtpRequest $request
    ): JsonResponse {
        return $this->verify(
            $request,
            'email',
            'CHANGE_EMAIL'
        );
    }

    public function completeEmail(
        CompleteContactChangeRequest $request
    ): JsonResponse {
        return $this->complete(
            $request,
            'email'
        );
    }

    private function verify(
        VerifyOtpRequest $request,
        string $channel,
        string $purpose
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $verification = RegistrationVerification::find(
            $request->verification_id
        );

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Verification request not found.',
            ], 404);
        }

        if ($verification->account_id !== $account->id) {
            return response()->json([
                'success' => false,
                'message' => 'Verification request does not belong to the authenticated account.',
            ], 403);
        }

        if ($verification->channel !== $channel) {
            return response()->json([
                'success' => false,
                'message' => 'Verification channel does not match.',
            ], 422);
        }

        if ($verification->purpose !== $purpose) {
            return response()->json([
                'success' => false,
                'message' => 'Verification purpose does not match.',
            ], 422);
        }

        if (!$this->otpService->verify(
            $verification,
            $request->otp
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        $verificationToken = $this->otpService
            ->issueVerificationToken($verification);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'data' => [
                'verification_token' => $verificationToken,
                'expires_at' => $verification->token_expires_at,
            ],
        ], 200);
    }

    private function complete(
        CompleteContactChangeRequest $request,
        string $channel
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $account = $this->contactChangeService->complete(
                $account,
                $channel,
                $request->verification_token
            );

            return response()->json([
                'success' => true,
                'message' => 'Contact information updated successfully.',
                'data' => [
                    'account' => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'phone' => $account->phone,
                        'email' => $account->email,
                        'phone_verified_at' => $account->phone_verified_at,
                        'email_verified_at' => $account->email_verified_at,
                        'profile_picture' => $account->profile_picture,
                        'role' => $account->role,
                        'status' => $account->status,
                    ],
                ],
            ], 200);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
