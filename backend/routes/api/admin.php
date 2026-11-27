<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminAccountDirectoryController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminNotificationController;
use App\Http\Controllers\Api\AdminPushSubscriptionController;
use App\Http\Controllers\Api\AdminRunnerDirectoryController;
use App\Http\Controllers\Api\AdminSuperAdminAppointmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RunnerController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::get('/admin/dashboard', [
    AdminDashboardController::class,
    'index',
])->middleware(AuthenticateSession::class);

/*
|--------------------------------------------------------------------------
| Admin Operational Notifications
|--------------------------------------------------------------------------
*/

Route::get('/admin/notifications', [
    AdminNotificationController::class,
    'index',
])->middleware(AuthenticateSession::class);

Route::patch('/admin/notifications/read-all', [
    AdminNotificationController::class,
    'markAllAsRead',
])->middleware(AuthenticateSession::class);

Route::patch('/admin/notifications/{notificationId}/read', [
    AdminNotificationController::class,
    'markAsRead',
])->middleware(AuthenticateSession::class);

/*
|--------------------------------------------------------------------------
| Admin Web Push Subscriptions
|--------------------------------------------------------------------------
*/

Route::get('/admin/push-public-key', [
    AdminPushSubscriptionController::class,
    'publicKey',
])->middleware(AuthenticateSession::class);

Route::post('/admin/push-subscriptions', [
    AdminPushSubscriptionController::class,
    'store',
])->middleware(AuthenticateSession::class);

Route::delete('/admin/push-subscriptions', [
    AdminPushSubscriptionController::class,
    'destroy',
])->middleware(AuthenticateSession::class);

/*
|--------------------------------------------------------------------------
| Account Management
|--------------------------------------------------------------------------
*/

Route::get('/admin/account-directory', [
    AdminAccountDirectoryController::class,
    'index',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:accounts.view',
]);

Route::get('/admin/account-directory/{accountId}', [
    AdminAccountDirectoryController::class,
    'show',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:accounts.view',
]);

Route::get('/admin/accounts', [
    AccountController::class,
    'index',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:accounts.view',
]);

Route::post('/admin/accounts/{accountId}/appoint-super-admin', [
    AdminSuperAdminAppointmentController::class,
    'appoint',
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

/*
|--------------------------------------------------------------------------
| Audit Logs
|--------------------------------------------------------------------------
*/

Route::get('/admin/audit-logs', [
    AuditLogController::class,
    'index',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:audit_logs.view',
]);

/*
|--------------------------------------------------------------------------
| Runner Management
|--------------------------------------------------------------------------
*/

Route::get('/admin/runner-applications', [
    RunnerController::class,
    'pendingApplications',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runner_applications.view',
]);

Route::get('/admin/runners', [
    AdminRunnerDirectoryController::class,
    'index',
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
