<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\DropdownOptionController\DropdownOptionController;


Route::get('/dropdown-options', [DropdownOptionController::class, 'index']);
