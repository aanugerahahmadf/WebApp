<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\FirebaseController\FirebaseController;


Route::get('/firebase/status', [FirebaseController::class, 'status']);
Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('firebase')->group(function (): void {
        Route::post('/read', [FirebaseController::class, 'read']);
        Route::post('/write', [FirebaseController::class, 'write']);
        Route::post('/update', [FirebaseController::class, 'update']);
        Route::post('/delete', [FirebaseController::class, 'delete']);
        Route::post('/push', [FirebaseController::class, 'push']);
        Route::post('/children', [FirebaseController::class, 'children']);
        Route::post('/exists', [FirebaseController::class, 'exists']);
        Route::post('/sync-order', [FirebaseController::class, 'syncOrder']);
        Route::post('/sync-message', [FirebaseController::class, 'syncMessage']);
        Route::post('/clear-cache', [FirebaseController::class, 'clearCache']);
    });
});
