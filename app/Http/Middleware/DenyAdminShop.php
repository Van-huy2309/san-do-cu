<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DenyAdminShop
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'Tài khoản quản trị không mua hàng trên Relic. Dùng tài khoản thường để test giỏ hàng.');
        }

        return $next($request);
    }
}
