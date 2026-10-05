<?php

namespace App\Http\Middleware;

use App\Models\IpBan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();
        $bannedIps = Cache::remember('relic.ip_bans', 60, function () {
            return IpBan::query()
                ->where(function ($query) {
                    $query->whereNull('banned_until')->orWhere('banned_until', '>', now());
                })
                ->get()
                ->mapWithKeys(fn (IpBan $ban) => [$ban->ip => $ban->banned_until?->toIso8601String()])
                ->all();
        });
        if (array_key_exists($ip, $bannedIps)) {
            $until = $bannedIps[$ip];
            $message = $until
                ? 'Đăng nhập sai quá nhiều lần. Máy này bị khóa đến '.\Illuminate\Support\Carbon::parse($until)->timezone(config('app.timezone'))->format('H:i d/m/Y').'.'
                : 'Địa chỉ máy này đã bị khóa vĩnh viễn.';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $message], 403);
            }

            return response()->view('errors.403-ban', ['message' => $message], 403);
        }

        if (Auth::check() && Auth::user()->is_banned) {
            $reason = Auth::user()->ban_reason ?: 'Tài khoản đã bị khóa.';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $reason], 403);
            }
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Tài khoản bị khóa: ' . $reason);
        }

        return $next($request);
    }
}
