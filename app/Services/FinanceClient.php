<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FinanceClient
{
    public function __construct(private FinanceSignature $signatures) {}

    public function enabled(): bool
    {
        return (string) config('finance_api.url') !== ''
            && (string) config('finance_api.key_id') !== ''
            && (string) config('finance_api.secret') !== '';
    }

    public function post(string $path, array $payload): array
    {
        $secret = (string) config('finance_api.secret');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $signature = $this->signatures->sign($secret, $timestamp, $nonce, 'POST', $path, $body);
        $url = rtrim((string) config('finance_api.url'), '/').$path;

        try {
            $response = Http::timeout(4)
                ->connectTimeout(2)
                ->withoutRedirecting()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Relic-Key' => (string) config('finance_api.key_id'),
                    'X-Relic-Timestamp' => $timestamp,
                    'X-Relic-Nonce' => $nonce,
                    'X-Relic-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Service ngân hàng không phản hồi.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Service ngân hàng từ chối gói tin (HTTP '.$response->status().').');
        }

        $json = $response->json();
        if (! is_array($json) || empty($json['ok'])) {
            throw new RuntimeException('Service ngân hàng trả gói tin không hợp lệ.');
        }

        return $json;
    }
}
