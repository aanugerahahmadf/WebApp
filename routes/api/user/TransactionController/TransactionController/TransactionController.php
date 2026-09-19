<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\TransactionController\TransactionController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
});
