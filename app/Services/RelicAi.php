<?php

namespace App\Services;

use App\Ai\Engine;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;

class RelicAi
{
    public function __construct(
        private Engine $nlu,
        private RelicOpsAi $opsAi,
        private RelicCareAi $careAi,
    ) {}

    public function care(?User $user, string $message): array
    {
        $parsed = $this->nlu->understand($message);
        session(['relic.ai.last_nlu' => $parsed]);

        return $this->withProducts($this->careAi->reply($user, $message, $parsed, $this));
    }

    public function ops(User $admin, string $message): array
    {
        $parsed = $this->nlu->understand($message);
        $q = $parsed['expanded'] !== '' ? $parsed['expanded'] : $this->norm($message);

        if (session('relic.ops.offer') && $this->opsAi->isFollowUpReply($q)) {
            return $this->withProducts($this->opsAi->reply($message));
        }

        if ($parsed['intent'] === 'chitchat' || $this->looksLikeChitchat($q)) {
            return $this->withProducts($this->chitchat($admin, true));
        }

        // Lệnh vận hành luôn thắng — tránh “KYC chờ” bị hiểu thành tìm hàng.
        if ($this->isOpsCommand($q) || $parsed['intent'] === 'ops') {
            return $this->withProducts($this->opsAi->reply($message));
        }

        // Câu hỏi xem/tìm hàng trên sàn → chỉ khi có tín hiệu hàng rõ.
        if (($parsed['intent'] === 'search' || $parsed['intent'] === 'followup' || $this->shouldSearchCatalog($q, $parsed))
            && $this->hasStrongSearchSignal($q, $parsed)) {
            if ($parsed['intent'] === 'followup' && session('relic.ai.last_query')) {
                return $this->withProducts($this->followUp($q, $admin));
            }

            $out = $this->searchProducts($q, $admin, $this->searchOverrides($parsed));
            $out['reply'] = '[Ops] '.$out['reply'];
            $out['links'] = array_values(array_merge(
                $out['links'] ?? [],
                [['label' => 'Tin đăng admin', 'url' => route('admin.listings')]]
            ));

            return $this->withProducts($out);
        }

        return $this->withProducts($this->opsAi->reply($message));
    }

    private function isOpsCommand(string $q): bool
    {
        if ($this->orderCode($q)) {
            return true;
        }
        if (preg_match('/(?:tim(?:\s+kiem)?\s+)?(?:shop|seller|user|nick)\s+\S{2,}/u', $q)) {
            return true;
        }
        if (preg_match('/\b(bao nhieu|co bao nhieu).{0,20}\b(nguoi|user|thanh vien|don)\b/u', $q)) {
            return true;
        }

        if (preg_match('/\btk\b/u', $q) && preg_match('/\b(nguoi|user|dung|bao nhieu|tai khoan)\b/u', $q)) {
            return true;
        }

        return $this->has($q, [
            'uu tien', 'hang uu tien', 'dashboard', 'tong quan', 'lam gi truoc', 'tinh hinh', 'thong ke',
            'kyc', 'cccd', 'dinh danh',
            'khieu nai', 'dispute', 'tranh chap',
            'tai chinh', 'doi soat', 'hoa hong', 'commission', 'nap vi', 'rut vi', 'escrow',
            'spam', 'clone shop',
            'seal', 'nguon goc',
            'tin cho', 'cho duyet', 'kiem duyet', 'rui ro', 'duyet tin', 'moderation',
            'bao cao', 'report',
            'khoa nick', 'mo khoa', 'ban user', 'lua dao',
            'danh muc thieu', 'hang thieu', 'brand thieu',
            'soan ly do', 'mau ly do', 'draft',
            'van don', 'ghn',
            'nguoi dung', 'thanh vien', 'khach hang', 'user', 'users', 'tai khoan',
            'don hang', 'don moi', 'cac don', 'order', 'orders',
            'tat ca tin', 'cac tin', 'bao nhieu tin',
            'bao nhieu nguoi', 'bao nhieu user', 'bao nhieu tk', 'ai dang ky', 'cac shop',
            'seller', 'buyer', 'nguoi ban', 'nguoi mua',
            'help', 'giup gi', 'huong dan', 'lam duoc gi',
            'hello', 'xin chao', 'chao ops',
        ]);
    }

    public function searchOverrides(array $parsed): array
    {
        return array_filter([
            'tokens' => $parsed['search_tokens'] ?? [],
            'price' => $parsed['price'] ?? [],
            'brand' => $this->brandFromHint($parsed['brand_hint'] ?? null),
            'category' => $this->categoryFromHint($parsed['category_hint'] ?? null),
        ], fn ($v) => $v !== null && $v !== []);
    }

    public function shouldSearchCatalog(string $q, array $parsed): bool
    {
        if ($this->looksLikeChitchat($q) || ($parsed['intent'] ?? '') === 'chitchat') {
            return false;
        }
        if (($parsed['aliases'] ?? []) !== [] || ($parsed['brand_hint'] ?? null) || ($parsed['category_hint'] ?? null)) {
            return true;
        }
        if ($this->matchedCategory($q) || $this->matchedBrand($q)) {
            return true;
        }

        return (bool) preg_match('/\b(xem|tim|muon|liet ke).{0,40}\b(san pham|dien thoai|iphone|laptop|may|hang)\b/u', $q)
            || (bool) preg_match('/\b(san pham|dien thoai|iphone|laptop).{0,20}\b(hien tai|dang ban|dang co)\b/u', $q);
    }

    public function hasStrongSearchSignal(string $q, array $parsed): bool
    {
        if ($this->looksLikeChitchat($q)) {
            return false;
        }
        if (($parsed['aliases'] ?? []) !== []) {
            return true;
        }
        if (($parsed['brand_hint'] ?? null) || ($parsed['category_hint'] ?? null)) {
            return true;
        }
        if (($parsed['price'] ?? []) !== []) {
            return true;
        }
        if ($this->matchedBrand($q) || $this->matchedCategory($q)) {
            return true;
        }

        return (bool) preg_match('/\b(iphone|ipad|macbook|samsung|laptop|dien thoai|san pham|tai nghe|may anh|ps5|ip\d{2}|s2\d)\b/u', $q)
            || (bool) preg_match('/\b(xem|tim|muon|liet ke).{0,40}\b(may|hang|san pham|dien thoai|iphone|laptop)\b/u', $q)
            || (bool) preg_match('/\b(may dang ban|hang dang ban|co may nao)\b/u', $q);
    }

    private function looksLikeChitchat(string $q): bool
    {
        return (bool) preg_match('/\b(dep trai|dep gai|dep khong|xinh|ngau|khoe khong|an com|yeu chua|dua thoi|haha|lol)\b/u', $q)
            || (bool) preg_match('/\b(toi|t|minh|ban).{0,16}\b(dep|xinh|ghe|ngau|xau)\b/u', $q);
    }

    private function chitchat(?User $user, bool $ops): array
    {
        return [
            'reply' => 'Ops chuyên vận hành sàn (user, đơn, KYC…) chứ không chấm điểm đẹp trai. Hỏi việc đi — ví dụ “bao nhiêu user?”.',
            'links' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Người dùng', 'url' => route('admin.users')],
            ],
            'products' => [],
        ];
    }

    private function categoryFromHint(?string $name): ?Category
    {
        if (! $name) {
            return null;
        }

        return Category::query()
            ->where('name', $name)
            ->orWhere('slug', \Illuminate\Support\Str::slug($name))
            ->first();
    }

    private function withProducts(array $out): array
    {
        $out['products'] = $out['products'] ?? [];

        return $out;
    }

    private function brandFromHint(?string $name): ?Brand
    {
        if (! $name) {
            return null;
        }

        return Brand::query()->where('name', $name)->first();
    }

    public function starterProducts(int $n = 5): array
    {
        return $this->featuredCards($n);
    }

    public function searchProducts(string $q, ?User $user, array $override = []): array
    {
        $this->ensureCatalog();
        $price = array_merge($this->parsePrice($q), $override['price'] ?? []);
        $brand = $override['brand'] ?? $this->matchedBrand($q);
        $category = $override['category'] ?? $this->matchedCategory($q);
        $tokens = $override['tokens'] ?? $this->searchTokens($q);
        $tokens = $this->stripFacetTokens($tokens, $brand, $category);
        $tokens = $this->stripBrowseNoise($tokens);
        $seal = $override['seal'] ?? $this->has($q, ['seal', 'nguon goc', 'kiem dinh']);
        $sort = $override['sort'] ?? ($this->has($q, ['re', 're hon', 'gia re', 're nhat']) ? 'price_asc' : 'newest');
        $offset = (int) ($override['offset'] ?? 0);

        // Có danh mục/hãng rõ → ưu tiên lọc facet, không ép khớp hết token nhiễu.
        $matchAll = ! ($category || $brand) && count($tokens) <= 2;
        $rows = $this->runListingQuery($brand, $category, $price, $seal, $tokens, $sort, $offset, $matchAll);
        if ($rows->isEmpty() && $tokens !== []) {
            $rows = $this->runListingQuery($brand, $category, $price, $seal, $tokens, $sort, $offset, false);
        }
        if ($rows->isEmpty() && ($brand || $category)) {
            $rows = $this->runListingQuery($brand, $category, $price, $seal, [], $sort, $offset, true);
        }
        $relaxed = false;
        if ($rows->isEmpty()) {
            $rows = $this->featuredListings(5);
            $relaxed = true;
        }

        $label = $brand?->name ?: ($category?->name ?: implode(' ', $tokens));
        if ($label === '') {
            $label = 'máy đang bán';
        }

        session([
            'relic.ai.last_query' => $q,
            'relic.ai.last_ids' => $rows->pluck('id')->all(),
            'relic.ai.last_offset' => $offset,
            'relic.ai.last_filter' => [
                'tokens' => $tokens,
                'brand_id' => $brand?->id,
                'category_id' => $category?->id,
                'price' => $price,
                'seal' => $seal,
                'sort' => $sort,
            ],
        ]);

        $shop = array_filter([
            'q' => implode(' ', $tokens) ?: null,
            'brand' => $brand?->id,
            'category' => $category?->id,
            'min_price' => $price['min'] ?? null,
            'max_price' => $price['max'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($rows->isEmpty()) {
            return [
                'reply' => 'Hiện chưa có máy khớp yêu cầu. Bạn thử từ khóa khác trên Chợ.',
                'links' => [['label' => 'Mở chợ', 'url' => route('listings.index')]],
                'products' => [],
            ];
        }

        $who = $user?->name ? $user->name.', ' : '';
        $intro = $relaxed
            ? "{$who}chưa khớp đúng “{$label}” — đây là máy đang bán:"
            : "{$who}đây là vài máy “{$label}” đang bán:";

        return [
            'reply' => $intro,
            'links' => [['label' => 'Tất cả trên chợ', 'url' => route('listings.index', $shop)]],
            'products' => $rows->map(fn (Listing $l) => $this->productCard($l))->values()->all(),
        ];
    }

    private function stripBrowseNoise(array $tokens): array
    {
        $noise = [
            'san', 'pham', 'hang', 'hoa', 'hien', 'tai', 'dang', 'ban', 'cac', 'nhung',
            'muon', 'can', 'xem', 'tim', 'mua', 'co', 'liet', 'ke', 'goi', 'y',
        ];

        return array_values(array_filter($tokens, fn ($t) => ! in_array($t, $noise, true)));
    }

    private function runListingQuery(?Brand $brand, ?Category $category, array $price, bool $seal, array $tokens, string $sort, int $offset, bool $matchAll)
    {
        $query = Listing::public()->with(['brand', 'category', 'origin', 'images']);
        if ($brand) {
            $query->where('brand_id', $brand->id);
        }
        if ($category) {
            $query->where('category_id', $category->id);
        }
        if (! empty($price['min'])) {
            $query->where('price', '>=', $price['min']);
        }
        if (! empty($price['max'])) {
            $query->where('price', '<=', $price['max']);
        }
        if ($seal) {
            $query->whereHas('origin', fn ($o) => $o->where('status', 'verified'));
        }
        if ($tokens !== []) {
            $query->where(function ($outer) use ($tokens, $matchAll) {
                foreach ($tokens as $token) {
                    $like = '%'.$token.'%';
                    $group = function ($q) use ($like) {
                        $q->where('title', 'like', $like)
                            ->orWhere('model', 'like', $like)
                            ->orWhere('description', 'like', $like)
                            ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like))
                            ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like));
                    };
                    $matchAll ? $outer->where($group) : $outer->orWhere($group);
                }
            });
        }
        if ($sort === 'price_asc') {
            $query->orderBy('price');
        } elseif ($sort === 'price_desc') {
            $query->orderByDesc('price');
        } else {
            $query->latest('published_at')->orderByDesc('id');
        }

        return $query->skip($offset)->take(5)->get();
    }

    private function stripFacetTokens(array $tokens, ?Brand $brand, ?Category $category): array
    {
        $skip = [];
        foreach ([$brand?->name, $brand?->slug, $category?->name, $category?->slug] as $name) {
            if ($name) {
                $skip[] = \App\Ai\Text::fold((string) $name);
            }
        }
        $skip = array_values(array_filter($skip));
        if ($skip === []) {
            return $tokens;
        }

        return array_values(array_filter($tokens, function ($token) use ($skip) {
            foreach ($skip as $facet) {
                if ($token === $facet || str_contains($facet, $token) || str_contains($token, $facet)) {
                    return false;
                }
            }

            return true;
        }));
    }
    private function featuredListings(int $n = 5)
    {
        $this->ensureCatalog();

        return Listing::public()
            ->with(['brand', 'category', 'origin', 'images'])
            ->latest('published_at')
            ->orderByDesc('id')
            ->take($n)
            ->get();
    }

    private function ensureCatalog(): void
    {
        // Không tự seed DB — tránh ghi đè dữ liệu live khi chợ trống.
    }

    public function featuredCards(int $n = 4): array
    {
        return $this->featuredListings($n)->map(fn (Listing $l) => $this->productCard($l))->values()->all();
    }

    public function followUp(string $q, ?User $user): array
    {
        $filter = session('relic.ai.last_filter', []);
        $ids = session('relic.ai.last_ids', []);
        $override = [
            'tokens' => $filter['tokens'] ?? [],
            'price' => $filter['price'] ?? [],
            'seal' => $filter['seal'] ?? false,
            'sort' => $filter['sort'] ?? 'newest',
            'offset' => 0,
        ];
        if (! empty($filter['brand_id'])) {
            $override['brand'] = Brand::find($filter['brand_id']);
        }
        if (! empty($filter['category_id'])) {
            $override['category'] = Category::find($filter['category_id']);
        }

        if ($this->has($q, ['re hon', 're nua', 're qua', 'giam gia', 're nhat'])) {
            $override['sort'] = 'price_asc';
            $min = Listing::whereIn('id', $ids)->min('price');
            if ($min) {
                $override['price']['max'] = (int) $min;
            }
        }
        if ($this->has($q, ['dat hon', 'cao hon', 'dat nua'])) {
            $override['sort'] = 'price_desc';
            $max = Listing::whereIn('id', $ids)->max('price');
            if ($max) {
                $override['price']['min'] = (int) $max;
            }
        }
        if ($this->has($q, ['xem them', 'tiep', 'khac', 'nua di'])) {
            $override['offset'] = (int) session('relic.ai.last_offset', 0) + 5;
        }
        if ($this->has($q, ['seal'])) {
            $override['seal'] = true;
        }
        if (preg_match('/(cai|so|tin)\s*(dau|1|mot)\b/', $q) && $ids) {
            $listing = Listing::public()->find($ids[0] ?? null);
            if ($listing) {
                return [
                    'reply' => "Tin đầu danh sách: {$listing->title} — {$listing->formattedPrice()}.",
                    'links' => [['label' => 'Xem tin', 'url' => route('listings.show', $listing)]],
                    'products' => [$this->productCard($listing)],
                ];
            }
        }

        return $this->searchProducts(session('relic.ai.last_query', $q), $user, $override);
    }

    private function isFollowUp(string $q): bool
    {
        return $this->phrase($q, [
            're hon', 're nua', 'dat hon', 'xem them', 'tiep di', 'cai dau', 'tin dau',
            'so 1', 'may khac', 'con khong', 'con nua', 'seal nua',
        ]);
    }

    private function productCard(Listing $listing): array
    {
        return [
            'title' => $listing->title,
            'price' => $listing->formattedPrice(),
            'url' => route('listings.show', $listing),
            'image' => $listing->coverUrl(),
            'meta' => trim(($listing->brand?->name ?? '').' · '.$listing->conditionLabel(), ' ·'),
            'seal' => $listing->isOriginVerified(),
        ];
    }

    private function searchTokens(string $q): array
    {
        $clean = $q;
        foreach (['duoi', 'toi da', 'tren', 'tu', 'den', 'trieu', 'tr', 'cu', 'nghin', 'k '] as $p) {
            $clean = str_replace($p, ' ', $clean);
        }
        $clean = preg_replace('/\d+/', ' ', $clean) ?? $clean;
        $stop = [
            'toi', 'minh', 'ban', 'la', 'cua', 'cho', 'voi', 'va', 'hoac', 'thi', 'nhe', 'nha', 'a', 'ah',
            'the', 'nay', 'do', 'cai', 'may', 'muon', 'can', 'giup', 'ho', 'tim', 'kiem', 'mua', 'xem',
            'co', 'khong', 'ko', 'duoc', 'hay', 'gi', 'nao', 'o', 'tren', 'relic', 'shop', 'san', 'web',
            'di', 'nhe', 'oi', 'uh', 'uk', 'dung', 'roi', 'nua', 'giup', 'toi', 'mot', 'vai', 'chiec',
            'san pham', 'hang', 'cu', 'do cu', 'goi y', 'nhe', 'nhe', 'ban oi',
        ];
        $parts = preg_split('/\s+/', trim($clean)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 2 || in_array($part, $stop, true)) {
                continue;
            }
            $out[] = $part;
        }

        return array_values(array_unique(array_slice($out, 0, 6)));
    }

    private function parsePrice(string $q): array
    {
        $toDong = function (string $num, string $unit): int {
            $n = (float) str_replace(',', '.', $num);

            return (int) round(match (true) {
                str_contains($unit, 'tr') || str_contains($unit, 'cu') => $n * 1_000_000,
                $unit === 'k' || str_contains($unit, 'nghin') => $n * 1_000,
                default => $n >= 1000 ? $n : $n * 1_000_000,
            });
        };
        $out = [];
        if (preg_match('/(duoi|toi da|max|<=)\s*(\d+(?:[.,]\d+)?)\s*(trieu|tr|cu|k|nghin)?/u', $q, $m)) {
            $out['max'] = $toDong($m[2], $m[3] ?? 'tr');
        }
        if (preg_match('/(tren|tu|>=)\s*(\d+(?:[.,]\d+)?)\s*(trieu|tr|cu|k|nghin)?/u', $q, $m)) {
            $out['min'] = $toDong($m[2], $m[3] ?? 'tr');
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(trieu|tr|cu)\s*(den|-|toi)\s*(\d+(?:[.,]\d+)?)/u', $q, $m)) {
            $out['min'] = $toDong($m[1], $m[2]);
            $out['max'] = $toDong($m[4], $m[2]);
        }

        return $out;
    }

    private function matchedBrand(string $q): ?Brand
    {
        static $brands = null;
        $brands ??= Brand::query()->get();
        foreach ($brands as $brand) {
            $n = $this->norm($brand->name);
            if ($n !== '' && str_contains($q, $n)) {
                return $brand;
            }
        }

        return null;
    }

    private function matchedCategory(string $q): ?Category
    {
        static $cats = null;
        $cats ??= Category::query()->get();
        $aliases = [
            'dien thoai' => ['dien thoai', 'smartphone', 'phone'],
            'may tinh bang' => ['may tinh bang', 'tablet', 'ipad'],
            'laptop' => ['laptop', 'macbook', 'notebook'],
            'may anh' => ['may anh', 'camera'],
            'am thanh' => ['am thanh', 'tai nghe', 'loa', 'airpods'],
            'dong ho' => ['dong ho', 'smartwatch', 'watch'],
            'may choi game' => ['may choi game', 'console', 'ps5', 'switch'],
        ];
        foreach ($cats as $cat) {
            $n = $this->norm($cat->name);
            $slug = $this->norm(str_replace('-', ' ', (string) $cat->slug));
            if ($n !== '' && (str_contains($q, $n) || ($slug !== '' && str_contains($q, $slug)))) {
                return $cat;
            }
            foreach ($aliases as $needles) {
                foreach ($needles as $needle) {
                    if (str_contains($q, $needle) && (str_contains($n, explode(' ', $needle)[0]) || str_contains($slug, explode(' ', $needle)[0]))) {
                        return $cat;
                    }
                }
            }
        }
        // Alias map → tìm category theo tên gợi ý Engine
        foreach ($aliases as $key => $needles) {
            foreach ($needles as $needle) {
                if (! str_contains($q, $needle)) {
                    continue;
                }
                $hit = $cats->first(function ($cat) use ($key, $needle) {
                    $n = $this->norm($cat->name);
                    $slug = $this->norm(str_replace('-', ' ', (string) $cat->slug));

                    return str_contains($n, $key) || str_contains($slug, $key) || str_contains($n, $needle) || str_contains($slug, $needle);
                });
                if ($hit) {
                    return $hit;
                }
            }
        }

        return null;
    }

    private function orderCode(string $q): ?string
    {
        if (preg_match('/rlc[a-z0-9]+/i', $q, $m)) {
            return strtoupper($m[0]);
        }

        return null;
    }

    private function has(string $q, array $keys): bool
    {
        foreach ($keys as $key) {
            if (str_contains($q, $key)) {
                return true;
            }
        }

        return false;
    }

    private function norm(string $message): string
    {
        $s = mb_strtolower(trim($message));
        $from = ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ'];
        $to = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];

        return preg_replace('/\s+/', ' ', str_replace($from, $to, $s)) ?? str_replace($from, $to, $s);
    }
}
