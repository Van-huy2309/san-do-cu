<?php

namespace App\Ai;

/**
 * Relic AI — lớp hội thoại dùng chung Care / Ops.
 * Giữ ngữ cảnh “offer” ngắn: hỏi số → trả số; khi cần list mới mở rộng.
 */
class Conversation
{
    public const OPS_OFFER = 'relic.ops.offer';

    public const CARE_OFFER = 'relic.care.offer';

    public static function isAffirm(string $q): bool
    {
        $t = trim($q);
        if ($t === '' || mb_strlen($t) > 28) {
            return false;
        }

        return (bool) preg_match('/^(co|co a|co nhe|ok|oke|okay|u|uh|uk|dc|duoc|yes|y|liet ke|xem|xem di|cho xem|tiep|them|muon|vang|da|chi tiet|day du)$/u', $t);
    }

    public static function isDeny(string $q): bool
    {
        $t = trim($q);

        return (bool) preg_match('/^(khong|ko|khong a|thoi|thoi nhe|huy|no|khong can|khong can dau|de sau)$/u', $t);
    }

    public static function isCountQuestion(string $q): bool
    {
        return str_contains($q, 'bao nhieu')
            || str_contains($q, 'co bao nhieu')
            || str_contains($q, 'so luong')
            || (bool) preg_match('/\b(bao nhieu|may)\b.{0,24}\b(user|nguoi|tk|tai khoan|don|tin|kyc)\b/u', $q);
    }

    public static function setOffer(string $key, array $payload): void
    {
        session([$key => $payload]);
    }

    public static function offer(string $key): ?array
    {
        $v = session($key);

        return is_array($v) ? $v : null;
    }

    public static function clear(string $key): void
    {
        session()->forget($key);
    }
}
