<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\WalletController\WalletController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/wallet', [WalletController::class, 'getWalletData']);
    Route::get('/wallet/history', [WalletController::class, 'getHistory']);
});
