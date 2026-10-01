<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\PusherAuthController\PusherAuthController;


Route::post('/pusher/auth', [PusherAuthController::class, 'auth']);
