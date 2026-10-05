<?php

namespace App\Http\Middleware;

use App\Models\TrafficAlert;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class LimitTraffic
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->exempt($request)) {
            return $next($request);
        }

        $user = $request->user();
        $limits = $user
            ? [
                ['account:'.$user->id, config('traffic.account.max'), config('traffic.account.seconds'), 'account'],
                ['account-burst:'.$user->id, config('traffic.account_burst.max'), config('traffic.account_burst.seconds'), 'account'],
            ]
            : [
                ['guest:'.$request->ip(), config('traffic.guest.max'), config('traffic.guest.seconds'), 'guest'],
                ['guest-burst:'.$request->ip(), config('traffic.guest_burst.max'), config('traffic.guest_burst.seconds'), 'guest'],
            ];

        foreach ($limits as [$key, $max, $seconds, $scope]) {
            if (RateLimiter::tooManyAttempts($key, (int) $max)) {
                $this->alert($request, $scope, (int) RateLimiter::attempts($key));

                return $this->blocked($request);
            }
        }

        foreach ($limits as [$key, $max, $seconds]) {
            RateLimiter::hit($key, (int) $seconds);
        }

        return $next($request);
    }

    private function exempt(Request $request): bool
    {
        return $request->is('up', 'ghn/webhook', 'payment/momo/ipn');
    }

    private function alert(Request $request, string $scope, int $hits): void
    {
        $userId = $request->user()?->id;
        $dedupe = 'traffic-alert:'.($userId ?: $request->ip()).':'.$scope;
        if (! Cache::add($dedupe, 1, now()->addMinutes(10))) {
            return;
        }

        TrafficAlert::query()->create([
            'user_id' => $userId,
            'ip' => (string) $request->ip(),
            'scope' => $scope,
            'hits' => $hits,
            'sample_path' => substr((string) $request->path(), 0, 180),
        ]);
    }

    private function blocked(Request $request): Response
    {
        $message = 'Bạn gửi quá nhiều yêu cầu trong thời gian ngắn. Đợi khoảng một phút rồi thử lại.';
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], 429);
        }

        return response()->view('errors.429', ['message' => $message], 429);
    }
}
