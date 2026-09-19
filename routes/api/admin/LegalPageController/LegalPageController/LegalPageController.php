<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\LegalPageController\LegalPageController;


Route::get('/legal-pages', [LegalPageController::class, 'indexPages']);
Route::get('/legal-pages/{id}', [LegalPageController::class, 'showPage']);
Route::post('/legal-pages', [LegalPageController::class, 'storePage']);
Route::put('/legal-pages/{id}', [LegalPageController::class, 'updatePage']);
Route::delete('/legal-pages/{id}', [LegalPageController::class, 'destroyPage']);
Route::get('/terms', [LegalPageController::class, 'indexTerms']);
Route::get('/terms/{id}', [LegalPageController::class, 'showTerm']);
Route::post('/terms', [LegalPageController::class, 'storeTerm']);
Route::put('/terms/{id}', [LegalPageController::class, 'updateTerm']);
Route::delete('/terms/{id}', [LegalPageController::class, 'destroyTerm']);
Route::get('/privacy-policies', [LegalPageController::class, 'indexPolicies']);
Route::get('/privacy-policies/{id}', [LegalPageController::class, 'showPolicy']);
Route::post('/privacy-policies', [LegalPageController::class, 'storePolicy']);
Route::put('/privacy-policies/{id}', [LegalPageController::class, 'updatePolicy']);
Route::delete('/privacy-policies/{id}', [LegalPageController::class, 'destroyPolicy']);
Route::get('/wedding-policies', [LegalPageController::class, 'indexWeddingPolicies']);
Route::get('/wedding-policies/{id}', [LegalPageController::class, 'showWeddingPolicy']);
Route::post('/wedding-policies', [LegalPageController::class, 'storeWeddingPolicy']);
Route::put('/wedding-policies/{id}', [LegalPageController::class, 'updateWeddingPolicy']);
Route::delete('/wedding-policies/{id}', [LegalPageController::class, 'destroyWeddingPolicy']);
