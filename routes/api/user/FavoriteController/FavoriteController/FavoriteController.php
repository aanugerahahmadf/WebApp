<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\FavoriteController\FavoriteController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/Favorite', [FavoriteController::class, 'index']);
    Route::post('/Favorite/toggle', [FavoriteController::class, 'toggle']);
    Route::get('/Favorite/{packageId}/check', [FavoriteController::class, 'isInFavorite']);
    Route::get('/Favorite/tier/{packageId}/check', [FavoriteController::class, 'isInFavorite']);
    Route::post('/Favorite/bulk-add', [FavoriteController::class, 'bulkAdd']);
    Route::delete('/Favorite/{packageId}', [FavoriteController::class, 'removeFromFavorite']);
    Route::delete('/Favorite/tier/{packageId}', [FavoriteController::class, 'removeFromFavorite']);
});
