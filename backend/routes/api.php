<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(AuthenticateSession::class);
