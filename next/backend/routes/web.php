<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\EwaysSessionController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => 'eways-next-backend',
    'status' => 'ok',
]));

Route::prefix('api/v1')->group(function (): void {
    Route::prefix('eways')->group(function (): void {
        Route::post('/login', [EwaysSessionController::class, 'login']);
        Route::get('/user', [EwaysSessionController::class, 'user']);
        Route::post('/logout', [EwaysSessionController::class, 'logout']);
    });

    Route::post('/orders', [OrderController::class, 'store']);

    Route::prefix('admin')->group(function (): void {
        Route::post('/login', [AdminController::class, 'login']);
        Route::get('/status', [AdminController::class, 'status']);
        Route::post('/logout', [AdminController::class, 'logout']);
        Route::post('/eways-product', [AdminController::class, 'ewaysProduct']);
        Route::get('/videos', [AdminController::class, 'videos']);
        Route::get('/categories', [AdminController::class, 'categories']);
    });
});
