<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\ReviewController\ReviewController;


Route::get('/reviews', [ReviewController::class, 'index']);
Route::get('/reviews/{id}', [ReviewController::class, 'show']);
Route::put('/reviews/{id}', [ReviewController::class, 'update']);
Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
