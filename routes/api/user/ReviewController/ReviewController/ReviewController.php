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
    Route::get('/reviews/product/{id}/summary', [ReviewController::class, 'getProductRatingSummary']);
    Route::get('/reviews/package/{packageId}', [ReviewController::class, 'getPackageReviews']);
    Route::get('/reviews/product/{productId}', [ReviewController::class, 'getProductReviews']);
    Route::get('/reviews/organizer/{id}', [ReviewController::class, 'getOrganizerReviews']);
    Route::post('/reviews/{id}/vote', [ReviewController::class, 'voteHelpful']);
    Route::post('/reviews/{id}/reply', [ReviewController::class, 'reply']);
    Route::delete('/reviews/{id}/reply/{replyId}', [ReviewController::class, 'deleteReply']);
});
