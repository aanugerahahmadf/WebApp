<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\PackageController\PackageController;


Route::get('/packages', [PackageController::class, 'index']);
Route::get('/packages/{id}', [PackageController::class, 'show']);
Route::post('/packages', [PackageController::class, 'store']);
Route::put('/packages/{id}', [PackageController::class, 'update']);
Route::delete('/packages/{id}', [PackageController::class, 'destroy']);
Route::post('/packages/{id}/upload-image', [PackageController::class, 'uploadImage']);
