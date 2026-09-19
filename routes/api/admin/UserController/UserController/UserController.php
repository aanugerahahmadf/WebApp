<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\UserController\UserController;


Route::get('/users', [UserController::class, 'index']);
Route::get('/vendors', [UserController::class, 'vendors']);
Route::get('/users/roles', [UserController::class, 'roles']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);
Route::post('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
Route::post('/users/{id}/kyc/approve', [UserController::class, 'approveKyc']);
Route::post('/users/{id}/kyc/reject', [UserController::class, 'rejectKyc']);
