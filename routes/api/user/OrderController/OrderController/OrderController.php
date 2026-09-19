<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\OrderController\OrderController;


Route::middleware('auth:sanctum')->group(function (): void {
    // Bookings
    Route::get('/bookings', [OrderController::class, 'getOrders']);
    Route::post('/bookings', [OrderController::class, 'createOrder']);
    Route::get('/bookings/{id}/payment-info', [OrderController::class, 'getPaymentInfo']);
    Route::post('/bookings/{id}/confirm-payment', [OrderController::class, 'confirmPayment']);
    Route::post('/bookings/{id}/virtual-account', [OrderController::class, 'createVirtualAccount']);
    Route::post('/bookings/{id}/qris', [OrderController::class, 'createQris']);
    Route::post('/bookings/{id}/pay', [OrderController::class, 'initiatePayment']);
    Route::post('/bookings/{id}/upload-proof', [OrderController::class, 'uploadProof']);
    Route::get('/bookings/track/{orderNumber}', [OrderController::class, 'trackOrder']);
    Route::get('/bookings/{id}', [OrderController::class, 'show']);
    Route::post('/bookings/{id}/cancel', [OrderController::class, 'cancelOrder']);
    Route::get('/bookings/{id}/invoice', [OrderController::class, 'downloadInvoice']);
    Route::post('/bookings/{id}/invoice/email', [OrderController::class, 'sendInvoiceEmail']);
    // Orders (alias dari bookings)
    Route::get('/orders', [OrderController::class, 'getOrders']);
    Route::post('/orders', [OrderController::class, 'createOrder']);
    Route::get('/orders/{id}/payment-info', [OrderController::class, 'getPaymentInfo']);
    Route::post('/orders/{id}/confirm-payment', [OrderController::class, 'confirmPayment']);
    Route::post('/orders/{id}/virtual-account', [OrderController::class, 'createVirtualAccount']);
    Route::post('/orders/{id}/qris', [OrderController::class, 'createQris']);
    Route::post('/orders/{id}/pay', [OrderController::class, 'initiatePayment']);
    Route::post('/orders/{id}/upload-proof', [OrderController::class, 'uploadProof']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
    Route::get('/orders/{id}/invoice', [OrderController::class, 'downloadInvoice']);
    Route::post('/orders/{id}/invoice/email', [OrderController::class, 'sendInvoiceEmail']);
});
