<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\WorldRegionController\WorldRegionController;


Route::get('/world-regions/countries', [WorldRegionController::class, 'countries']);
Route::get('/world-regions/states', [WorldRegionController::class, 'states']);
Route::get('/world-regions/cities', [WorldRegionController::class, 'cities']);
