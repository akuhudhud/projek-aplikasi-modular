<?php

use App\Http\Controllers\Api\IdentityVerificationController;
use App\Http\Controllers\Api\IdentityVerificationDeletionController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::post('/me/identity-verification', [
    IdentityVerificationController::class,
    'submit',
])->middleware(AuthenticateSession::class);

Route::get('/me/identity-verification', [
    IdentityVerificationController::class,
    'showMyStatus',
])->middleware(AuthenticateSession::class);

Route::get('/admin/identity-verifications', [
    IdentityVerificationController::class,
    'index',
])->middleware([
    AuthenticateSession::class,
    'admin.permission:identity_verifications.review',
]);

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
        'showDocument',
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
