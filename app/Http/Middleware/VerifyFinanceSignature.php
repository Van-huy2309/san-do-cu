<?php

namespace App\Http\Middleware;

use App\Services\FinanceSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyFinanceSignature
{
    public function __construct(private FinanceSignature $signatures) {}

    public function handle(Request $request, Closure $next): Response
    {
        $keyId = (string) $request->header('X-Relic-Key');
        $timestamp = (string) $request->header('X-Relic-Timestamp');
        $nonce = (string) $request->header('X-Relic-Nonce');
        $signature = (string) $request->header('X-Relic-Signature');
        $expectedKey = (string) config('finance_api.key_id');
        $secret = (string) config('finance_api.secret');
        $previous = (string) config('finance_api.secret_previous');
        $body = $request->getContent();

        if (
            $expectedKey === ''
            || $secret === ''
            || strlen($keyId) !== strlen($expectedKey)
            || ! hash_equals($expectedKey, $keyId)
            || strlen($timestamp) !== 10
            || ! ctype_digit($timestamp)
            || abs(time() - (int) $timestamp) > (int) config('finance_api.skew')
            || ! preg_match('/^[a-f0-9]{32}$/', $nonce)
            || strlen($body) > 65536
            || ! str_contains((string) $request->header('Content-Type'), 'application/json')
        ) {
            return $this->reject($request);
        }

        $parts = [$timestamp, $nonce, $request->getMethod(), $request->getPathInfo(), $body];
        $valid = $this->signatures->matches($secret, $signature, ...$parts)
            || ($previous !== '' && $this->signatures->matches($previous, $signature, ...$parts));
        if (! $valid) {
            return $this->reject($request);
        }

        try {
            $nonces = DB::connection('finance');
            $nonces->table('api_nonces')->where('expires_at', '<', now())->delete();
            $stored = $nonces->table('api_nonces')->insertOrIgnore([
                'nonce' => $nonce,
                'expires_at' => now()->addSeconds(((int) config('finance_api.skew')) * 2),
            ]);
        } catch (\Throwable $e) {
            Log::error('Finance nonce store failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Unavailable'], 503);
        }

        if ((int) $stored === 0) {
            return $this->reject($request);
        }

        return $next($request);
    }

    private function reject(Request $request): Response
    {
        Log::warning('Finance packet rejected', ['path' => $request->getPathInfo()]);

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
