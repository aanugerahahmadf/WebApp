<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\GeoController\GeoController;


Route::get('/geo/admin2', [GeoController::class, 'admin2']);
Route::get('/geo/admin3', [GeoController::class, 'admin3']);
Route::get('/geo/postal-codes', [GeoController::class, 'postalCodes']);
