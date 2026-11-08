<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\CategoryController\CategoryController;

Route::get('/categories', [CategoryController::class, 'index']);
// Static routes must come before `/categories/{id}`.
Route::get('/categories-with-packages', [CategoryController::class, 'withTopPackages']);
// Canonical domain name; the packages route above remains a compatibility alias.
Route::get('/categories-with-tiers', [CategoryController::class, 'withTopPackages']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
