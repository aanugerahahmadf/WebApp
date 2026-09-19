<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\ReportController\ReportController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/reports', [ReportController::class, 'store']);
});
