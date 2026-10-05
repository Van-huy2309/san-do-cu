<?php

namespace App\Services;

class FinanceSignature
{
    public function sign(string $secret, string $timestamp, string $nonce, string $method, string $path, string $body): string
    {
        return hash_hmac('sha256', $this->canonical($timestamp, $nonce, $method, $path, $body), $secret);
    }

    public function matches(string $secret, string $provided, string $timestamp, string $nonce, string $method, string $path, string $body): bool
    {
        if ($secret === '' || strlen($provided) !== 64 || ! ctype_xdigit($provided)) {
            return false;
        }

        return hash_equals($this->sign($secret, $timestamp, $nonce, $method, $path, $body), strtolower($provided));
    }

    public function canonical(string $timestamp, string $nonce, string $method, string $path, string $body): string
    {
        return $timestamp."\n".$nonce."\n".strtoupper($method)."\n".$path."\n".hash('sha256', $body);
    }
}
