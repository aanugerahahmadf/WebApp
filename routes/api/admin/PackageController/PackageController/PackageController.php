<?php

use App\Http\Controllers\Api\Admin\PackageController\PackageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin tier ("pilihan paket") routes
|--------------------------------------------------------------------------
| Tiers are always scoped to a catalog and only readable/writable through
| `/admin/catalog/{catalogId}/tiers`. The standalone `/admin/packages` group
| has been removed.
*/

Route::prefix('catalog/{catalogId}/tiers')->group(function (): void {
    Route::get('/', [PackageController::class, 'tiers']);
    Route::post('/', [PackageController::class, 'store']);
});
