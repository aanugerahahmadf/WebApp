<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\BankController\BankController;


Route::get('/banks', [BankController::class, 'index']);
Route::get('/banks/{id}', [BankController::class, 'show']);
Route::post('/banks', [BankController::class, 'store']);
Route::put('/banks/{id}', [BankController::class, 'update']);
Route::delete('/banks/{id}', [BankController::class, 'destroy']);
