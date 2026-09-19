<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatabaseProxyController\DatabaseProxyController;


Route::post('/db-proxy', [DatabaseProxyController::class, 'proxy']);
