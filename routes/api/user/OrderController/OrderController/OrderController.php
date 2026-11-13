<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\OrderController\OrderController;


Route::middleware('auth:sanctum')->group(function (): void {
    // Orders
    Route::get('/orders', [OrderController::class, 'getOrders']);
    Route::post('/orders', [OrderController::class, 'createOrder']);
    Route::get('/orders/track/{orderNumber}', [OrderController::class, 'trackOrder']);
    Route::get('/orders/{id}/payment-info', [OrderController::class, 'getPaymentInfo']);
    Route::post('/orders/{id}/confirm-payment', [OrderController::class, 'confirmPayment']);
    Route::post('/orders/{id}/virtual-account', [OrderController::class, 'createVirtualAccount']);
    Route::post('/orders/{id}/qris', [OrderController::class, 'createQris']);
    Route::post('/orders/{id}/pay', [OrderController::class, 'initiatePayment']);
    Route::post('/orders/{id}/upload-proof', [OrderController::class, 'uploadProof']);
    Route::post('/orders/{id}/tracking/sync', [OrderController::class, 'syncTracking']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}', [OrderController::class, 'updateOrder']);
    Route::delete('/orders/{id}', [OrderController::class, 'deleteOrder']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
    Route::post('/orders/{id}/complete', [OrderController::class, 'confirmReceived']);
    Route::get('/orders/{id}/invoice', [OrderController::class, 'downloadInvoice']);
    Route::post('/orders/{id}/invoice/email', [OrderController::class, 'sendInvoiceEmail']);
});
