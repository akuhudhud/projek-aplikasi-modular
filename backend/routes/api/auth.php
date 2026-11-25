<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactChangeController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProfilePictureController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/register/complete', [AuthController::class, 'completeRegistration']);

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login-ip');

Route::post('/otp/request', [OtpController::class, 'request'])
    ->middleware([
        'throttle:otp-request-ip',
        'throttle:otp-request-contact',
    ]);

Route::post('/otp/verify', [OtpController::class, 'verify'])
    ->middleware([
        'throttle:otp-verify-ip',
        'throttle:otp-verify-id',
    ]);

Route::get('/me', [AuthController::class, 'me'])
    ->middleware(AuthenticateSession::class);

Route::patch('/me', [AuthController::class, 'updateProfile'])
    ->middleware(AuthenticateSession::class);

Route::post('/me/profile-picture', [ProfilePictureController::class, 'update'])
    ->middleware(AuthenticateSession::class);

Route::post('/me/change-password', [AuthController::class, 'changePassword'])
    ->middleware(AuthenticateSession::class);

Route::post('/me/change-phone/request', [ContactChangeController::class, 'requestPhone'])
    ->middleware(AuthenticateSession::class);

Route::post('/me/change-phone/verify', [ContactChangeController::class, 'verifyPhone'])
    ->middleware(AuthenticateSession::class);

Route::post('/me/change-phone/complete', [ContactChangeController::class, 'completePhone'])
    ->middleware(AuthenticateSession::class);

Route::post('/me/change-email/request', [ContactChangeController::class, 'requestEmail'])
    ->middleware(AuthenticateSession::class);

Route::post('/me/change-email/verify', [ContactChangeController::class, 'verifyEmail'])
    ->middleware(AuthenticateSession::class);

Route::post('/me/change-email/complete', [ContactChangeController::class, 'completeEmail'])
    ->middleware(AuthenticateSession::class);

Route::post('/me/deactivate', [AuthController::class, 'deactivate'])
    ->middleware(AuthenticateSession::class);

Route::post('/me/delete', [AuthController::class, 'delete'])
    ->middleware(AuthenticateSession::class);

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(AuthenticateSession::class);
