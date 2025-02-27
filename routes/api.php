<?php

use App\Http\Controllers\API\FileController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DocumentController;

Route::controller(UserController::class)->group(function () {
    Route::post('/users/register', 'registerUser');
    Route::post('/users/login', 'loginUser');
    Route::post('/users/logout', 'logoutUser')->middleware('auth:sanctum'); // Protect logout route
});
Route::apiResource('/files', FileController::class);
Route::apiResource('/document',DocumentController::class);
Route::get('/generate-code',[DocumentController::class,'generateTrackingCode']);

