<?php

use App\Http\Controllers\EwaysSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => 'eways-next-backend',
    'status' => 'ok',
]));

Route::prefix('api/v1/eways')->group(function (): void {
    Route::post('/login', [EwaysSessionController::class, 'login']);
    Route::get('/user', [EwaysSessionController::class, 'user']);
    Route::post('/logout', [EwaysSessionController::class, 'logout']);
});
