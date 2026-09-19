<?php

use App\Services\EwaysApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => [
    'ok' => true,
    'service' => 'eways-next',
    'time' => now()->toIso8601String(),
]);

Route::post('/v1/eways/login', function (Request $request, EwaysApiService $eways) {
    $validated = $request->validate([
        'username' => ['required', 'string', 'max:190'],
        'password' => ['required', 'string', 'max:190'],
    ]);

    $username = strtr(trim($validated['username']), [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ]);

    $result = $eways->login($username, $validated['password']);

    return response()->json([
        'ok' => true,
        'data' => $result,
    ]);
});
