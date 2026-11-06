<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CustomerTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('web/auth')
    ->name('web.auth.')
    ->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login-ip')
            ->name('login');

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
    });

Route::view('/customer', 'customer')
    ->name('customer.app');
