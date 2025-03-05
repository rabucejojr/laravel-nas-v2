<?php

use App\Http\Controllers\API\DocumentController;
use App\Http\Controllers\API\FileController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

// User routes
Route::controller(UserController::class)->group(function () {
    Route::post('/users/register', 'registerUser');
    Route::post('/users/login', 'loginUser');
    Route::post('/users/logout', 'logoutUser')->middleware('auth:sanctum'); // Protect logout route
});

// Api Resource for files and documents
Route::apiResource('/files', FileController::class);
Route::apiResource('/document', DocumentController::class);

// Generate tracking code
Route::get('document/generate-code', [DocumentController::class, 'generateTrackingCode']);
// Search endpoints
Route::get('/document/search', [DocumentController::class, 'search']);
Route::get('/file/search', [FileController::class, 'search']);
