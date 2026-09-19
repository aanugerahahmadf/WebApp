<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\VoucherController\VoucherController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/vouchers', [VoucherController::class, 'index']);
    Route::post('/vouchers/validate', [VoucherController::class, 'validateVoucher']);
    Route::post('/vouchers/{voucher}/claim', [VoucherController::class, 'claim']);
});
