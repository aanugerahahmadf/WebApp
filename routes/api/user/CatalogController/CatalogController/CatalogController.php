<?php

use App\Http\Controllers\Api\User\CatalogController\CatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog routes (mobile + web)
|--------------------------------------------------------------------------
| Publicly browsable by guests and authenticated users.
*/

Route::get('/catalog', [CatalogController::class, 'index']);
Route::get('/catalog/public', [CatalogController::class, 'index']);
Route::get('/catalog/featured', [CatalogController::class, 'featured']);
Route::get('/catalog/on-sale', [CatalogController::class, 'onSale']);
Route::get('/catalog/{id}/tiers', [CatalogController::class, 'tiers']);
Route::get('/catalog/{id}', [CatalogController::class, 'show']);
