<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/products', [ProductController::class, 'getList']);
    Route::post('/products', [ProductController::class, 'createProduct']);
    Route::get('/products/{product_id}', [ProductController::class, 'getProduct']);
    Route::put('/products/{product_id}', [ProductController::class, 'updateProduct']);
    Route::delete('/products/{product_id}', [ProductController::class, 'deleteProduct']);
});
