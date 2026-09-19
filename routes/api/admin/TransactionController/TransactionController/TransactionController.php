<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\TransactionController\TransactionController;


Route::get('/transactions', [TransactionController::class, 'index']);
Route::get('/transactions/{id}', [TransactionController::class, 'show']);
