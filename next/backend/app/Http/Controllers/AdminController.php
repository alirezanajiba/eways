<?php

namespace App\Http\Controllers;

use App\Services\EwaysApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:190'],
        ]);

        $expectedUser = (string) config('admin.username');
        $expectedPass = (string) config('admin.password');
        if ($expectedPass === '') {
            return response()->json(['ok' => false, 'message' => 'رمز مدیر هنوز روی سرور تنظیم نشده است.'], 503);
        }

        if (!hash_equals($expectedUser, (string) $data['username']) || !hash_equals($expectedPass, (string) $data['password'])) {
            return response()->json(['ok' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است.'], 401);
        }

        $request->session()->regenerate();
        $request->session()->put('admin.authenticated', true);
        return response()->json(['ok' => true]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'authenticated' => (bool) $request->session()->get('admin.authenticated', false)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget('admin.authenticated');
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    }

    public function ewaysProduct(Request $request, EwaysApiService $eways): JsonResponse
    {
        if (!$request->session()->get('admin.authenticated')) {
            return response()->json(['ok' => false, 'message' => 'نیاز به ورود مدیر دارید.'], 401);
        }

        $productId = (int) $request->validate(['product_id' => ['required', 'integer', 'min:1']])['product_id'];
        try {
            $result = $eways->product($productId);
            $product = $result['product'] ?? ($result['data'] ?? (!empty($result['id']) ? $result : null));
            if (!is_array($product) || empty($product['id'])) {
                throw new RuntimeException($eways->description($result, 'کالایی با این کد در ایویز پیدا نشد.'));
            }
            return response()->json(['ok' => true, 'product' => $product]);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function videos(Request $request): JsonResponse
    {
        if (!$request->session()->get('admin.authenticated')) return response()->json(['ok' => false, 'message' => 'نیاز به ورود مدیر دارید.'], 401);

        $rows = DB::table('videos as v')
            ->leftJoin('categories as c', 'c.id', '=', 'v.category_id')
            ->orderBy('v.sort_order')->orderByDesc('v.id')
            ->select('v.*', 'c.name as category_name')
            ->get()->map(function ($row) {
                $video = (array) $row;
                $video['total_views'] = (int) DB::table('video_stats')->where('video_id', $row->id)->value('total_views');
                $video['unique_views'] = DB::table('video_viewers')->where('video_id', $row->id)->count();
                $video['sales_count'] = (int) DB::table('order_items')->where('video_id', $row->id)->sum('quantity');
                $video['sales_amount'] = (int) DB::table('order_items')->where('video_id', $row->id)->sum('line_total');
                $video['saves_count'] = DB::table('video_saves')->where('video_id', $row->id)->count();
                $video['comments_count'] = DB::table('comments')->where('video_id', $row->id)->where('is_approved', 1)->count();
                $video['price_tiers'] = DB::table('price_tiers')->where('video_id', $row->id)->orderBy('min_qty')->get(['min_qty','unit_price'])->map(fn ($tier) => (array) $tier)->all();
                return $video;
            });
        return response()->json(['ok' => true, 'videos' => $rows]);
    }

    public function categories(Request $request): JsonResponse
    {
        if (!$request->session()->get('admin.authenticated')) return response()->json(['ok' => false, 'message' => 'نیاز به ورود مدیر دارید.'], 401);
        $rows = DB::table('categories')->orderBy('sort_order')->orderBy('id')->get()->map(function ($row) {
            $category = (array) $row;
            $category['products_count'] = DB::table('videos')->where('category_id', $row->id)->count();
            return $category;
        });
        return response()->json(['ok' => true, 'categories' => $rows]);
    }
}
