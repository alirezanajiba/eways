<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EwaysApiService
{
    public function __construct(
        private readonly string $baseUrl = '',
        private readonly string $version = '1',
    ) {
    }

    private function client(?string $token = null, array $headers = []): PendingRequest
    {
        $base = rtrim($this->baseUrl ?: (string) config('services.eways.base_url'), '/');

        $request = Http::baseUrl($base)
            ->acceptJson()
            ->asJson()
            ->timeout(25)
            ->connectTimeout(8)
            ->withHeaders($headers);

        return $token ? $request->withToken(preg_replace('/^Bearer\s+/i', '', trim($token)) ?: $token) : $request;
    }

    private function endpoint(string $path): string
    {
        return str_replace('{version}', rawurlencode($this->version ?: (string) config('services.eways.version', '1')), $path);
    }

    public function login(string $username, string $password): array
    {
        $appKey = (string) config('services.eways.app_key', 'JXZYtqDmdPqpHkYL');

        $response = $this->client(null, [
            'appKey' => $appKey,
            'lang' => 'FA',
        ])->post($this->endpoint('/api/service/v{version}/user/login'), [
            'AppKey' => $appKey,
            'Info' => ' deviceName : Eways Video Web',
            'Password' => $password,
            'RememberMe' => true,
            'Type' => 8,
            'UserName' => $username,
            'TraceCode' => '9A4DFE50-ADD4-4F26-8F90-FEA6691CF415',
        ]);

        return $this->normalizeResponse($response->json(), $response->successful(), $response->status());
    }

    public function profile(string $token): array
    {
        return $this->request('GET', '/api/service/v{version}/user/GetProfile', null, $token);
    }

    public function product(int $productId, ?string $token = null): array
    {
        return $this->request('GET', '/api/service/v{version}/store/GetProduct/'.$productId, null, $token ?: $this->serverToken());
    }

    public function basketDetails(array $user, string $token): array
    {
        return $this->request('POST', '/api/service/v{version}/store/GetBasketDetails', [
            'type' => 0,
            'couponCode' => null,
            'state' => $user['stateId'] ?? null,
            'city' => $user['townId'] ?? null,
        ], $token);
    }

    public function replaceBasket(array $items, string $token): void
    {
        $this->request('GET', '/api/service/v{version}/store/RemoveBasketItems', null, $token);

        foreach ($items as $item) {
            $productId = (int) ($item['productId'] ?? 0);
            $count = (int) ($item['count'] ?? 0);
            if ($productId < 1 || $count < 1) {
                continue;
            }

            $result = $this->request('POST', '/api/service/v{version}/store/AddToBasket', [
                'productId' => $productId,
                'count' => $count,
                'categoryId' => null,
            ], $token);

            if (!array_key_exists('items', $result)) {
                throw new RuntimeException($this->description($result, 'ساخت سبد خرید ایویز انجام نشد.'));
            }
        }
    }

    public function buy(array $user, array $basket, string $token): array
    {
        return $this->request('POST', '/api/service/v{version}/store/Buy', [
            'type' => (int) ($basket['shippingType'] ?? 0),
            'deliveryAddress' => $user['address'] ?? '',
            'description' => 'سفارش ثبت شده از ایویز ویدئو',
            'couponCode' => null,
            'gateway' => 0,
            'gatewayType' => 0,
            'stateId' => $user['stateId'] ?? null,
            'cityId' => $user['townId'] ?? null,
            'zipCode' => $user['postCode'] ?? null,
            'periodTimeId' => null,
            'recipientName' => $user['fullName'] ?? trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? '')),
            'recipientCellPhone' => $user['mobile'] ?? null,
            'callbackUrl' => null,
        ], $token);
    }

    public function description(array $response, string $fallback): string
    {
        $description = trim((string) ($response['description'] ?? ''));
        return $description !== '' ? $description : $fallback;
    }

    private function request(string $method, string $path, ?array $payload, ?string $token): array
    {
        $response = $this->client($token)->send($method, $this->endpoint($path), $payload === null ? [] : ['json' => $payload]);
        return $this->normalizeResponse($response->json(), $response->successful(), $response->status());
    }

    private function serverToken(): string
    {
        $token = preg_replace('/^Bearer\s+/i', '', trim((string) config('services.eways.api_token', ''))) ?? '';
        if ($token === '') {
            throw new RuntimeException('توکن وب سرویس ایویز روی سرور تنظیم نشده است.');
        }
        return $token;
    }

    private function normalizeResponse(mixed $payload, bool $successful, int $statusCode): array
    {
        if (!is_array($payload)) {
            throw new RuntimeException('پاسخ وب سرویس ایویز قابل پردازش نیست.');
        }

        $normalized = $this->normalizeKeys($payload);
        if (isset($normalized['data']) && is_array($normalized['data']) && !array_is_list($normalized['data'])) {
            $normalized = array_replace($normalized['data'], $normalized);
        }

        if (!$successful) {
            $message = trim((string) ($normalized['description'] ?? $normalized['detail'] ?? $normalized['message'] ?? $normalized['title'] ?? 'خطا در وب سرویس ایویز'));
            if ($message === '' && !empty($normalized['errors']) && is_array($normalized['errors'])) {
                $parts = [];
                array_walk_recursive($normalized['errors'], static function (mixed $value) use (&$parts): void {
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        $parts[] = trim((string) $value);
                    }
                });
                $message = implode('، ', array_unique($parts));
            }
            throw new RuntimeException(($message ?: 'خطا در وب سرویس ایویز').' (HTTP '.$statusCode.')');
        }

        return $normalized;
    }

    private function normalizeKeys(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->normalizeKeys($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[is_string($key) ? lcfirst($key) : $key] = $this->normalizeKeys($item);
        }
        return $normalized;
    }
}
