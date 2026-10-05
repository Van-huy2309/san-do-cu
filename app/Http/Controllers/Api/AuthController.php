<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginJail;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|max:120|unique:users,email',
            'password' => 'required|min:8',
        ]);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
            'role' => 'customer',
        ]);

        return $this->tokenResponse($user);
    }

    public function login(Request $request, LoginJail $jail)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            if ($jail->hit($request->ip())) {
                return response()->json(['message' => $jail->message($request->ip())], 403);
            }

            return response()->json(['message' => 'Email hoặc mật khẩu không đúng.'], 422);
        }
        $jail->clear($request->ip());
        if ($user->is_banned) {
            return response()->json(['message' => 'Tài khoản bị khóa.'], 403);
        }

        return $this->tokenResponse($user);
    }

    public function sendOtp(Request $request, OtpService $otp)
    {
        $data = $request->validate(['phone' => ['required', 'regex:/^0\d{9}$/']]);
        $code = $otp->send($data['phone']);
        $payload = ['message' => 'Đã gửi OTP.'];
        if (config('services.sms.driver', 'log') === 'log') {
            $payload['debug_code'] = $code;
        }

        return response()->json($payload);
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
            return response()->json(['message' => $e->getMessage()], 422);
        }
        if ($user->is_banned) {
            return response()->json(['message' => 'Tài khoản bị khóa.'], 403);
        }

        return $this->tokenResponse($user);
    }

    public function google(Request $request)
    {
        $data = $request->validate(['id_token' => 'required|string']);
        try {
            $social = Socialite::driver('google')->stateless()->userFromToken($data['id_token']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Google token không hợp lệ.'], 422);
        }
        $email = $social->getEmail() ?: ('google' . $social->getId() . '@users.relic.local');
        $user = User::where('google_id', $social->getId())->orWhere('email', $email)->first();
        if (! $user) {
            $user = User::create([
                'name' => $social->getName() ?: 'Google User',
                'email' => $email,
                'password' => Str::password(24),
                'email_verified_at' => now(),
                'google_id' => $social->getId(),
                'role' => 'customer',
            ]);
        } else {
            $user->forceFill(['google_id' => $social->getId(), 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        }

        return $this->tokenResponse($user);
    }

    public function apple(Request $request)
    {
        $data = $request->validate(['id_token' => 'required|string']);
        $parts = explode('.', $data['id_token']);
        if (count($parts) < 2) {
            return response()->json(['message' => 'Apple token không hợp lệ.'], 422);
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '{}', true);
        $sub = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;
        if (! $sub) {
            return response()->json(['message' => 'Apple token không hợp lệ.'], 422);
        }
        $user = User::where('apple_id', $sub)->when($email, fn ($q) => $q->orWhere('email', $email))->first();
        if (! $user) {
            $user = User::create([
                'name' => 'Apple User',
                'email' => $email ?: ('apple' . $sub . '@users.relic.local'),
                'password' => Str::password(24),
                'email_verified_at' => now(),
                'apple_id' => $sub,
                'role' => 'customer',
            ]);
        } else {
            $user->forceFill(['apple_id' => $sub, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        }

        return $this->tokenResponse($user);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'city' => $user->city,
            'lat' => $user->lat,
            'lng' => $user->lng,
            'kyc' => $user->kyc_status,
            'wallet' => $user->wallet_balance,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    private function tokenResponse(User $user)
    {
        $token = $user->createToken('flutter')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }
}
