<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\ReviewController\ReviewController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    Route::get('/reviews/user/{userId}', [ReviewController::class, 'getUserPublicReviews']);
    Route::get('/reviews/user', [ReviewController::class, 'getUserReviews']);
    Route::get('/reviews/package/{id}/summary', [ReviewController::class, 'getPackageRatingSummary']);
    Route::get('/reviews/catalog/{id}/summary', [ReviewController::class, 'getCatalogRatingSummary']);
    Route::get('/reviews/package/{packageId}', [ReviewController::class, 'getPackageReviews']);
    Route::get('/reviews/catalog/{catalogId}', [ReviewController::class, 'getCatalogReviews']);
    
    Route::post('/reviews/{id}/vote', [ReviewController::class, 'voteHelpful']);
    Route::post('/reviews/{id}/reply', [ReviewController::class, 'reply']);
    Route::delete('/reviews/{id}/reply/{replyId}', [ReviewController::class, 'deleteReply']);
});
