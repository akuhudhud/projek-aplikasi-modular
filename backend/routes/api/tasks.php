<?php

use App\Http\Controllers\Api\CoreTaskController;
use App\Http\Controllers\Api\RunnerTaskRatingController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

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

Route::post('/core/tasks/{taskId}/rating', [
    RunnerTaskRatingController::class,
    'storeOrUpdate',
])->middleware(AuthenticateSession::class);

Route::patch('/core/tasks/{taskId}/rating', [
    RunnerTaskRatingController::class,
    'storeOrUpdate',
])->middleware(AuthenticateSession::class);
