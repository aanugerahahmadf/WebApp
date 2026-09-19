<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\CBIRController\CBIRController;


Route::get('/cbir/arithmetic/ops', [CBIRController::class, 'arithmeticOps']);
Route::get('/cbir/stats', [CBIRController::class, 'getStats']);
Route::get('/cbir/evaluate', [CBIRController::class, 'evaluate']);
Route::get('/cbir/health', [CBIRController::class, 'healthCheck']);
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/cbir/search', [CBIRController::class, 'searchSimilar']);
    Route::post('/cbir/arithmetic', [CBIRController::class, 'arithmeticSearch']);
    Route::post('/cbir/index/product', [CBIRController::class, 'indexItem']);
    Route::post('/cbir/index/build', [CBIRController::class, 'buildIndex']);
});
