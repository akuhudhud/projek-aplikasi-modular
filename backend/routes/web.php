<?php

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\ContactChangeController;
use App\Http\Controllers\Api\CoreTaskController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\ProfilePictureController;
use App\Http\Controllers\Api\RunnerPublicProfileController;
use App\Http\Controllers\Api\RunnerTaskRatingController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\RegistrationController;
use App\Http\Controllers\Web\CustomerMarketplaceRequestController;
use App\Http\Controllers\Web\CustomerNotificationController;
use App\Http\Controllers\Web\CustomerTaskController;
use App\Http\Controllers\Web\RunnerNotificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ExpireMarketplaceItems;

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

/*
|--------------------------------------------------------------------------
| Runner PWA
|--------------------------------------------------------------------------
*/

Route::prefix('web/runner')
    ->name('web.runner.')
    ->middleware('web.auth')
    ->group(function (): void {
        Route::get('/tasks', [
            CoreTaskController::class,
            'runnerIndex',
        ])->name('tasks.index');

        Route::get('/tasks/history', [
            CoreTaskController::class,
            'runnerIndex',
        ])->defaults('view', 'history')
            ->name('tasks.history');

        Route::post('/tasks/{taskId}/start', [
            CoreTaskController::class,
            'start',
        ])->name('tasks.start');

        Route::post('/tasks/{taskId}/arrive', [
            CoreTaskController::class,
            'arrive',
        ])->name('tasks.arrive');

        Route::post('/tasks/{taskId}/complete', [
            CoreTaskController::class,
            'runnerComplete',
        ])->name('tasks.complete');

        Route::get('/notifications', [
            RunnerNotificationController::class,
            'index',
        ])->name('notifications.index');

        Route::patch('/notifications/{notificationId}/read', [
            RunnerNotificationController::class,
            'markAsRead',
        ])->name('notifications.read');

        Route::middleware(ExpireMarketplaceItems::class)
            ->prefix('requests')
            ->name('requests.')
            ->group(function (): void {
                Route::get('/', [
                    MarketplaceController::class,
                    'index',
                ])->name('index');

                Route::get('/{requestId}', [
                    MarketplaceController::class,
                    'show',
                ])->name('show');

                Route::post('/{requestId}/quotations', [
                    MarketplaceController::class,
                    'submitQuotation',
                ])->name('quotations.store');
            });

        Route::post('/profile', [
            RunnerPublicProfileController::class,
            'save',
        ])->name('profile.save');

        Route::get('/profile/analytics', [
            RunnerPublicProfileController::class,
            'analytics',
        ])->name('profile.analytics');
    });

/*
|--------------------------------------------------------------------------
| Customer PWA
|--------------------------------------------------------------------------
*/

Route::prefix('web/customer')
    ->name('web.customer.')
    ->middleware('web.auth')
    ->group(function (): void {
        Route::get('/tasks', [
            CustomerTaskController::class,
            'index',
        ])->name('tasks.index');

        Route::get('/tasks/{taskId}', [
            CustomerTaskController::class,
            'show',
        ])->name('tasks.show');

        Route::post(
            '/tasks/{taskId}/rating',
            [RunnerTaskRatingController::class, 'storeOrUpdate']
        )->name('tasks.rating.store');

        Route::patch(
            '/tasks/{taskId}/rating',
            [RunnerTaskRatingController::class, 'storeOrUpdate']
        )->name('tasks.rating.update');

        Route::get('/requests', [
            CustomerMarketplaceRequestController::class,
            'index',
        ])->name('requests.index');

        Route::post('/requests', [
            CustomerMarketplaceRequestController::class,
            'store',
        ])->name('requests.store');

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
