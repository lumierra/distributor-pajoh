<?php

use App\Http\Controllers\Api\V1\Auth\LoginController as ApiLoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController as ApiLogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController as ApiMeController;
use App\Http\Controllers\Api\V1\Delivery\DeliveryOrderApiController;
use App\Http\Controllers\Api\V1\Payment\PaymentRequestApiController;
use App\Http\Controllers\Api\V1\Sales\BypassApiController;
use App\Http\Controllers\Api\V1\Sales\ScheduleApiController;
use App\Http\Controllers\Api\V1\Sales\VisitApiController;
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

    // Payment Request mobile endpoints
    Route::get('payment-requests/invoices', [PaymentRequestApiController::class, 'payableInvoices'])
        ->name('api.sales.payment-requests.payable-invoices');
    Route::get('payment-requests', [PaymentRequestApiController::class, 'index'])
        ->name('api.sales.payment-requests.index');
    Route::post('payment-requests', [PaymentRequestApiController::class, 'store'])
        ->name('api.sales.payment-requests.store');
    Route::get('payment-requests/{payment_request}', [PaymentRequestApiController::class, 'show'])
        ->name('api.sales.payment-requests.show');
    Route::put('payment-requests/{payment_request}', [PaymentRequestApiController::class, 'update'])
        ->name('api.sales.payment-requests.update');
    Route::post('payment-requests/{payment_request}/submit', [PaymentRequestApiController::class, 'submit'])
        ->name('api.sales.payment-requests.submit');
    Route::post('payment-requests/{payment_request}/cancel', [PaymentRequestApiController::class, 'cancel'])
        ->name('api.sales.payment-requests.cancel');

    // T10 — Sales Visit + Schedule + Bypass
    Route::get('schedules/today', [ScheduleApiController::class, 'today'])->name('api.sales.schedules.today');

    Route::get('visits', [VisitApiController::class, 'index'])->name('api.sales.visits.index');
    Route::get('visits/active', [VisitApiController::class, 'active'])->name('api.sales.visits.active');
    Route::post('visits/checkin', [VisitApiController::class, 'checkin'])->name('api.sales.visits.checkin');
    Route::post('visits/{visit}/checkout', [VisitApiController::class, 'checkout'])->name('api.sales.visits.checkout');

    Route::post('bypass-requests', [BypassApiController::class, 'store'])->name('api.sales.bypass-requests.store');
});
