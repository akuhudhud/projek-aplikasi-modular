<?php

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\ContactChangeController;
use App\Http\Controllers\Api\ProfilePictureController;
use App\Http\Controllers\Api\RunnerTaskRatingController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\RegistrationController;
use App\Http\Controllers\Web\CustomerMarketplaceRequestController;
use App\Http\Controllers\Web\CustomerNotificationController;
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
            ->middleware([
                'throttle:registration-complete-ip',
                'throttle:registration-complete-token',
            ])
            ->name('register.complete');

        Route::middleware('web.auth')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])
                ->name('me');

            Route::patch('/profile', [AuthController::class, 'updateProfile'])
                ->name('profile.update');

            Route::post(
                '/profile-picture',
                [ProfilePictureController::class, 'update']
            )->name('profile-picture.update');

            Route::post(
                '/change-password',
                [ApiAuthController::class, 'changePassword']
            )->name('change-password');

            Route::post(
                '/change-phone/request',
                [ContactChangeController::class, 'requestPhone']
            )->name('change-phone.request');

            Route::post(
                '/change-phone/verify',
                [ContactChangeController::class, 'verifyPhone']
            )->name('change-phone.verify');

            Route::post(
                '/change-phone/complete',
                [ContactChangeController::class, 'completePhone']
            )->name('change-phone.complete');

            Route::post(
                '/change-email/request',
                [ContactChangeController::class, 'requestEmail']
            )->name('change-email.request');

            Route::post(
                '/change-email/verify',
                [ContactChangeController::class, 'verifyEmail']
            )->name('change-email.verify');

            Route::post(
                '/change-email/complete',
                [ContactChangeController::class, 'completeEmail']
            )->name('change-email.complete');

            Route::post(
                '/deactivate',
                [ApiAuthController::class, 'deactivate']
            )->name('deactivate');

            Route::post(
                '/delete',
                [ApiAuthController::class, 'delete']
            )->name('delete');

            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('logout');
        });
    });

Route::prefix('web/customer')
    ->name('web.customer.')
    ->middleware('web.auth')
    ->group(function (): void {
        // Customer tasks.
        Route::get('/tasks', [CustomerTaskController::class, 'index'])
            ->name('tasks.index');

        Route::get('/tasks/{taskId}', [CustomerTaskController::class, 'show'])
            ->name('tasks.show');

        Route::post(
            '/tasks/{taskId}/rating',
            [RunnerTaskRatingController::class, 'storeOrUpdate']
        )->name('tasks.rating.store');

        Route::patch(
            '/tasks/{taskId}/rating',
            [RunnerTaskRatingController::class, 'storeOrUpdate']
        )->name('tasks.rating.update');

        // Customer marketplace requests.
        Route::get('/requests', [CustomerMarketplaceRequestController::class, 'index'])
            ->name('requests.index');

        Route::post('/requests', [CustomerMarketplaceRequestController::class, 'store'])
            ->name('requests.store');

        Route::get(
            '/requests/{requestId}',
            [CustomerMarketplaceRequestController::class, 'show']
        )->name('requests.show');

        Route::post(
            '/requests/{requestId}/award',
            [CustomerMarketplaceRequestController::class, 'awardQuotation']
        )->name('requests.award');

        Route::post(
            '/requests/{requestId}/cancel',
            [CustomerMarketplaceRequestController::class, 'cancel']
        )->name('requests.cancel');

        // Customer notifications.
        Route::get(
            '/notifications',
            [CustomerNotificationController::class, 'index']
        )->name('notifications.index');

        Route::patch(
            '/notifications/{notificationId}/read',
            [CustomerNotificationController::class, 'markAsRead']
        )->name('notifications.read');

        Route::delete(
            '/notifications/{notificationId}',
            [CustomerNotificationController::class, 'destroy']
        )->name('notifications.destroy');

        Route::delete(
            '/notifications',
            [CustomerNotificationController::class, 'destroyAll']
        )->name('notifications.destroy-all');
    });
