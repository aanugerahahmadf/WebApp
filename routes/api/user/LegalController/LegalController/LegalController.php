<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\LegalController\LegalController;


Route::get('/legal/terms', [LegalController::class, 'getTerms']);
Route::get('/legal/privacy', [LegalController::class, 'getPrivacy']);
Route::get('/legal/wedding-decoration-policy', [LegalController::class, 'getWeddingDecorationPolicy']);
Route::get('/legal/about', [LegalController::class, 'getAbout']);
Route::get('/legal/help', [LegalController::class, 'getHelp']);
