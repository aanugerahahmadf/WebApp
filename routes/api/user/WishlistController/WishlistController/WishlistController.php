<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\WishlistController\WishlistController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);
    Route::get('/wishlist/{packageId}/check', [WishlistController::class, 'isInWishlist']);
    Route::post('/wishlist/bulk-add', [WishlistController::class, 'bulkAdd']);
    Route::delete('/wishlist/{packageId}', [WishlistController::class, 'removeFromWishlist']);
});
