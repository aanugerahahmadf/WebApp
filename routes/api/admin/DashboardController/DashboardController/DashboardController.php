<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\DashboardController\DashboardController;


Route::get('/dashboard', [DashboardController::class, 'index']);
