<?php

use App\Http\Controllers\Api\DrivingLicenseCatalogController;
use App\Http\Controllers\Api\DrivingLicenseTypeController;
use App\Http\Controllers\Api\RunnerController;
use App\Http\Controllers\Api\RunnerLicenseVerificationController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::get('/me/runner-license-verification', [
    RunnerLicenseVerificationController::class,
    'showMyStatus',
])->middleware(AuthenticateSession::class);

Route::get('/admin/runner-license-verifications/{accountId}', [
    RunnerLicenseVerificationController::class,
    'showForReview',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runner_applications.view',
]);

Route::get('/admin/runner-license-verifications/{accountId}/document', [
    RunnerLicenseVerificationController::class,
    'showDocument',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:runner_applications.view',
]);

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

Route::get('/me/runner-application', [
    RunnerController::class,
    'showMyApplication',
])->middleware(AuthenticateSession::class);

Route::post('/me/runner-application', [
    RunnerController::class,
    'apply',
])->middleware(AuthenticateSession::class);
