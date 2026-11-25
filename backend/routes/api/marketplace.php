<?php

use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\MarketplaceLifecycleController;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\ExpireMarketplaceItems;
use Illuminate\Support\Facades\Route;

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
