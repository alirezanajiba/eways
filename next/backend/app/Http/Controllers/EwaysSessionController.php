<?php

namespace App\Http\Controllers;

use App\Services\EwaysApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EwaysSessionController
{
    public function login(Request $request, EwaysApiService $eways): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:190'],
        ]);

        $username = strtr(trim($validated['username']), [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);

        try {
            $result = $eways->login($username, $validated['password']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 401);
        }

        $token = trim((string) ($result['token'] ?? ''));
        $user = $result['userInfo'] ?? (!empty($result['userId']) ? $result : null);

        if ($token === '' || !is_array($user) || empty($user['userId'])) {
            $message = $eways->description($result, 'نام کاربری یا رمز عبور ایویز صحیح نیست.');
            if (isset($result['status']) && is_scalar($result['status']) && (string) $result['status'] !== '') {
                $message .= ' (کد '.(string) $result['status'].')';
            }
            return response()->json(['ok' => false, 'message' => $message], 401);
        }

        $request->session()->regenerate();
        $request->session()->put('eways.token', $token);
        $request->session()->put('eways.user', $user);

        return response()->json([
            'ok' => true,
            'user' => $user,
            'deposit_url' => config('services.eways.deposit_url'),
        ]);
    }

    public function user(Request $request, EwaysApiService $eways): JsonResponse
    {
        $token = (string) $request->session()->get('eways.token', '');
        if ($token === '') {
            return response()->json(['ok' => true, 'authenticated' => false, 'user' => null, 'deposit_url' => config('services.eways.deposit_url')]);
        }

        try {
            $result = $eways->profile($token);
            $user = $result['userInfo'] ?? (!empty($result['userId']) ? $result : null);
            if (!is_array($user) || empty($user['userId'])) {
                throw new RuntimeException('نشست کاربری ایویز معتبر نیست.');
            }
            $request->session()->put('eways.user', $user);

            return response()->json(['ok' => true, 'authenticated' => true, 'user' => $user, 'deposit_url' => config('services.eways.deposit_url')]);
        } catch (\Throwable) {
            $request->session()->forget(['eways.token', 'eways.user']);
            return response()->json(['ok' => true, 'authenticated' => false, 'user' => null, 'deposit_url' => config('services.eways.deposit_url')]);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget(['eways.token', 'eways.user']);
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    }
}
