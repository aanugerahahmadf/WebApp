<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\LoginActivityController\LoginActivityController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/security/login-activity', [LoginActivityController::class, 'index']);
    Route::delete('/security/login-activity/{id}', [LoginActivityController::class, 'destroy']);
});
