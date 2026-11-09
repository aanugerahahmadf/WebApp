<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\FavoriteController\FavoriteController;


Route::get('/Favorites', [FavoriteController::class, 'index']);
Route::delete('/Favorites/{id}', [FavoriteController::class, 'destroy']);
