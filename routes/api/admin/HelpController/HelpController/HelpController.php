<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\HelpController\HelpController;


Route::get('/helps', [HelpController::class, 'index']);
Route::get('/helps/{id}', [HelpController::class, 'show']);
Route::post('/helps', [HelpController::class, 'store']);
Route::put('/helps/{id}', [HelpController::class, 'update']);
Route::delete('/helps/{id}', [HelpController::class, 'destroy']);
