<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\NotificationController\NotificationController;


Route::get('/notifications', [NotificationController::class, 'index']);
Route::get('/notifications/{id}', [NotificationController::class, 'show']);
Route::post('/notifications/send', [NotificationController::class, 'sendToUser']);
Route::post('/notifications/send-bulk', [NotificationController::class, 'sendBulk']);
Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
