<?php

namespace App\Ai;

use App\Models\Brand;
use App\Models\Category;

class Engine
{
    private ?array $model = null;

    public function __construct(
        private Classifier $classifier,
        private Trainer $trainer,
    ) {}

    public function understand(string $message): array
    {
        $raw = trim($message);
        $expand = Lexicon::expand($raw);
        $expanded = $expand['expanded'];
        $folded = Text::fold($expanded);

        if ($this->isGreeting($folded, $expand['aliases'])) {
            return $this->result('greet', 1, $raw, $expand, []);
        }

        if ($this->isChitchat($folded)) {
            return $this->result('chitchat', 0.96, $raw, $expand, []);
        }

        $model = $this->model();
        $pred = $this->classifier->predict($model, $raw);
        $intent = $pred['intent'];
        $conf = $pred['confidence'];

        if ($this->isFollowUp($folded)) {
            $intent = 'followup';
            $conf = max($conf, 0.85);
        } elseif ($this->isCatalogBrowse($folded) || $expand['aliases'] !== [] || $this->looksLikeProduct($folded)) {
            $intent = 'search';
            $conf = max($conf, 0.92);
        } elseif (preg_match('/\b(may dang ban|hang dang ban|san pham|tim may|xem hang|co may nao|liet ke)\b/u', $folded)) {
            $intent = 'search';
            $conf = max($conf, 0.9);
        }

        $tokens = $this->searchTokens($folded);
        $price = $this->parsePrice($folded);
        $brand = $this->brandHint($folded);
        $category = $this->categoryHint($folded);

        // Không biến tán gẫu / intent mềm thành search chỉ vì còn vài token linh tinh.
        if ($intent === 'chitchat') {
            return $this->result('chitchat', max($conf, 0.9), $raw, $expand, [], $price, $brand, $category);
        }

        if ($intent === 'greet' && ($expand['aliases'] !== [] || $this->looksLikeProduct($folded) || $category || $brand)) {
            $intent = 'search';
        }
        if (in_array($intent, ['help', 'who', 'thanks', 'bye'], true)
            && ($expand['aliases'] !== [] || $this->looksLikeProduct($folded) || $category || $this->isCatalogBrowse($folded))) {
            $intent = 'search';
        }

        $strongProduct = $expand['aliases'] !== []
            || $this->looksLikeProduct($folded)
            || $category
            || $brand
            || $this->isCatalogBrowse($folded)
            || $price !== [];

        if ($conf < 0.35 && $strongProduct) {
            $intent = 'search';
            $conf = 0.6;
        }

        // Độ tin thấp + không tín hiệu hàng → coi là chitchat / unknown, không dump catalog.
        if ($conf < 0.28 && ! $strongProduct && ! in_array($intent, [
            'escrow', 'payment', 'commission', 'kyc', 'sell', 'login', 'account',
            'gps', 'wallet', 'dispute', 'chat', 'alerts', 'orders', 'safety', 'escalate',
            'ops', 'help', 'who', 'thanks', 'bye', 'greet', 'followup',
        ], true)) {
            $intent = 'chitchat';
            $conf = 0.55;
        }

        return $this->result($intent, $conf, $raw, $expand, $tokens, $price, $brand, $category);
    }

    private function result(
        string $intent,
        float $conf,
        string $raw,
        array $expand,
        array $tokens,
        array $price = [],
        ?string $brand = null,
        ?string $category = null,
    ): array {
        return [
            'intent' => $intent,
            'confidence' => $conf,
            'original' => $raw,
            'expanded' => $expand['expanded'],
            'aliases' => $expand['aliases'],
            'search_tokens' => $tokens,
            'price' => $price,
            'brand_hint' => $brand,
            'category_hint' => $category,
        ];
    }

    private function isChitchat(string $folded): bool
    {
        if (preg_match('/\b(dep trai|dep gai|dep khong|xinh khong|ngau khong|khoe khong|khoe ko|an com chua|yeu chua|co ny|crush|buon|vui|met qua|buon ngu|chill|haha|hihi|kkk|lol|dua thoi|dua a|do dua)\b/u', $folded)) {
            return true;
        }
        if (preg_match('/\b(toi|t|minh|ban|m|cau).{0,16}\b(dep|xinh|ghe|ngau|xau|beo|gay)\b/u', $folded)) {
            return true;
        }
        if (preg_match('/\b(thoi tiet|mua chua|may gio|hom nay the nao|ban the nao)\b/u', $folded)) {
            return true;
        }
        // Câu rất ngắn, không hàng hóa.
        $tokens = Text::tokens($folded);
        if (count($tokens) <= 5 && ! $this->looksLikeProduct($folded) && ! $this->isCatalogBrowse($folded)) {
            if (preg_match('/\b(dep|xinh|ngau|khoe|yeu|buon|vui|met|ngu|an|choi|dua)\b/u', $folded)) {
                return true;
            }
        }

        return false;
    }

    private function isCatalogBrowse(string $folded): bool
    {
        $wantsList = (bool) preg_match('/\b(xem|tim|muon|can|liet ke|show|list|dang ban|hien tai|dang co|gioi thieu|goi y)\b/u', $folded)
            || (bool) preg_match('/\bco\b.{0,24}\b(may|hang|san pham|dien thoai|iphone|laptop)\b/u', $folded);
        $aboutGoods = (bool) preg_match('/\b(san pham|mat hang|tin dang|may moc|hang hoa|dien thoai|iphone|ipad|macbook|laptop|samsung|xiaomi|sony|canon|tai nghe|may anh|dong ho|console|ps5|tablet)\b/u', $folded);

        return $wantsList && $aboutGoods;
    }

    private function isGreeting(string $folded, array $aliases): bool
    {
        if ($aliases !== []) {
            return false;
        }
        $compact = trim($folded);
        if (in_array($compact, Lexicon::greetPhrases(), true)) {
            return true;
        }
        $tokens = Text::tokens($folded);
        if ($tokens === [] || count($tokens) > 4) {
            return false;
        }
        $greet = ['hello', 'hi', 'hey', 'yo', 'alo', 'halo', 'helo', 'chao', 'xin', 'ban', 'relic', 'shop', 'ad', 'morning', 'good', 'evening', 'afternoon', 'there'];

        return count(array_diff($tokens, $greet)) === 0
            && array_intersect($tokens, ['hello', 'hi', 'hey', 'yo', 'alo', 'halo', 'helo', 'chao', 'xin']) !== [];
    }

    private function isFollowUp(string $folded): bool
    {
        return (bool) preg_match('/\b(re hon|re nua|dat hon|xem them|cai dau|tin dau|may khac|con khong|cheaper|more)\b/u', $folded);
    }

    private function looksLikeProduct(string $folded): bool
    {
        return (bool) preg_match('/\b(iphone|ipad|macbook|samsung|laptop|sony|canon|airpods|xiaomi|dell|asus|ps5|switch|watch|may anh|dien thoai|tai nghe|dong ho)\b/u', $folded);
    }

    private function brandHint(string $folded): ?string
    {
        foreach (Lexicon::brandHints() as $needle => $brand) {
            if (str_contains($folded, $needle)) {
                return $brand;
            }
        }
        $row = Brand::query()->get()->first(fn (Brand $b) => str_contains($folded, Text::fold($b->name)));

        return $row?->name;
    }

    private function categoryHint(string $folded): ?string
    {
        $aliases = [
            'dien thoai' => 'Điện thoại',
            'smartphone' => 'Điện thoại',
            'may tinh bang' => 'Máy tính bảng',
            'tablet' => 'Máy tính bảng',
            'ipad' => 'Máy tính bảng',
            'laptop' => 'Laptop',
            'may anh' => 'Máy ảnh',
            'tai nghe' => 'Âm thanh',
            'loa' => 'Âm thanh',
            'dong ho' => 'Đồng hồ',
            'smartwatch' => 'Đồng hồ',
            'console' => 'Máy chơi game',
            'ps5' => 'Máy chơi game',
            'man hinh' => 'Màn hình',
            'phu kien' => 'Phụ kiện',
        ];
        // Không map từ "phone" lẻ trong câu tán gẫu — chỉ khi có ngữ cảnh hàng.
        if (preg_match('/\bphone\b/u', $folded) && preg_match('/\b(tim|xem|mua|may|hang|san pham|used|cu)\b/u', $folded)) {
            return 'Điện thoại';
        }
        foreach ($aliases as $needle => $name) {
            if (str_contains($folded, $needle)) {
                return $name;
            }
        }
        $row = Category::query()->where('is_active', true)->get()
            ->first(function (Category $c) use ($folded) {
                $name = Text::fold($c->name);
                $slug = Text::fold((string) $c->slug);
                if (mb_strlen($name) < 4) {
                    return false;
                }

                return str_contains($folded, $name) || (mb_strlen($slug) >= 4 && str_contains($folded, $slug));
            });

        return $row?->name;
    }

    private function searchTokens(string $folded): array
    {
        $clean = preg_replace('/\b(duoi|toi da|tren|tu|den|trieu|tr|cu|nghin)\b/u', ' ', $folded) ?? $folded;
        $clean = preg_replace('/\d+/', ' ', $clean) ?? $clean;
        $stop = [
            'toi', 'minh', 'ban', 'la', 'cua', 'cho', 'voi', 'va', 'thi', 'nhe', 'tim', 'kiem', 'mua', 'xem',
            'co', 'khong', 'ko', 'gi', 'nao', 'find', 'search', 'buy', 'relic', 'shop', 'may', 'cai',
            'muon', 'can', 'cac', 'nhung', 'san', 'pham', 'hang', 'hoa', 'hien', 'tai', 'dang', 'ban',
            'liet', 'ke', 'show', 'list', 'product', 'products', 'goi', 'y', 'giup', 'ho', 'oi',
            'dep', 'trai', 'gai', 'xinh', 'ngau', 'khoe', 'dua',
        ];
        $out = [];
        foreach (Text::tokens($clean) as $t) {
            if (mb_strlen($t) < 2 || in_array($t, $stop, true)) {
                continue;
            }
            $out[] = $t;
        }

        return array_values(array_unique(array_slice($out, 0, 8)));
    }

    private function parsePrice(string $folded): array
    {
        $toDong = function (string $num, string $unit): int {
            $n = (float) str_replace(',', '.', $num);

            return (int) round(match (true) {
                str_contains($unit, 'tr') || str_contains($unit, 'cu') => $n * 1_000_000,
                default => $n >= 1000 ? $n : $n * 1_000_000,
            });
        };
        $out = [];
        if (preg_match('/(duoi|toi da|max)\s*(\d+(?:[.,]\d+)?)\s*(trieu|tr|cu)?/u', $folded, $m)) {
            $out['max'] = $toDong($m[2], $m[3] ?? 'tr');
        }
        if (preg_match('/(tren|tu)\s*(\d+(?:[.,]\d+)?)\s*(trieu|tr|cu)?/u', $folded, $m)) {
            $out['min'] = $toDong($m[2], $m[3] ?? 'tr');
        }

        return $out;
    }

    private function model(): array
    {
        return $this->model ??= $this->trainer->load();
    }
}
