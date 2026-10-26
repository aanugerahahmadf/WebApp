<?php

use App\Http\Controllers\Api\Admin\AnalyticsController\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::get('/analytics', [AnalyticsController::class, 'index']);
Route::get('/security/emergency-status', [AnalyticsController::class, 'emergencyStatus']);
