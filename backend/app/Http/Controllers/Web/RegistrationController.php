
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateWebSession;
use App\Http\Requests\Auth\CompleteRegistrationRequest;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\Account;
use App\Models\RegistrationVerification;
use App\Services\Otp\OtpService;
use App\Services\Session\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly SessionService $sessionService
    ) {
    }

    public function requestOtp(
        RequestOtpRequest $request
    ): JsonResponse {
        if ($request->purpose !== 'REGISTRATION') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP purpose.',
            ], 422);
        }

        $contactField = $request->channel === 'phone'
            ? 'phone'
            : 'email';

        $exists = Account::query()
            ->where($contactField, $request->contact)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'An account already exists for this contact.',
            ], 422);
        }

        try {
            $verification = $this->otpService->request(
                $request->channel,
                $request->contact,
                'REGISTRATION'
            );
        } catch (\App\Exceptions\OtpResendException $exception) {
            $response = [
                'success' => false,
                'message' => $exception->getMessage(),
            ];

            if ($exception->retryAfterSeconds > 0) {
                $response['retry_after_seconds'] =
                    $exception->retryAfterSeconds;
            }

            return response()->json($response, 429);
        } catch (\App\Exceptions\OtpDeliveryException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'OTP tidak dapat dihantar buat masa ini. Sila cuba semula.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'data' => [
                'verification_id' => $verification->id,
                'expires_at' => $verification->expires_at,
            ],
        ], 200);
    }

    public function verifyOtp(
        VerifyOtpRequest $request
    ): JsonResponse {
        $verification = RegistrationVerification::query()
            ->find($request->verification_id);

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Verification request not found.',
            ], 404);
        }

        if (
            $verification->purpose !== 'REGISTRATION'
            || $verification->account_id !== null
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid registration verification request.',
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
                'expires_at' => $verification->fresh()->token_expires_at,
            ],
        ], 200);
    }

    public function complete(
        CompleteRegistrationRequest $request
    ): JsonResponse {
        try {
            return DB::transaction(function () use ($request) {
                $verification = $this->otpService
                    ->consumeVerificationToken(
                        $request->verification_token,
                        'REGISTRATION'
                    );

                $contactField = $verification->channel === 'phone'
                    ? 'phone'
                    : 'email';

                $exists = Account::query()
                    ->where($contactField, $verification->contact)
                    ->exists();

                if ($exists) {
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

                $request->session()->regenerate();

                $request->session()->put(
                    AuthenticateWebSession::SESSION_TOKEN_KEY,
                    $sessionData['token']
                );

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
}
