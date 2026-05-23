<?php

use App\Http\Controllers\Api\V1\Auth\LoginController as ApiLoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController as ApiLogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController as ApiMeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('login', ApiLoginController::class)->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', ApiLogoutController::class);
        Route::get('me', ApiMeController::class);
    });
});
