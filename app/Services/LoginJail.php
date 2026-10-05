<?php

namespace App\Services;

use App\Models\IpBan;
use App\Models\TrafficAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class LoginJail
{
    public function hit(string $ip): bool
    {
        if ($this->ignored($ip)) {
            return false;
        }

        $key = $this->key($ip);
        RateLimiter::hit($key, $this->findtime());
        $this->log('Relic login failed ip='.$ip);

        if (RateLimiter::attempts($key) < $this->maxretry()) {
            return false;
        }

        return $this->ban($ip);
    }

    public function clear(string $ip): void
    {
        RateLimiter::clear($this->key($ip));
    }

    public function message(string $ip): string
    {
        $ban = IpBan::query()->where('ip', $ip)->first();
        if ($ban && $ban->banned_until === null) {
            return 'Địa chỉ máy này đã bị khóa vĩnh viễn.';
        }

        $until = $ban?->banned_until ?? now()->addSeconds($this->bantime());

        return 'Đăng nhập sai quá nhiều lần. Máy này bị khóa đến '
            .$until->timezone(config('app.timezone'))->format('H:i d/m/Y').'.';
    }

    private function ban(string $ip): bool
    {
        $existing = IpBan::query()->where('ip', $ip)->first();
        if ($existing && $existing->source !== 'fail2ban' && $existing->banned_until === null) {
            return true;
        }

        $until = now()->addSeconds($this->bantime());
        IpBan::query()->updateOrCreate(
            ['ip' => $ip],
            ['banned_until' => $until, 'source' => 'fail2ban']
        );
        Cache::forget('relic.ip_bans');
        $this->log('Relic login ban ip='.$ip);

        TrafficAlert::query()->create([
            'user_id' => null,
            'ip' => $ip,
            'scope' => 'login',
            'hits' => $this->maxretry(),
            'sample_path' => '/login',
        ]);

        return true;
    }

    private function ignored(string $ip): bool
    {
        return in_array($ip, config('fail2ban.ignoreip', []), true);
    }

    private function key(string $ip): string
    {
        return 'fail2ban:login:'.$ip;
    }

    private function maxretry(): int
    {
        return max(1, (int) config('fail2ban.maxretry'));
    }

    private function findtime(): int
    {
        return max(60, (int) config('fail2ban.findtime'));
    }

    private function bantime(): int
    {
        return max(60, (int) config('fail2ban.bantime'));
    }

    private function log(string $line): void
    {
        Log::channel('fail2ban')->warning($line);
    }
}
