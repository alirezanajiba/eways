<?php

use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => [
    'ok' => true,
    'service' => 'eways-next',
    'time' => now()->toIso8601String(),
]);
