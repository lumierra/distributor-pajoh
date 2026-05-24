<?php

use App\Http\Controllers\Api\V1\Auth\LoginController as ApiLoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController as ApiLogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController as ApiMeController;
use App\Http\Controllers\Api\V1\Delivery\DeliveryOrderApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('login', ApiLoginController::class)->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', ApiLogoutController::class);
        Route::get('me', ApiMeController::class);
    });
});

// Mobile endpoints (Driver/Sales) untuk Delivery Order.
Route::middleware('auth:sanctum')->prefix('v1/sales')->group(function (): void {
    Route::get('delivery-orders', [DeliveryOrderApiController::class, 'index'])
        ->name('api.sales.delivery-orders.index');
    Route::get('delivery-orders/{delivery_order}', [DeliveryOrderApiController::class, 'show'])
        ->name('api.sales.delivery-orders.show');
    Route::post('delivery-orders/{delivery_order}/start-delivery', [DeliveryOrderApiController::class, 'startDelivery'])
        ->name('api.sales.delivery-orders.start-delivery');
    Route::post('delivery-orders/{delivery_order}/mark-delivered', [DeliveryOrderApiController::class, 'markDelivered'])
        ->name('api.sales.delivery-orders.mark-delivered');
});
