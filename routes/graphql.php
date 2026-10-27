<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GraphQL\GraphQLController;
use App\Http\Middleware\GraphQLMiddleware;

/*
|--------------------------------------------------------------------------
| GraphQL Routes
|--------------------------------------------------------------------------
|
| Here is where you can register GraphQL routes for your application.
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

Route::group(['prefix' => 'graphql', 'middleware' => ['api', GraphQLMiddleware::class]], function () {
    // GraphQL endpoint
    Route::post('/', [GraphQLController::class, 'index']);
    // GraphQL Playground (only in non-production)
    if (config('graphql.playground.enabled', false)) {
        Route::get('/playground', [GraphQLController::class, 'playground']);
    }
});
