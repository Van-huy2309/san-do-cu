<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\DenyAdminShop;
use App\Http\Middleware\EnsureNotBanned;
use App\Http\Middleware\LimitTraffic;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            if (! \App\Support\RelicRole::isFinance()) {
                return;
            }

            \Illuminate\Support\Facades\Route::middleware(\App\Http\Middleware\VerifyFinanceSignature::class)
                ->prefix('internal/finance')
                ->group(base_path('routes/finance.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->appendToGroup('web', EnsureNotBanned::class);
        $middleware->appendToGroup('web', LimitTraffic::class);
        $middleware->appendToGroup('api', EnsureNotBanned::class);
        $middleware->appendToGroup('api', LimitTraffic::class);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'deny.admin.shop' => DenyAdminShop::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->validateCsrfTokens(except: [
            'ghn/webhook',
            'payment/momo/ipn',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Phiên làm việc hết hạn. Vui lòng tải lại trang.',
                ], 419);
            }

            $target = url()->previous();
            if ($target === $request->fullUrl() || $target === '') {
                $target = route('home');
            }

            return redirect()
                ->to($target)
                ->with('error', 'Phiên làm việc hết hạn. Vui lòng tải lại trang rồi thử lại.');
        });
    })->create();
