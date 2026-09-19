<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\HistoryController\HistoryController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/histories', [HistoryController::class, 'index']);
});
