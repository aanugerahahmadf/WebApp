<?php

use App\Http\Controllers\Api\User\CatalogController\CatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog routes (mobile + web)
|--------------------------------------------------------------------------
| `/catalog` is the only path -- the pre-rename alias has been removed, so
| every caller (Flutter app, web panel, Filament) now uses /catalog.
*/

Route::get('/catalog/public', [CatalogController::class, 'index']);
Route::get('/catalog/{id}/tiers', [CatalogController::class, 'tiers']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/catalog', [CatalogController::class, 'index']);
    Route::get('/catalog/featured', [CatalogController::class, 'featured']);
    Route::get('/catalog/on-sale', [CatalogController::class, 'onSale']);
    Route::get('/catalog/{id}', [CatalogController::class, 'show']);
});
