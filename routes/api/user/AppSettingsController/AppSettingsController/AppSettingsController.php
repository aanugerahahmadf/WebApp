<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\AppSettingsController\AppSettingsController;


Route::get('/settings', [AppSettingsController::class, 'index']);
