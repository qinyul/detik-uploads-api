<?php

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ProductImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::prefix('import')->middleware('auth:sanctum')->group(function () {
        Route::post('/products', [ProductImportController::class, 'import']);
        Route::get('/status/{jobId}', [ProductImportController::class, 'show']);
    });
});
