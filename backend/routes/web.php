<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\RegistrationController;
use App\Http\Controllers\Web\CustomerMarketplaceRequestController;
use App\Http\Controllers\Web\CustomerTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('web/auth')
    ->name('web.auth.')
    ->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login-ip')
            ->name('login');

        Route::post('/register/otp/request', [RegistrationController::class, 'requestOtp'])
            ->middleware([
                'throttle:otp-request-ip',
                'throttle:otp-request-contact',
            ])
            ->name('register.otp.request');

        Route::post('/register/otp/verify', [RegistrationController::class, 'verifyOtp'])
            ->middleware([
                'throttle:otp-verify-ip',
                'throttle:otp-verify-id',
            ])
            ->name('register.otp.verify');

        Route::post('/register/complete', [RegistrationController::class, 'complete'])
            ->name('register.complete');

        Route::middleware('web.auth')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])
                ->name('me');

            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('logout');
        });
    });

Route::prefix('web/customer')
    ->name('web.customer.')
    ->middleware('web.auth')
    ->group(function (): void {
        Route::get('/tasks', [CustomerTaskController::class, 'index'])
            ->name('tasks.index');

        Route::get('/tasks/{taskId}', [CustomerTaskController::class, 'show'])
            ->name('tasks.show');

        Route::get('/requests', [CustomerMarketplaceRequestController::class, 'index'])
            ->name('requests.index');

        Route::post('/requests', [CustomerMarketplaceRequestController::class, 'store'])
            ->name('requests.store');
    });
