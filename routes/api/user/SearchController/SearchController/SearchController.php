<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\SearchController\SearchController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/search', [SearchController::class, 'byText']);
    Route::post('/search/image', [SearchController::class, 'byImage']);
});
