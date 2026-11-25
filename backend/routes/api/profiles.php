<?php

use App\Http\Controllers\Api\RunnerPublicProfileController;
use App\Http\Controllers\Api\RunnerPublicReviewsController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::get('/runners/{slug}', [
    RunnerPublicProfileController::class,
    'show',
]);

Route::get('/runners/{slug}/reviews', [
    RunnerPublicReviewsController::class,
    'index',
]);

Route::post('/me/runner-profile', [
    RunnerPublicProfileController::class,
    'save',
])->middleware(AuthenticateSession::class);

Route::get('/me/runner-profile/analytics', [
    RunnerPublicProfileController::class,
    'analytics',
])->middleware(AuthenticateSession::class);
