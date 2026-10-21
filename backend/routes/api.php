<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactChangeController;
use App\Http\Controllers\Api\CoreTaskController;
use App\Http\Controllers\Api\DrivingLicenseCatalogController;
use App\Http\Controllers\Api\DrivingLicenseTypeController;
use App\Http\Controllers\Api\IdentityVerificationController;
use App\Http\Controllers\Api\IdentityVerificationDeletionController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\MarketplaceLifecycleController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProfilePictureController;
use App\Http\Controllers\Api\RunnerController;
use App\Http\Controllers\Api\RunnerLicenseVerificationController;
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
| Identity Verification
|--------------------------------------------------------------------------
*/

Route::post('/me/identity-verification', [
    IdentityVerificationController::class,
    'submit',
])->middleware(AuthenticateSession::class);

Route::get('/me/identity-verification', [
    IdentityVerificationController::class,
    'showMyStatus',
])->middleware(AuthenticateSession::class);

Route::get('/admin/identity-verifications/{accountId}', [
    IdentityVerificationController::class,
    'showForReview',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:identity_verifications.review',
]);

Route::post('/admin/identity-verifications/{accountId}/decision', [
    IdentityVerificationController::class,
    'decide',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:identity_verifications.review',
]);

Route::get(
    '/admin/identity-verifications/{accountId}/documents/{documentId}',
    [
        IdentityVerificationController::class,
        'downloadDocument',
    ]
)->middleware([
    AuthenticateSession::class,
    'admin.permission:identity_verifications.review',
]);

Route::delete('/admin/identity-verifications/{accountId}/data', [
    IdentityVerificationDeletionController::class,
    'destroy',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:identity_verifications.delete_data',
]);

/*
|--------------------------------------------------------------------------
| Runner License Verification
|--------------------------------------------------------------------------
*/

Route::get('/me/runner-license-verification', [
    RunnerLicenseVerificationController::class,
    'showMyStatus',
])->middleware(AuthenticateSession::class);

Route::post('/me/runner-license-verification', [
    RunnerLicenseVerificationController::class,
    'submit',
])->middleware(AuthenticateSession::class);

Route::get('/admin/runner-license-verifications', [
    RunnerLicenseVerificationController::class,
    'index',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runner_license_verifications.review',
]);

Route::get('/admin/runner-license-verifications/{accountId}', [
    RunnerLicenseVerificationController::class,
    'showForReview',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runner_license_verifications.review',
]);

Route::post('/admin/runner-license-verifications/{accountId}/decision', [
    RunnerLicenseVerificationController::class,
    'decide',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runner_license_verifications.review',
]);

Route::get('/admin/runner-license-verifications/{accountId}/document', [
    RunnerLicenseVerificationController::class,
    'downloadDocument',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runner_license_verifications.review',
]);

/*
|--------------------------------------------------------------------------
| Driving License Types
|--------------------------------------------------------------------------
*/

Route::get('/driving-license-types', [
    DrivingLicenseTypeController::class,
    'index',
])->middleware(AuthenticateSession::class);

Route::get('/admin/driving-license-types', [
    DrivingLicenseTypeController::class,
    'adminIndex',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:driving_license_types.manage',
]);

Route::post('/admin/driving-license-types', [
    DrivingLicenseTypeController::class,
    'store',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:driving_license_types.manage',
]);

Route::patch('/admin/driving-license-types/{id}', [
    DrivingLicenseTypeController::class,
    'update',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:driving_license_types.manage',
]);

Route::delete('/admin/driving-license-types/{id}', [
    DrivingLicenseTypeController::class,
    'deactivate',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:driving_license_types.manage',
]);

/*
|--------------------------------------------------------------------------
| Driving License Catalog
|--------------------------------------------------------------------------
*/

Route::get('/driving-license-catalog', [
    DrivingLicenseCatalogController::class,
    'index',
])->middleware(AuthenticateSession::class);

Route::get('/admin/driving-license-catalog', [
    DrivingLicenseCatalogController::class,
    'adminIndex',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:driving_license_catalog.manage',
]);

Route::post('/admin/driving-license-catalog', [
    DrivingLicenseCatalogController::class,
    'store',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:driving_license_catalog.manage',
]);

Route::patch('/admin/driving-license-catalog/{id}', [
    DrivingLicenseCatalogController::class,
    'update',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:driving_license_catalog.manage',
]);

Route::delete('/admin/driving-license-catalog/{id}', [
    DrivingLicenseCatalogController::class,
    'deactivate',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:driving_license_catalog.manage',
]);

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

Route::get('/runners/{slug}', [
    RunnerPublicProfileController::class,
    'show',
]);

Route::post('/me/runner-profile', [
    RunnerPublicProfileController::class,
    'save',
])->middleware(AuthenticateSession::class);

Route::get('/me/runner-profile/analytics', [
    RunnerPublicProfileController::class,
    'analytics',
])->middleware(AuthenticateSession::class);

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::get('/admin/accounts', [
    AccountController::class,
    'index',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:accounts.view',
]);

Route::post('/admin/accounts', [
    AccountController::class,
    'createSuperAdmin',
])->middleware(AuthenticateSession::class);

Route::patch('/admin/accounts/{accountId}/job-title', [
    AccountController::class,
    'updateSuperAdminJobTitle',
])->middleware(AuthenticateSession::class);

Route::get('/admin/accounts/{accountId}/permissions', [
    AccountController::class,
    'showSuperAdminPermissions',
])->middleware(AuthenticateSession::class);

Route::put('/admin/accounts/{accountId}/permissions', [
    AccountController::class,
    'updateSuperAdminPermissions',
])->middleware(AuthenticateSession::class);

Route::post('/admin/accounts/{accountId}/reactivate', [
    AuthController::class,
    'reactivate',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:accounts.reactivate',
]);

Route::post('/admin/accounts/{accountId}/suspend', [
    AuthController::class,
    'suspend',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:accounts.suspend',
]);

Route::post('/admin/accounts/{accountId}/unsuspend', [
    AuthController::class,
    'unsuspend',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:accounts.unsuspend',
]);

Route::get('/admin/audit-logs', [
    AuditLogController::class,
    'index',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:audit_logs.view',
]);

/*
|--------------------------------------------------------------------------
| Core Runner Administration
|--------------------------------------------------------------------------
*/

Route::get('/admin/runner-applications', [
    RunnerController::class,
    'pendingApplications',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runner_applications.view',
]);

Route::post('/admin/runner-applications/{accountId}/approve', [
    RunnerController::class,
    'approve',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runner_applications.approve',
]);

Route::post('/admin/runner-applications/{accountId}/reject', [
    RunnerController::class,
    'reject',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runner_applications.reject',
]);

Route::post('/admin/runners/{accountId}/suspend', [
    RunnerController::class,
    'suspend',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runners.suspend',
]);

Route::post('/admin/runners/{accountId}/restore', [
    RunnerController::class,
    'restore',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runners.restore',
]);
