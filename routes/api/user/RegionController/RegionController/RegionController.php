<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\RegionController\RegionController;


Route::get('/regions/provinces', [RegionController::class, 'provinces']);
Route::get('/regions/cities/{provinceCode}', [RegionController::class, 'cities']);
Route::get('/regions/districts/{cityCode}', [RegionController::class, 'districts']);
Route::get('/regions/villages/{districtCode}', [RegionController::class, 'villages']);
