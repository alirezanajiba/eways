<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function __construct(private readonly EwaysApiService $eways) {}

    public function submit(array $items, array $sessionUser, string $sessionToken): array
    {
        $requested = [];
        foreach ($items as $item) {
            $videoId = filter_var($item['video_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($videoId && $quantity && $quantity > 0) {
                $requested[(int) $videoId] = ($requested[(int) $videoId] ?? 0) + (int) $quantity;
            }
        }
        if (!$requested) {
            throw new RuntimeException('سبد خرید خالی است.');
        }

        $products = [];
        $shortages = [];
        foreach ($requested as $videoId => $quantity) {
            $product = DB::table('videos')
                ->select('id','title','price','stock_remaining','is_active','timer_end','source_type','eways_product_id')
                ->where('id', $videoId)
                ->first();

            $available = $product && (int) $product->is_active === 1 && (!$product->timer_end || strtotime((string) $product->timer_end) > time())
                ? (int) $product->stock_remaining : 0;

            if (!$product || $quantity > $available) {
                $shortages[] = [
                    'video_id' => $videoId,
                    'title' => $product->title ?? 'محصول ناموجود',
                    'available' => $available,
                    'requested' => $quantity,
                ];
                continue;
            }

            $unitPrice = $product->source_type === 'eways'
                ? (int) $product->price
                : $this->tierPrice($videoId, $quantity, (int) $product->price);

            $products[] = [
                'id' => (int) $product->id,
                'title' => (string) $product->title,
                'source_type' => (string) $product->source_type,
                'eways_product_id' => $product->eways_product_id ? (int) $product->eways_product_id : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $quantity,
            ];
        }

        if ($shortages) {
            return ['ok' => false, 'status' => 409, 'message' => 'موجودی بعضی محصولات برای ثبت سفارش کافی نیست.', 'shortages' => $shortages];
        }

        $sourceTypes = array_values(array_unique(array_column($products, 'source_type')));
        if (count($sourceTypes) > 1) {
            return ['ok' => false, 'status' => 422, 'message' => 'کالاهای متصل به ایویز و کالاهای مستقل را در دو سفارش جداگانه ثبت کنید.'];
        }

        return ($sourceTypes[0] ?? 'manual') === 'eways'
            ? $this->submitEways($products, $sessionUser, $sessionToken)
            : $this->submitLocal($products);
    }

    private function submitEways(array $products, array $user, string $token): array
    {
        if (!$user || $token === '') {
            return ['ok' => false, 'status' => 401, 'message' => 'برای خرید با سپرده، ابتدا وارد حساب ایویز شوید.', 'login_required' => true];
        }

        $deposit = (int) round((float) ($user['revenue'] ?? 0));
        $remoteProducts = [];
        $shortages = [];

        foreach ($products as &$product) {
            $remote = $this->eways->product((int) $product['eways_product_id'], $token);
            $remoteProduct = $remote['product'] ?? (!empty($remote['id']) ? $remote : $remote['data'] ?? null);
            if (!is_array($remoteProduct)) {
                throw new RuntimeException('اطلاعات کالای ایویز معتبر نیست.');
            }
            $available = !empty($remoteProduct['availability']) ? (int) ($remoteProduct['stock'] ?? 0) : 0;
            $maxOrder = (int) ($remoteProduct['maxOrder'] ?? 0);
            if ($maxOrder > 0) {
                $available = min($available, $maxOrder);
            }
            if ((int) $product['quantity'] > $available) {
                $shortages[] = ['video_id' => $product['id'], 'title' => $product['title'], 'available' => $available, 'requested' => $product['quantity']];
                continue;
            }
            $product['unit_price'] = (int) round((float) ($remoteProduct['price'] ?? 0));
            $product['line_total'] = $product['unit_price'] * $product['quantity'];
            $remoteProducts[(int) $product['eways_product_id']] = $remoteProduct;
        }
        unset($product);

        if ($shortages) {
            return ['ok' => false, 'status' => 409, 'message' => 'موجودی بعضی محصولات در ایویز کافی نیست.', 'shortages' => $shortages];
        }

        $originalBasket = $this->eways->basketDetails($user, $token);
        $originalItems = [];
        foreach (($originalBasket['basket'] ?? []) as $item) {
            if (is_array($item) && !empty($item['productId']) && !empty($item['count'])) {
                $originalItems[] = ['productId' => (int) $item['productId'], 'count' => (int) $item['count']];
            }
        }

        $appItems = array_map(fn (array $product) => [
            'productId' => (int) $product['eways_product_id'],
            'count' => (int) $product['quantity'],
        ], $products);

        try {
            $this->eways->replaceBasket($appItems, $token);
            $basket = $this->eways->basketDetails($user, $token);
            $total = (int) round((float) ($basket['payingPrice'] ?? array_sum(array_column($products, 'line_total'))));

            if ($deposit < $total) {
                return [
                    'ok' => false,
                    'status' => 402,
                    'message' => 'سپرده ایویز برای ثبت این سفارش کافی نیست.',
                    'insufficient_deposit' => true,
                    'deposit' => $deposit,
                    'required' => $total,
                    'deposit_url' => config('services.eways.deposit_url'),
                ];
            }

            $buy = $this->eways->buy($user, $basket, $token);
            $ewaysOrderId = (int) ($buy['orderId'] ?? 0);
            if ($ewaysOrderId < 1) {
                return ['ok' => false, 'status' => 422, 'message' => $this->eways->description($buy, 'ثبت سفارش در ایویز انجام نشد.')];
            }

            $localOrderId = DB::transaction(function () use ($products, $user, $buy, $ewaysOrderId, $total, $remoteProducts): int {
                $orderId = DB::table('orders')->insertGetId([
                    'total_amount' => $total,
                    'status' => 'registered',
                    'order_source' => 'eways',
                    'eways_user_id' => (int) ($user['userId'] ?? 0),
                    'eways_order_id' => $ewaysOrderId,
                    'eways_request_id' => (string) ($buy['reqId'] ?? ''),
                    'created_at' => now(),
                ]);
                foreach ($products as $product) {
                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'video_id' => $product['id'],
                        'quantity' => $product['quantity'],
                        'unit_price' => $product['unit_price'],
                        'line_total' => $product['line_total'],
                        'created_at' => now(),
                    ]);
                    $remote = $remoteProducts[(int) $product['eways_product_id']];
                    $remaining = max(0, (int) ($remote['stock'] ?? 0) - $product['quantity']);
                    DB::table('videos')->where('id', $product['id'])->update([
                        'price' => $product['unit_price'],
                        'stock_remaining' => $remaining,
                        'stock_total' => DB::raw('GREATEST(stock_total, '.(int) ($remote['stock'] ?? 0).')'),
                    ]);
                }
                return $orderId;
            });

            return ['ok' => true, 'status' => 200, 'order_id' => $localOrderId, 'eways_order_id' => $ewaysOrderId, 'total_amount' => $total, 'message' => 'سفارش با موفقیت در ایویز ثبت شد.'];
        } finally {
            try {
                $this->eways->replaceBasket($originalItems, $token);
            } catch (\Throwable) {
                // Restoration failure is non-fatal after the main operation.
            }
        }
    }

    private function submitLocal(array $products): array
    {
        $total = array_sum(array_column($products, 'line_total'));
        $orderId = DB::transaction(function () use ($products, $total): int {
            $orderId = DB::table('orders')->insertGetId([
                'total_amount' => $total,
                'status' => 'registered',
                'order_source' => 'local',
                'created_at' => now(),
            ]);
            foreach ($products as $product) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'video_id' => $product['id'],
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_price'],
                    'line_total' => $product['line_total'],
                    'created_at' => now(),
                ]);
                DB::table('videos')->where('id', $product['id'])->decrement('stock_remaining', $product['quantity']);
            }
            return $orderId;
        });

        return ['ok' => true, 'status' => 200, 'order_id' => $orderId, 'total_amount' => $total, 'message' => 'سفارش با موفقیت ثبت شد.'];
    }

    private function tierPrice(int $videoId, int $quantity, int $basePrice): int
    {
        $price = DB::table('price_tiers')
            ->where('video_id', $videoId)
            ->where('min_qty', '<=', $quantity)
            ->orderByDesc('min_qty')
            ->value('unit_price');
        return $price === null ? $basePrice : (int) $price;
    }
}
