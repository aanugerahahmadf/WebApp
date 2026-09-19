<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\UserLanguageController\UserLanguageController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user/language', [UserLanguageController::class, 'show']);
    Route::put('/user/language', [UserLanguageController::class, 'update']);
});
