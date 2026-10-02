<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
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
