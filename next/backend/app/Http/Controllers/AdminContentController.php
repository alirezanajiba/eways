<?php

namespace App\Http\Controllers;

use App\Services\EwaysApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminContentController
{
    private const ICONS = ['grid','mobile','charger','headphones','speaker','battery','cable','watch','camera','computer','keyboard','mouse','gamepad','car','home','lamp','gift','bag','tools','screen','wifi','memory','printer','audio'];

    private function guard(Request $request): ?JsonResponse
    {
        return $request->session()->get('admin.authenticated')
            ? null
            : response()->json(['ok' => false, 'message' => 'نیاز به ورود مدیر دارید.'], 401);
    }

    public function saveCategory(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request)) return $guard;
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'icon_key' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable'],
        ]);
        $name = trim($data['name']);
        $icon = in_array((string) ($data['icon_key'] ?? ''), self::ICONS, true) ? (string) $data['icon_key'] : 'grid';
        $id = (int) ($data['id'] ?? 0);
        $exists = DB::table('categories')->where('name', $name)->when($id, fn ($q) => $q->where('id', '<>', $id))->exists();
        if ($exists) return response()->json(['ok' => false, 'message' => 'دسته بندی دیگری با این نام وجود دارد.'], 422);

        $values = ['name' => $name, 'icon_key' => $icon, 'sort_order' => (int) ($data['sort_order'] ?? 0), 'is_active' => $request->boolean('is_active'), 'updated_at' => now()];
        if ($id) {
            if (!DB::table('categories')->where('id', $id)->exists()) return response()->json(['ok' => false, 'message' => 'دسته بندی پیدا نشد.'], 404);
            DB::table('categories')->where('id', $id)->update($values);
        } else {
            $values['created_at'] = now();
            $id = DB::table('categories')->insertGetId($values);
        }
        return response()->json(['ok' => true, 'id' => $id, 'message' => 'دسته بندی ذخیره شد.']);
    }

    public function deleteCategory(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request)) return $guard;
        $id = (int) $request->validate(['id' => ['required','integer','min:1']])['id'];
        if (DB::table('videos')->where('category_id', $id)->exists()) {
            return response()->json(['ok' => false, 'message' => 'ابتدا محصولات این دسته بندی را جابجا کنید.'], 422);
        }
        DB::table('categories')->where('id', $id)->delete();
        return response()->json(['ok' => true, 'message' => 'دسته بندی حذف شد.']);
    }

    public function saveVideo(Request $request, EwaysApiService $eways): JsonResponse
    {
        if ($guard = $this->guard($request)) return $guard;

        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT) ?: null;
        $sourceType = $request->input('source_type') === 'eways' ? 'eways' : 'manual';
        $ewaysProductId = $sourceType === 'eways' ? (filter_var($request->input('eways_product_id'), FILTER_VALIDATE_INT) ?: null) : null;
        $remoteProduct = null;

        if ($sourceType === 'eways') {
            if (!$ewaysProductId) return response()->json(['ok' => false, 'message' => 'کد کالای ایویز را وارد و استعلام کنید.'], 422);
            try {
                $result = $eways->product((int) $ewaysProductId);
                $remoteProduct = $result['product'] ?? ($result['data'] ?? (!empty($result['id']) ? $result : null));
                if (!is_array($remoteProduct) || empty($remoteProduct['id'])) throw new RuntimeException('کالایی با این کد در ایویز پیدا نشد.');
            } catch (RuntimeException $e) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            $duplicate = DB::table('videos')->where('eways_product_id', $ewaysProductId)->when($id, fn ($q) => $q->where('id', '<>', $id))->exists();
            if ($duplicate) return response()->json(['ok' => false, 'message' => 'این کالای ایویز قبلاً به یک ویدئو متصل شده است.'], 422);
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '' && $remoteProduct) $title = trim((string) ($remoteProduct['name'] ?? ''));
        if ($title === '') return response()->json(['ok' => false, 'message' => 'عنوان محصول اجباری است.'], 422);

        $existing = $id ? DB::table('videos')->where('id', $id)->first() : null;
        if ($id && !$existing) return response()->json(['ok' => false, 'message' => 'ویدئو پیدا نشد.'], 404);

        $videoPath = $existing->video_path ?? null;
        $posterPath = $existing->poster_path ?? null;
        if ($request->hasFile('video_file')) {
            $new = $this->saveUpload($request, 'video_file', true);
            $this->deleteMedia($videoPath);
            $videoPath = $new;
        }
        if ($request->hasFile('poster_file')) {
            $new = $this->saveUpload($request, 'poster_file', false);
            $this->deleteMedia($posterPath);
            $posterPath = $new;
        }
        if ($request->boolean('remove_poster')) {
            $this->deleteMedia($posterPath);
            $posterPath = null;
        }
        if (!$videoPath) return response()->json(['ok' => false, 'message' => 'انتخاب فایل ویدئو اجباری است.'], 422);

        $categoryId = filter_var($request->input('category_id'), FILTER_VALIDATE_INT) ?: null;
        if ($categoryId && !DB::table('categories')->where('id', $categoryId)->exists()) {
            return response()->json(['ok' => false, 'message' => 'دسته بندی انتخاب شده معتبر نیست.'], 422);
        }

        $tiers = [];
        if ($sourceType === 'manual') {
            $mins = (array) $request->input('tier_min_qty', []);
            $prices = (array) $request->input('tier_unit_price', []);
            foreach ($mins as $index => $minimum) {
                if ($minimum === '' && ($prices[$index] ?? '') === '') continue;
                $minimum = (int) $minimum;
                $unitPrice = (int) preg_replace('/\D+/', '', (string) ($prices[$index] ?? '0'));
                if ($minimum < 1 || $unitPrice < 1) return response()->json(['ok' => false, 'message' => 'حداقل تعداد و قیمت همه پله ها باید بیشتر از صفر باشد.'], 422);
                $tiers[$minimum] = $unitPrice;
            }
            ksort($tiers, SORT_NUMERIC);
            $previous = max(0, (int) preg_replace('/\D+/', '', (string) $request->input('price', '0')));
            foreach ($tiers as $price) {
                if ($previous > 0 && $price > $previous) return response()->json(['ok' => false, 'message' => 'قیمت هر پله باید از قیمت پله قبلی کمتر یا مساوی باشد.'], 422);
                $previous = $price;
            }
        }

        $remotePrice = $remoteProduct ? max(0, (int) round((float) ($remoteProduct['price'] ?? 0))) : null;
        $remoteStock = $remoteProduct ? max(0, (int) ($remoteProduct['stock'] ?? 0)) : null;
        $manualPrice = max(0, (int) preg_replace('/\D+/', '', (string) $request->input('price', '0')));
        $timer = trim((string) $request->input('timer_end', ''));
        $values = [
            'product_code' => $sourceType === 'eways' ? (string) $ewaysProductId : (trim((string) $request->input('product_code')) ?: null),
            'source_type' => $sourceType,
            'eways_product_id' => $ewaysProductId,
            'category_id' => $categoryId,
            'title' => $title,
            'description' => trim((string) $request->input('description')) ?: null,
            'brand' => $remoteProduct ? (trim((string) ($remoteProduct['brandName'] ?? '')) ?: null) : (trim((string) $request->input('brand')) ?: null),
            'shipping_text' => trim((string) $request->input('shipping_text')) ?: null,
            'price' => $remotePrice ?? $manualPrice,
            'stock_remaining' => $remoteStock ?? max(0, (int) $request->input('stock_remaining', 0)),
            'stock_total' => $remoteStock ?? max(0, (int) $request->input('stock_total', 0)),
            'timer_end' => $timer !== '' ? date('Y-m-d H:i:s', strtotime($timer)) : null,
            'video_path' => $videoPath,
            'poster_path' => $posterPath,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active'),
            'updated_at' => now(),
        ];

        DB::transaction(function () use (&$id, $values, $tiers): void {
            if ($id) DB::table('videos')->where('id', $id)->update($values);
            else {
                $insert = $values;
                $insert['created_at'] = now();
                $id = DB::table('videos')->insertGetId($insert);
            }
            DB::table('price_tiers')->where('video_id', $id)->delete();
            foreach ($tiers as $minimum => $unitPrice) {
                DB::table('price_tiers')->insert(['video_id' => $id, 'min_qty' => $minimum, 'unit_price' => $unitPrice, 'created_at' => now()]);
            }
        });

        return response()->json(['ok' => true, 'id' => $id, 'message' => 'ویدئو ذخیره شد.']);
    }

    public function deleteVideo(Request $request): JsonResponse
    {
        if ($guard = $this->guard($request)) return $guard;
        $id = (int) $request->validate(['id' => ['required','integer','min:1']])['id'];
        $row = DB::table('videos')->where('id', $id)->first();
        if (!$row) return response()->json(['ok' => false, 'message' => 'ویدئو پیدا نشد.'], 404);
        if (DB::table('order_items')->where('video_id', $id)->exists()) {
            return response()->json(['ok' => false, 'message' => 'این ویدئو سابقه فروش دارد و قابل حذف نیست؛ آن را غیرفعال کنید.'], 422);
        }
        DB::table('videos')->where('id', $id)->delete();
        $this->deleteMedia($row->video_path);
        $this->deleteMedia($row->poster_path);
        return response()->json(['ok' => true, 'message' => 'ویدئو حذف شد.']);
    }

    private function saveUpload(Request $request, string $field, bool $video): string
    {
        $file = $request->file($field);
        $maxKb = $video ? 131072 : 8192;
        $rules = $video ? ['file','mimetypes:video/mp4,application/mp4','max:'.$maxKb] : ['file','mimes:jpg,jpeg,png,webp','max:'.$maxKb];
        validator([$field => $file], [$field => $rules])->validate();
        $folder = $video ? 'videos' : 'posters';
        $extension = strtolower($file->getClientOriginalExtension()) ?: ($video ? 'mp4' : 'jpg');
        $name = Str::random(40).'.'.$extension;
        $target = public_path('media/'.$folder);
        if (!is_dir($target)) mkdir($target, 0755, true);
        $file->move($target, $name);
        return '/media/'.$folder.'/'.$name;
    }

    private function deleteMedia(?string $url): void
    {
        if (!$url || !str_starts_with($url, '/media/')) return;
        $mediaRoot = realpath(public_path('media'));
        $path = realpath(public_path(ltrim($url, '/')));
        if ($mediaRoot && $path && str_starts_with($path, $mediaRoot.DIRECTORY_SEPARATOR) && is_file($path)) unlink($path);
    }
}
