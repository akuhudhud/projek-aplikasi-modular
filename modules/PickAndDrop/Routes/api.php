<?php

use Illuminate\Support\Facades\Route;
use Modules\PickAndDrop\Http\Controllers\PickAndDropFoundationController;

/*
|--------------------------------------------------------------------------
| Pick & Drop API Routes
|--------------------------------------------------------------------------
|
| Business routes for the Pick & Drop module belong here.
|
| The module remains independent from Core route definitions.
| The API boundary is registered by the module Service Provider and
| receives the application's API middleware through the module route
| registration.
|
*/

Route::middleware('api')
    ->prefix('api/pick-and-drop')
    ->group(function (): void {
        Route::get(
            '/foundation/status',
            [PickAndDropFoundationController::class, 'status']
        );
    });
