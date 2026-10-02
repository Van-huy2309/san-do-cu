<?php

namespace App\Ai;

class Text
{
    public static function fold(string $text): string
    {
        $s = mb_strtolower(trim($text));
        $from = ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ'];
        $to = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];
        $s = str_replace($from, $to, $s);
        $s = str_replace(['&', '+', '/', '\\', ',', ';', ':', '!', '?', '.', '"', '\'', '(', ')', '[', ']', '{', '}'], ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    public static function tokens(string $folded): array
    {
        if ($folded === '') {
            return [];
        }
        $parts = preg_split('/\s+/u', $folded) ?: [];

        return array_values(array_filter($parts, fn ($t) => $t !== ''));
    }

    public static function ngrams(array $tokens, int $max = 2): array
    {
        $out = $tokens;
        if ($max >= 2) {
            for ($i = 0; $i < count($tokens) - 1; $i++) {
                $out[] = $tokens[$i].'_'.$tokens[$i + 1];
            }
        }

        return $out;
    }
}
