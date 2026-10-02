<?php

namespace App\Ai;

class Lexicon
{
    /** @return array<string, string> longest alias first */
    public static function aliases(): array
    {
        $map = [
            'iphone 15 pro max' => 'iphone 15 pro max',
            'iphone 14 pro max' => 'iphone 14 pro max',
            'iphone 13 pro max' => 'iphone 13 pro max',
            'iphone 12 pro max' => 'iphone 12 pro max',
            'iphone 15 pro' => 'iphone 15 pro',
            'iphone 14 pro' => 'iphone 14 pro',
            'iphone 13 pro' => 'iphone 13 pro',
            'macbook air m2' => 'macbook air m2',
            'macbook air m1' => 'macbook air m1',
            'macbook pro m1' => 'macbook pro m1',
            'airpods pro 2' => 'airpods pro 2',
            'airpods pro2' => 'airpods pro 2',
            'galaxy s24 ultra' => 'samsung s24 ultra',
            'galaxy s23 ultra' => 'samsung s23 ultra',
            'galaxy s24' => 'samsung s24',
            'galaxy s23' => 'samsung s23',
            'wh-1000xm5' => 'sony wh-1000xm5',
            'wh1000xm5' => 'sony wh-1000xm5',
            'wh-1000xm4' => 'sony wh-1000xm4',
            'sony a7 iii' => 'sony a7 iii',
            'sony a7iii' => 'sony a7 iii',
            'canon eos r10' => 'canon eos r10',
            'switch oled' => 'nintendo switch oled',
            'ps5 digital' => 'ps5 digital',
            'apple watch s8' => 'apple watch s8',
            'apple watch s9' => 'apple watch s9',
            'ipad air 5' => 'ipad air 5',
            'ipad air5' => 'ipad air 5',
            'dell xps 13' => 'dell xps 13',
            'asus tuf' => 'asus tuf',
            'ip15pm' => 'iphone 15 pro max',
            'ip14pm' => 'iphone 14 pro max',
            'ip13pm' => 'iphone 13 pro max',
            'ip12pm' => 'iphone 12 pro max',
            '15prm' => 'iphone 15 pro max',
            '14prm' => 'iphone 14 pro max',
            '13prm' => 'iphone 13 pro max',
            'ip15 pro' => 'iphone 15 pro',
            'ip14 pro' => 'iphone 14 pro',
            'ip13 pro' => 'iphone 13 pro',
            'ip15p' => 'iphone 15 pro',
            'ip14p' => 'iphone 14 pro',
            'ip13p' => 'iphone 13 pro',
            'ip16' => 'iphone 16',
            'ip15' => 'iphone 15',
            'ip14' => 'iphone 14',
            'ip13' => 'iphone 13',
            'ip12' => 'iphone 12',
            'ip11' => 'iphone 11',
            'ipxs' => 'iphone xs',
            'ipxr' => 'iphone xr',
            'iphone13' => 'iphone 13',
            'iphone14' => 'iphone 14',
            'iphone15' => 'iphone 15',
            'iphone12' => 'iphone 12',
            's24u' => 'samsung s24 ultra',
            's23u' => 'samsung s23 ultra',
            's22u' => 'samsung s22 ultra',
            's24 ultra' => 'samsung s24 ultra',
            's23 ultra' => 'samsung s23 ultra',
            's24' => 'samsung s24',
            's23' => 'samsung s23',
            's22' => 'samsung s22',
            'note20' => 'samsung note 20',
            'note 20' => 'samsung note 20',
            'xiaomi 13' => 'xiaomi 13',
            'mi13' => 'xiaomi 13',
            'mi 13' => 'xiaomi 13',
            'mba m2' => 'macbook air m2',
            'mba m1' => 'macbook air m1',
            'mbp m1' => 'macbook pro m1',
            'mba' => 'macbook air',
            'mbp' => 'macbook pro',
            'mb air' => 'macbook air',
            'mb pro' => 'macbook pro',
            'mac air' => 'macbook air',
            'mac pro' => 'macbook pro',
            'air m1' => 'macbook air m1',
            'air m2' => 'macbook air m2',
            'xps13' => 'dell xps 13',
            'xps 13' => 'dell xps 13',
            'tuf f15' => 'asus tuf f15',
            'ipad9' => 'ipad 9',
            'ipad 9' => 'ipad 9',
            'aw s8' => 'apple watch s8',
            'aw s9' => 'apple watch s9',
            'aws8' => 'apple watch s8',
            'watch s8' => 'apple watch s8',
            'ap2' => 'airpods pro 2',
            'app2' => 'airpods pro 2',
            'airpod pro 2' => 'airpods pro 2',
            'airpod 2' => 'airpods 2',
            'xm5' => 'sony wh-1000xm5',
            'xm4' => 'sony wh-1000xm4',
            'a7iii' => 'sony a7 iii',
            'a7 iii' => 'sony a7 iii',
            'r10' => 'canon eos r10',
            'nsw oled' => 'nintendo switch oled',
            'nsw' => 'nintendo switch',
            'ps5d' => 'ps5 digital',
            'may anh' => 'may anh',
            'dt' => 'dien thoai',
            'đt' => 'dien thoai',
            'laptop gaming' => 'laptop gaming',
            'may tinh bang' => 'may tinh bang',
            'tai nghe' => 'tai nghe',
            'find' => 'tim',
            'search' => 'tim',
            'buy' => 'mua',
            'cheap' => 'gia re',
            'used' => 'do cu',
            'phone' => 'dien thoai',
            'tablet' => 'may tinh bang',
            'camera' => 'may anh',
            'headphone' => 'tai nghe',
            'headset' => 'tai nghe',
            'console' => 'may choi game',
            'how to pay' => 'thanh toan escrow',
            'refund' => 'khieu nai hoan tien',
            'order' => 'don hang',
            'wallet' => 'vi relic',
            'login' => 'dang nhap',
            'sign in' => 'dang nhap',
        ];
        uksort($map, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $map;
    }

    public static function brandHints(): array
    {
        return [
            'iphone' => 'Apple',
            'ipad' => 'Apple',
            'macbook' => 'Apple',
            'airpods' => 'Apple',
            'apple watch' => 'Apple',
            'samsung' => 'Samsung',
            'galaxy' => 'Samsung',
            'xiaomi' => 'Xiaomi',
            'dell' => 'Dell',
            'asus' => 'Asus',
            'sony' => 'Sony',
            'canon' => 'Canon',
            'nintendo' => 'Nintendo',
            'lg' => 'LG',
            'jbl' => 'JBL',
            'tp-link' => 'TP-Link',
            'tplink' => 'TP-Link',
        ];
    }

    public static function greetPhrases(): array
    {
        return [
            'hello', 'hello relic', 'hi', 'hey', 'yo', 'alo', 'halo', 'good morning', 'good afternoon',
            'good evening', 'morning', 'xin chao', 'xin chao ban', 'xin chao relic', 'chao', 'chao ban',
            'chao relic', 'chao shop', 'chao ad', 'chào', 'helo', 'hallo', 'hii', 'hiii',
        ];
    }

    public static function expand(string $raw): array
    {
        $folded = Text::fold($raw);
        $folded = preg_replace('/<\s*=?\s*/', 'duoi ', $folded) ?? $folded;
        $folded = preg_replace('/>\s*=?\s*/', 'tren ', $folded) ?? $folded;
        $folded = preg_replace('/(\d+)\s*(trieu|tr|cu|cc)\b/u', '$1 trieu', $folded) ?? $folded;
        $folded = preg_replace('/(\d+)(tr|cu)\b/u', '$1 trieu', $folded) ?? $folded;

        $used = [];
        $aliases = self::aliases();
        foreach ($aliases as $from => $to) {
            $fromFold = Text::fold($from);
            if ($fromFold === '' || $fromFold === $to) {
                continue;
            }
            // Alias ngắn (dt, mba…) chỉ khớp cả từ — tránh “dep trai” → điện thoại.
            $hit = mb_strlen($fromFold) <= 3
                ? (bool) preg_match('/\b'.preg_quote($fromFold, '/').'\b/u', $folded)
                : str_contains($folded, $fromFold);
            if (! $hit) {
                continue;
            }
            if (mb_strlen($fromFold) <= 3) {
                $folded = preg_replace('/\b'.preg_quote($fromFold, '/').'\b/u', $to, $folded) ?? $folded;
            } else {
                $folded = str_replace($fromFold, $to, $folded);
            }
            $used[$from] = $to;
        }
        $folded = preg_replace('/\s+/u', ' ', $folded) ?? $folded;
        $folded = trim($folded);

        return [
            'expanded' => $folded,
            'aliases' => $used,
        ];
    }
}
