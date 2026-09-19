<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => 'eways-next-backend',
    'status' => 'ok',
]));
