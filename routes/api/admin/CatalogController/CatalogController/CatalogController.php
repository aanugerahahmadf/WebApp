<?php

use App\Http\Controllers\Api\Admin\CatalogController\CatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin catalog routes
|--------------------------------------------------------------------------
| `/admin/catalog` is the only path -- the pre-rename alias has been removed.
*/

Route::prefix('catalog')->group(function (): void {
    Route::get('/', [CatalogController::class, 'index']);
    Route::get('/{id}', [CatalogController::class, 'show']);
    Route::post('/', [CatalogController::class, 'store']);
    Route::put('/{id}', [CatalogController::class, 'update']);
    Route::delete('/{id}', [CatalogController::class, 'destroy']);
    Route::post('/{id}/upload-image', [CatalogController::class, 'uploadImage']);
});
