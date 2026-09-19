<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\ReferenceOptionController\ReferenceOptionController;


Route::get('/reference-options', [ReferenceOptionController::class, 'index']);
Route::get('/reference-options/{id}', [ReferenceOptionController::class, 'show']);
Route::post('/reference-options', [ReferenceOptionController::class, 'store']);
Route::put('/reference-options/{id}', [ReferenceOptionController::class, 'update']);
Route::delete('/reference-options/{id}', [ReferenceOptionController::class, 'destroy']);
