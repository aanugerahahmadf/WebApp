<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\HomeController\HomeController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/home', [HomeController::class, 'index']);
});
