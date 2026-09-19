<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PusherAuthController\PusherAuthController;


Route::post('/pusher/auth', [PusherAuthController::class, 'auth']);
