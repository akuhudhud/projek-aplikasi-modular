<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactChangeController;
use App\Http\Controllers\Api\CoreTaskController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\MarketplaceLifecycleController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProfilePictureController;
use App\Http\Controllers\Api\RunnerController;
use App\Http\Controllers\Api\RunnerPublicProfileController;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\ExpireMarketplaceItems;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/register/complete', [AuthController::class, 'completeRegistration']);
Route::post('/login', [AuthController::class, 'login']);

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

/*
|--------------------------------------------------------------------------
| Core Runner Application
|--------------------------------------------------------------------------
*/

Route::get('/me/runner-application', [
    RunnerController::class,
    'showMyApplication',
])->middleware(AuthenticateSession::class);

Route::post('/me/runner-application', [
    RunnerController::class,
    'apply',
])->middleware(AuthenticateSession::class);

/*
|--------------------------------------------------------------------------
| Core Tasks
|--------------------------------------------------------------------------
*/

Route::get('/core/tasks', [CoreTaskController::class, 'index'])
    ->middleware(AuthenticateSession::class);

Route::post('/core/tasks/{taskId}/start', [
    CoreTaskController::class,
    'start',
])->middleware(AuthenticateSession::class);

Route::post('/core/tasks/{taskId}/complete', [
    CoreTaskController::class,
    'complete',
])->middleware(AuthenticateSession::class);

/*
|--------------------------------------------------------------------------
| Marketplace
|--------------------------------------------------------------------------
*/

Route::middleware([
    AuthenticateSession::class,
    ExpireMarketplaceItems::class,
])
    ->prefix('marketplace')
    ->group(function () {
        Route::get('/requests', [
            MarketplaceController::class,
            'index',
        ]);

        Route::post('/requests', [
            MarketplaceController::class,
            'store',
        ]);

        Route::get('/requests/{requestId}', [
            MarketplaceController::class,
            'show',
        ]);

        Route::post('/requests/{requestId}/quotations', [
            MarketplaceController::class,
            'submitQuotation',
        ]);

        Route::post('/requests/{requestId}/award', [
            MarketplaceController::class,
            'awardQuotation',
        ]);

        Route::post('/requests/{requestId}/cancel', [
            MarketplaceLifecycleController::class,
            'cancelRequest',
        ]);

        Route::post(
            '/requests/{requestId}/quotations/{quotationId}/withdraw',
            [
                MarketplaceLifecycleController::class,
                'withdrawQuotation',
            ]
        );
    });

/*
|--------------------------------------------------------------------------
| Runner Public Profiles
|--------------------------------------------------------------------------
*/

// Public profile: no login required.
Route::get('/runners/{slug}', [
    RunnerPublicProfileController::class,
    'show',
]);

// Manage own public profile: authenticated session required.
Route::post('/me/runner-profile', [
    RunnerPublicProfileController::class,
    'save',
])->middleware(AuthenticateSession::class);

// View analytics for the authenticated Runner's own public profile.
Route::get('/me/runner-profile/analytics', [
    RunnerPublicProfileController::class,
    'analytics',
])->middleware(AuthenticateSession::class);

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::get('/admin/accounts', [AccountController::class, 'index'])
    ->middleware(AuthenticateSession::class);

Route::post('/admin/accounts', [AccountController::class, 'createSuperAdmin'])
    ->middleware(AuthenticateSession::class);

Route::patch('/admin/accounts/{accountId}/job-title', [
    AccountController::class,
    'updateSuperAdminJobTitle',
])->middleware(AuthenticateSession::class);

Route::post('/admin/accounts/{accountId}/reactivate', [
    AuthController::class,
    'reactivate',
])->middleware(AuthenticateSession::class);

Route::post('/admin/accounts/{accountId}/suspend', [
    AuthController::class,
    'suspend',
])->middleware(AuthenticateSession::class);

Route::post('/admin/accounts/{accountId}/unsuspend', [
    AuthController::class,
    'unsuspend',
])->middleware(AuthenticateSession::class);

Route::get('/admin/audit-logs', [
    AuditLogController::class,
    'index',
])->middleware(AuthenticateSession::class);

/*
|--------------------------------------------------------------------------
| Core Runner Administration
|--------------------------------------------------------------------------
*/

Route::get('/admin/runner-applications', [
    RunnerController::class,
    'pendingApplications',
])->middleware(AuthenticateSession::class);

Route::post('/admin/runner-applications/{accountId}/approve', [
    RunnerController::class,
    'approve',
])->middleware(AuthenticateSession::class);

Route::post('/admin/runner-applications/{accountId}/reject', [
    RunnerController::class,
    'reject',
])->middleware(AuthenticateSession::class);

Route::post('/admin/runners/{accountId}/suspend', [
    RunnerController::class,
    'suspend',
])->middleware(AuthenticateSession::class);

Route::post('/admin/runners/{accountId}/restore', [
    RunnerController::class,
    'restore',
])->middleware(AuthenticateSession::class);
