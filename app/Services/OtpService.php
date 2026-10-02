<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OtpService
{
    public function __construct(private SmsService $sms) {}

    public function send(string $phone, string $purpose = 'login'): string
    {
        $code = (string) random_int(100000, 999999);
        OtpCode::where('phone', $phone)->whereNull('consumed_at')->delete();
        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->sms->send($phone, 'Relic OTP: ' . $code . ' (10 phút)');

        return $code;
    }

    public function verify(string $phone, string $code): User
    {
        $row = OtpCode::where('phone', $phone)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $row || $row->expires_at->isPast() || ! Hash::check($code, $row->code_hash)) {
            throw new \RuntimeException('Mã OTP không đúng hoặc đã hết hạn.');
        }

        $row->update(['consumed_at' => now()]);

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            $user = User::create([
                'name' => 'User ' . substr($phone, -4),
                'email' => 'otp' . $phone . '@users.relic.local',
                'password' => Str::password(24),
                'phone' => $phone,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'role' => 'customer',
            ]);
        } else {
            $user->forceFill([
                'phone_verified_at' => now(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        return $user;
    }
}
