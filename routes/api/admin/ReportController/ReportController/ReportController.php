<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\ReportController\ReportController;


Route::get('/reports', [ReportController::class, 'index']);
Route::get('/reports/stats', [ReportController::class, 'stats']);
Route::get('/reports/{id}', [ReportController::class, 'show']);
Route::put('/reports/{id}/status', [ReportController::class, 'updateStatus']);
