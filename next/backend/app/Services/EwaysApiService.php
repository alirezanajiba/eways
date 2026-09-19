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

        return $token ? $request->withToken($token) : $request;
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
        $response = $this->client($token)->get($this->endpoint('/api/service/v{version}/user/GetProfile'));
        return $this->normalizeResponse($response->json(), $response->successful(), $response->status());
    }

    public function product(int $productId, ?string $token = null): array
    {
        $request = $token ? $this->client($token) : $this->client((string) config('services.eways.api_token'));
        $response = $request->get($this->endpoint('/api/service/v{version}/store/GetProduct/'.$productId));
        return $this->normalizeResponse($response->json(), $response->successful(), $response->status());
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
            $message = trim((string) ($normalized['description'] ?? $normalized['detail'] ?? $normalized['message'] ?? 'خطا در وب سرویس ایویز'));
            throw new RuntimeException($message.' (HTTP '.$statusCode.')');
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
