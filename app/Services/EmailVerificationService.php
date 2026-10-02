<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EmailVerificationService
{
    public function issue(User $user): string
    {
        $code = (string) random_int(100000, 999999);
        $user->forceFill([
            'email_verify_code_hash' => Hash::make($code),
            'email_verify_expires_at' => now()->addMinutes(15),
        ])->save();

        return $code;
    }

    public function confirm(User $user, string $code): void
    {
        $code = trim($code);
        if (
            ! $user->email_verify_code_hash
            || ! $user->email_verify_expires_at
            || $user->email_verify_expires_at->isPast()
            || ! Hash::check($code, $user->email_verify_code_hash)
        ) {
            throw new \RuntimeException('Mã xác thực không đúng hoặc đã hết hạn.');
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'email_verify_code_hash' => null,
            'email_verify_expires_at' => null,
        ])->save();
    }
}
