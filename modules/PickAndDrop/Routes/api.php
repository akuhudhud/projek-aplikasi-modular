<?php

use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Modules\PickAndDrop\Http\Controllers\PickAndDropFoundationController;
use Modules\PickAndDrop\Http\Controllers\PickAndDropRequestController;

/*
|--------------------------------------------------------------------------
| Pick & Drop API Routes
|--------------------------------------------------------------------------
|
| Business routes for the Pick & Drop module belong here.
| Core authentication is reused through AuthenticateSession.
|
*/

Route::middleware('api')
    ->prefix('api/pick-and-drop')
    ->group(function (): void {
        Route::get(
            '/foundation/status',
            [PickAndDropFoundationController::class, 'status']
        );

        Route::post(
            '/requests',
            [PickAndDropRequestController::class, 'store']
        )->middleware(AuthenticateSession::class);
    });
