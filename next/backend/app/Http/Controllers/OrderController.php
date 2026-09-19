<?php

namespace App\Http\Controllers;

use App\Services\EwaysApiService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderController
{
    public function store(Request $request, OrderService $orders, EwaysApiService $eways): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.video_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $token = (string) $request->session()->get('eways.token', '');
        $user = (array) $request->session()->get('eways.user', []);

        if ($token !== '') {
            try {
                $profile = $eways->profile($token);
                $freshUser = $profile['userInfo'] ?? (!empty($profile['userId']) ? $profile : null);
                if (is_array($freshUser) && !empty($freshUser['userId'])) {
                    $user = $freshUser;
                    $request->session()->put('eways.user', $user);
                }
            } catch (\Throwable) {
                $request->session()->forget(['eways.token', 'eways.user']);
                $token = '';
                $user = [];
            }
        }

        try {
            $result = $orders->submit($validated['items'], $user, $token);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        if (($result['ok'] ?? false) && $token !== '' && !empty($user)) {
            try {
                $profile = $eways->profile($token);
                $freshUser = $profile['userInfo'] ?? (!empty($profile['userId']) ? $profile : null);
                if (is_array($freshUser)) {
                    $request->session()->put('eways.user', $freshUser);
                    $result['user'] = $freshUser;
                }
            } catch (\Throwable) {
            }
        }

        return response()->json($result, $status);
    }
}
