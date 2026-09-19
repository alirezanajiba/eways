<?php

use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => [
    'ok' => true,
    'service' => 'eways-next',
    'time' => now()->toIso8601String(),
]);

Route::get('/v1/catalog', [CatalogController::class, 'catalog']);
Route::get('/v1/comments', [CatalogController::class, 'comments']);
Route::post('/v1/comments', [CatalogController::class, 'storeComment']);
Route::post('/v1/track-view', [CatalogController::class, 'trackView']);
Route::post('/v1/track-save', [CatalogController::class, 'trackSave']);
