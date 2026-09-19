<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController
{
    public function catalog(): JsonResponse
    {
        $videos = DB::table('videos as v')
            ->leftJoin('categories as c', 'c.id', '=', 'v.category_id')
            ->where('v.is_active', 1)
            ->orderBy('v.sort_order')
            ->orderByDesc('v.id')
            ->select('v.*', 'c.name as category_name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $ids = array_map(fn ($row) => (int) $row['id'], $videos);
        $tiers = $this->groupedTiers($ids);
        $commentCounts = $ids
            ? DB::table('comments')->whereIn('video_id', $ids)->where('is_approved', 1)->select('video_id', DB::raw('COUNT(*) as total'))->groupBy('video_id')->pluck('total', 'video_id')
            : collect();

        foreach ($videos as &$video) {
            $video['comments_count'] = (int) ($commentCounts[$video['id']] ?? 0);
            $video['price_tiers'] = $tiers[(int) $video['id']] ?? [];
        }
        unset($video);

        $categories = DB::table('categories as c')
            ->where('c.is_active', 1)
            ->orderBy('c.sort_order')
            ->orderBy('c.id')
            ->select('c.id', 'c.name', 'c.icon_key', 'c.sort_order')
            ->get()
            ->map(function ($category) {
                $row = (array) $category;
                $row['products_count'] = DB::table('videos')->where('category_id', $category->id)->where('is_active', 1)->count();
                return $row;
            })->all();

        return response()->json(['ok' => true, 'videos' => $videos, 'categories' => $categories]);
    }

    public function comments(Request $request): JsonResponse
    {
        $videoId = (int) $request->validate(['video_id' => ['required', 'integer', 'min:1']])['video_id'];
        $comments = DB::table('comments')
            ->where('video_id', $videoId)
            ->where('is_approved', 1)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'display_name', 'body', 'created_at']);
        return response()->json(['ok' => true, 'comments' => $comments]);
    }

    public function storeComment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'video_id' => ['required', 'integer', 'min:1'],
            'display_name' => ['required', 'string', 'min:2', 'max:100'],
            'body' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $exists = DB::table('videos')->where('id', $data['video_id'])->where('is_active', 1)->exists();
        if (!$exists) {
            return response()->json(['ok' => false, 'message' => 'ویدئو پیدا نشد.'], 404);
        }

        $id = DB::table('comments')->insertGetId([
            'video_id' => $data['video_id'],
            'display_name' => trim($data['display_name']),
            'body' => trim($data['body']),
            'is_approved' => 1,
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true, 'message' => 'کامنت ثبت شد.', 'comment' => [
            'id' => $id,
            'display_name' => trim($data['display_name']),
            'body' => trim($data['body']),
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]]);
    }

    public function trackView(Request $request): JsonResponse
    {
        $data = $request->validate([
            'video_id' => ['required', 'integer', 'min:1'],
            'visitor_token' => ['required', 'regex:/^[a-zA-Z0-9_-]{16,80}$/'],
        ]);
        if (!DB::table('videos')->where('id', $data['video_id'])->where('is_active', 1)->exists()) {
            return response()->json(['ok' => false, 'message' => 'ویدئو پیدا نشد.'], 404);
        }

        DB::transaction(function () use ($data): void {
            DB::statement('INSERT INTO video_stats (video_id, total_views) VALUES (?, 1) ON DUPLICATE KEY UPDATE total_views = total_views + 1', [$data['video_id']]);
            DB::table('video_viewers')->insertOrIgnore([
                'video_id' => $data['video_id'],
                'visitor_token' => $data['visitor_token'],
                'first_viewed_at' => now(),
            ]);
        });
        return response()->json(['ok' => true]);
    }

    public function trackSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'video_id' => ['required', 'integer', 'min:1'],
            'visitor_token' => ['required', 'regex:/^[a-zA-Z0-9_-]{16,80}$/'],
            'saved' => ['required', 'boolean'],
        ]);

        if ($data['saved']) {
            DB::table('video_saves')->insertOrIgnore([
                'video_id' => $data['video_id'],
                'visitor_token' => $data['visitor_token'],
                'created_at' => now(),
            ]);
        } else {
            DB::table('video_saves')->where('video_id', $data['video_id'])->where('visitor_token', $data['visitor_token'])->delete();
        }
        return response()->json(['ok' => true]);
    }

    private function groupedTiers(array $ids): array
    {
        if (!$ids) return [];
        $grouped = [];
        foreach (DB::table('price_tiers')->whereIn('video_id', $ids)->orderBy('min_qty')->get() as $tier) {
            $grouped[(int) $tier->video_id][] = ['min_qty' => (int) $tier->min_qty, 'unit_price' => (int) $tier->unit_price];
        }
        return $grouped;
    }
}
