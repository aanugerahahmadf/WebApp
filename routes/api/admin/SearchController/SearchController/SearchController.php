<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\SearchController\SearchController;


Route::get('/search', [SearchController::class, 'byTextAdmin']);
