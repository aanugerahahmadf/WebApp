<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\LegalPageController\LegalPageController;


Route::get('/legal-pages', [LegalPageController::class, 'index']);
Route::get('/legal-pages/{slug}', [LegalPageController::class, 'show']);
