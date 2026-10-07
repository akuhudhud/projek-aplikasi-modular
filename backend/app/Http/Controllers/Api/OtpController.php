<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\RegistrationVerification;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function request(
        RequestOtpRequest $request,
        OtpService $otpService
    ): JsonResponse {
        $account = $request->attributes->get('account');

        $accountId = null;

        if (in_array($request->purpose, [
            'CHANGE_PHONE',
            'CHANGE_EMAIL',
        ], true)) {
            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $accountId = $account->id;
        }

        $verification = $otpService->request(
            $request->channel,
            $request->contact,
            $request->purpose,
            $accountId
        );

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'data' => [
                'verification_id' => $verification->id,
                'expires_at' => $verification->expires_at,
            ],
        ], 200);
    }

    public function verify(
        VerifyOtpRequest $request,
        OtpService $otpService
    ): JsonResponse {
        $verification = RegistrationVerification::find(
            $request->verification_id
        );

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Verification request not found.',
            ], 404);
        }

        if (!$otpService->verify(
            $verification,
            $request->otp
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        $verificationToken = $otpService->issueVerificationToken(
            $verification
        );

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'data' => [
                'verification_token' => $verificationToken,
                'expires_at' => $verification->token_expires_at,
            ],
        ], 200);
    }
}
