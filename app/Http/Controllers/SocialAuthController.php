<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OtpService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, ['google', 'apple'], true), 404);

        if ($provider === 'google' && ! config('services.google.client_id')) {
            return redirect()->route('login')->with('error', 'Chưa cấu hình GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET trong .env.');
        }
        if ($provider === 'apple' && ! config('services.apple.client_id')) {
            return redirect()->route('login')->with('error', 'Chưa cấu hình APPLE_CLIENT_ID / APPLE_CLIENT_SECRET trong .env.');
        }

        $driver = Socialite::driver($provider);
        if ($provider === 'apple') {
            $driver->scopes(['name', 'email']);
        }

        return $driver->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless(in_array($provider, ['google', 'apple'], true), 404);

        try {
            $social = Socialite::driver($provider)->user();
        } catch (Exception $e) {
            Log::warning('Social login failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return redirect()->route('login')->with('error', 'Đăng nhập ' . $provider . ' thất bại.');
        }

        $email = $social->getEmail() ?: ($provider . $social->getId() . '@users.relic.local');
        $user = User::query()
            ->when($provider === 'google', fn ($q) => $q->where('google_id', $social->getId()))
            ->when($provider === 'apple', fn ($q) => $q->where('apple_id', $social->getId()))
            ->first()
            ?: User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $social->getName() ?: 'Relic User',
                'email' => $email,
                'password' => Str::password(24),
                'email_verified_at' => now(),
                'role' => 'customer',
            ]);
        }

        $user->forceFill([
            $provider . '_id' => $social->getId(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($user->is_banned) {
            return redirect()->route('login')->with('error', 'Tài khoản bị khóa.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function sendOtp(Request $request, OtpService $otp)
    {
        $data = $request->validate(['phone' => ['required', 'regex:/^0\d{9}$/']]);
        $code = $otp->send($data['phone']);
        $payload = ['success' => 'Đã gửi OTP tới ' . $data['phone'] . '.'];
        if (config('services.sms.driver', 'log') === 'log') {
            $payload['success'] .= ' (môi trường log: ' . $code . ')';
        }

        return back()->with($payload)->withInput();
    }

    public function verifyOtp(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^0\d{9}$/'],
            'code' => ['required', 'digits:6'],
        ]);

        try {
            $user = $otp->verify($data['phone'], $data['code']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        if ($user->is_banned) {
            return redirect()->route('login')->with('error', 'Tài khoản bị khóa.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
