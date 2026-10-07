<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactChangeController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProfilePictureController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/register/complete', [AuthController::class, 'completeRegistration']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/otp/request', [OtpController::class, 'request']);
Route::post('/otp/verify', [OtpController::class, 'verify']);

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

Route::get('/admin/accounts', [AccountController::class, 'index'])
    ->middleware(AuthenticateSession::class);

Route::post('/admin/accounts', [AccountController::class, 'createSuperAdmin'])
    ->middleware(AuthenticateSession::class);

Route::post('/admin/accounts/{accountId}/reactivate', [AuthController::class, 'reactivate'])
    ->middleware(AuthenticateSession::class);

Route::post('/admin/accounts/{accountId}/suspend', [AuthController::class, 'suspend'])
    ->middleware(AuthenticateSession::class);

Route::post('/admin/accounts/{accountId}/unsuspend', [AuthController::class, 'unsuspend'])
    ->middleware(AuthenticateSession::class);

Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])
    ->middleware(AuthenticateSession::class);
