<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get("/sign", "App\Http\Controllers\SignController@test");
Route::get("/firm", "App\Http\Controllers\SignController@signXML");
Route::post("/sign", "App\Http\Controllers\SignController@signXML");
Route::post("/sign/sendSunat", "App\Http\Controllers\SignController@sendSunat");
