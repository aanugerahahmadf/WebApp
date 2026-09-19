<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\WishlistController\WishlistController;


Route::get('/wishlists', [WishlistController::class, 'index']);
Route::delete('/wishlists/{id}', [WishlistController::class, 'destroy']);
