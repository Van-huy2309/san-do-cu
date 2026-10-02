<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class E2eAuditCommand extends Command
{
    protected $signature = 'relic:e2e-audit';

    protected $description = 'Dọn danh mục ẩn rỗng và smoke HTTP live (kernel + Apache).';

    public function handle(): int
    {
        $deleted = Category::query()->where('is_active', false)->whereDoesntHave('listings')->delete();
        Cache::forget('relic.categories.active');
        $this->info("Đã xóa {$deleted} danh mục ẩn không còn tin.");

        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $failed = 0;
        foreach (['/' => 200, '/cho' => 200, '/login' => 200, '/register' => 200, '/gio-hang' => [200, 302]] as $uri => $expect) {
            $request = Request::create($uri, 'GET');
            $response = $kernel->handle($request);
            $code = $response->getStatusCode();
            $ok = is_array($expect) ? in_array($code, $expect, true) : $code === $expect;
            $this->line(($ok ? 'OK ' : 'ERR')." kernel {$uri} -> {$code}");
            if (! $ok) {
                $failed++;
            }
            $kernel->terminate($request, $response);
        }

        $base = rtrim((string) config('app.url'), '/');
        try {
            $home = Http::timeout(12)->withOptions(['allow_redirects' => false])->get($base.'/');
            $ok = $home->status() === 200;
            $this->line(($ok ? 'OK ' : 'ERR')." apache {$base}/ -> ".$home->status());
            if (! $ok) {
                $failed++;
            }
        } catch (\Throwable $e) {
            $this->error('Apache không tới được: '.$e->getMessage());
            $failed++;
        }

        try {
            $momo = app(\App\Services\MomoService::class)->pingCreate(10000);
            $code = $momo['resultCode'] ?? null;
            $ok = isset($momo['payUrl']) && (string) $code === '0';
            $this->line(($ok ? 'OK ' : 'ERR').' momo create 10000 -> resultCode='.(string) ($code ?? 'n/a').(isset($momo['payUrl']) ? ' payUrl' : ' '.($momo['message'] ?? '')));
            if (! $ok) {
                $failed++;
            }
        } catch (\Throwable $e) {
            $this->error('MoMo không tới được: '.$e->getMessage());
            $failed++;
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
