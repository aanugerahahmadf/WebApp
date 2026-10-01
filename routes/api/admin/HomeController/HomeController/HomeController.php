<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\HomeController\HomeController;


Route::get('/dashboard', [HomeController::class, 'index']);
