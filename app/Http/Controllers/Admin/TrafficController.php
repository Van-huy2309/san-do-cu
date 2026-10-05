<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpBan;
use App\Models\TrafficAlert;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TrafficController extends Controller
{
    public function index()
    {
        $alerts = TrafficAlert::query()->with('user:id,name,email')->latest('id')->limit(40)->get();
        $ips = IpBan::query()
            ->where(function ($query) {
                $query->whereNull('banned_until')->orWhere('banned_until', '>', now());
            })
            ->latest('id')
            ->limit(40)
            ->get();
        $accounts = User::query()->where('is_banned', true)->latest('banned_at')->limit(40)->get(['id', 'name', 'email', 'banned_at']);

        return view('admin.traffic.index', compact('alerts', 'ips', 'accounts'));
    }

    public function banUser(User $user)
    {
        abort_if($user->isAdmin() || $user->id === Auth::id(), 403, 'Không khóa tài khoản quản trị.');

        $user->update([
            'is_banned' => true,
            'ban_reason' => null,
            'banned_at' => now(),
        ]);

        return back()->with('success', 'Đã khóa vĩnh viễn tài khoản '.$user->email.'.');
    }

    public function banIp(Request $request)
    {
        $data = $request->validate([
            'ip' => ['required', 'ip'],
        ]);
        abort_if($data['ip'] === $request->ip(), 403, 'Không khóa IP của chính máy quản trị đang dùng.');

        IpBan::query()->updateOrCreate(
            ['ip' => $data['ip']],
            ['banned_until' => null, 'source' => 'manual']
        );
        Cache::forget('relic.ip_bans');

        return back()->with('success', 'Đã khóa vĩnh viễn IP '.$data['ip'].'.');
    }

    public function liftUser(User $user)
    {
        $user->update([
            'is_banned' => false,
            'ban_reason' => null,
            'banned_at' => null,
        ]);

        return back()->with('success', 'Đã mở khóa tài khoản '.$user->email.'.');
    }

    public function liftIp(IpBan $ban)
    {
        $ban->delete();
        Cache::forget('relic.ip_bans');

        return back()->with('success', 'Đã mở khóa IP '.$ban->ip.'.');
    }
}
