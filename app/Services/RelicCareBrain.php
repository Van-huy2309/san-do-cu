<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Lớp “não” LLM: biến câu trả lời template + dữ liệu thật thành hội thoại tự nhiên, đầy đủ hơn.
 */
class RelicCareBrain
{
    public function __construct(
        private LlmClient $llm,
        private RelicCareKnowledge $knowledge,
        private RelicCareGuard $guard,
    ) {}

    /**
     * @param  array{reply?: string, links?: array, products?: array}  $base
     * @return array{reply: string, links: array, products: array}
     */
    public function enrich(?User $user, string $message, array $parsed, array $base): array
    {
        $reply = trim((string) ($base['reply'] ?? ''));
        $links = array_values($base['links'] ?? []);
        $products = array_values($base['products'] ?? []);
        $free = ! empty($base['_free']);

        if (! config('ai.llm.enabled', true) || ! $this->llm->enabled()) {
            return [
                'reply' => $reply,
                'links' => $links,
                'products' => $products,
            ];
        }

        $intent = (string) ($parsed['intent'] ?? '');
        $history = $this->historyMessages();
        $facts = $this->buildFacts($free ? '' : $reply, $parsed, $products);

        $messages = [
            ['role' => 'system', 'content' => $this->knowledge->systemPrompt($user)],
            ...$history,
            [
                'role' => 'user',
                'content' => $this->userPacket($message, $intent, $facts, $products, $free),
            ],
        ];

        $generated = $this->llm->chat($messages, [
            'temperature' => $this->temperatureFor($intent, $products !== []),
            'max_tokens' => (int) config('ai.llm.max_tokens', 900),
        ]);

        if ($generated === null) {
            return [
                'reply' => $reply,
                'links' => $links,
                'products' => $products,
            ];
        }

        $generated = $this->stripProductEcho($this->sanitize($generated), $products);

        if ($this->isRefusal($generated)) {
            return ['reply' => RelicCareGuard::REFUSAL, 'links' => [], 'products' => []];
        }

        // Lộ số liệu / thiếu dữ kiện quan trọng → dùng câu mẫu an toàn.
        if ($generated === '' || $this->guard->leaksInAnswer($generated)
            || (! $free && $reply !== '' && ! $this->preservesKeyFacts($reply, $generated))) {
            Log::debug('Relic Care LLM answer replaced by template', ['answer' => mb_substr($generated, 0, 400)]);
            $generated = $reply;
        }

        return [
            'reply' => $generated,
            'links' => $links,
            'products' => $products,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function buildFacts(string $baseReply, array $parsed, array $products): string
    {
        $bits = [];
        $intent = (string) ($parsed['intent'] ?? '');
        $topics = $this->knowledge->topicFacts();

        if ($intent !== '' && isset($topics[$intent])) {
            $bits[] = 'Chủ đề nhận diện: '.$intent."\n".$topics[$intent];
        }

        if ($catalog = $this->catalogContext()) {
            $bits[] = $catalog;
        }

        if (($parsed['aliases'] ?? []) !== []) {
            $bits[] = 'Alias máy: '.implode(', ', $parsed['aliases']);
        }
        if (! empty($parsed['brand_hint'])) {
            $bits[] = 'Brand hint: '.$parsed['brand_hint'];
        }
        if (! empty($parsed['category_hint'])) {
            $bits[] = 'Category hint: '.$parsed['category_hint'];
        }

        if ($baseReply !== '') {
            $bits[] = "DỮ LIỆU / MẪU TRẢ LỜI TỪ HỆ THỐNG (bám sát sự thật này):\n".$baseReply;
        }

        if ($products !== []) {
            $lines = [];
            foreach (array_slice($products, 0, 6) as $p) {
                $title = (string) ($p['title'] ?? $p['name'] ?? 'SP');
                $price = (string) ($p['price'] ?? '');
                $meta = (string) ($p['meta'] ?? '');
                $seal = ! empty($p['seal']) ? ' · có Relic Seal' : '';
                $lines[] = '- '.$title.($price !== '' ? ' — '.$price : '').($meta !== '' ? ' ('.$meta.$seal.')' : '');
            }
            $bits[] = "Máy đang bán trên Relic khớp câu hỏi (chỉ nói về các máy này, đừng bịa thêm):\n".implode("\n", $lines);
        }

        return implode("\n\n", $bits);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function userPacket(string $message, string $intent, string $facts, array $products, bool $free = false): string
    {
        $extra = $products !== []
            ? 'Thẻ sản phẩm (tên, giá, ảnh) đã hiển thị bên dưới câu trả lời — KHÔNG liệt kê lại từng máy; chỉ nhận xét ngắn 1–2 câu giúp chọn máy, không đếm số lượng.'
            : 'Không có thẻ sản phẩm kèm theo.';
        if ($free) {
            $extra .= "\n- Câu này hệ thống chưa nhận diện được. Nếu nó KHÔNG liên quan tới mua bán/sử dụng web Relic thì TUYỆT ĐỐI không trả lời nội dung (không đưa kiến thức, đáp án, code…), chỉ nói: \"Câu này nằm ngoài phạm vi của mình, mình chỉ hỗ trợ mua bán trên Relic thôi — bạn cần tìm máy hay hỗ trợ đơn hàng không?\"";
        }

        return <<<TXT
Câu hỏi người dùng:
{$message}

Intent NLU (gợi ý): {$intent}

{$facts}

Yêu cầu:
- Trả lời ngắn gọn, trực tiếp, không mâu thuẫn dữ liệu trên.
- {$extra}
- Không nhắc "theo dữ liệu hệ thống". Không in đậm.
- Nếu là tán gẫu hoặc câu không liên quan: đáp 1 câu rồi lái về việc tìm máy / đơn hàng trên Relic.
- Câu hỏi nhạy cảm theo quy tắc bảo mật: chỉ trả lời đúng câu từ chối.
TXT;
    }

    /** @return list<array{role: string, content: string}> */
    private function historyMessages(): array
    {
        $log = session('relic.ai.care.log', []);
        if (! is_array($log) || $log === []) {
            return [];
        }

        $limit = (int) config('ai.llm.history_turns', 8);
        $slice = array_slice($log, -($limit * 2));
        $out = [];

        foreach ($slice as $row) {
            $text = trim((string) ($row['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $who = ($row['who'] ?? '') === 'me' ? 'user' : 'assistant';
            // Cắt ngắn lịch sử để tiết kiệm token.
            if (mb_strlen($text) > 500) {
                $text = mb_substr($text, 0, 500).'…';
            }
            $out[] = ['role' => $who, 'content' => $text];
        }

        return $out;
    }

    private function temperatureFor(string $intent, bool $hasProducts): float
    {
        if ($hasProducts || in_array($intent, ['orders', 'payment', 'escrow', 'commission', 'wallet'], true)) {
            return 0.35;
        }
        if (in_array($intent, ['chitchat', 'greet'], true)) {
            return 0.7;
        }

        return (float) config('ai.llm.temperature', 0.55);
    }

    private function sanitize(string $text): string
    {
        $text = preg_replace('/^#{1,6}\s+/mu', '', $text) ?? $text;
        $text = preg_replace('/\*\*(.+?)\*\*|__(.+?)__/u', '$1$2', $text) ?? $text;
        $text = preg_replace('/[ \t]+$/mu', '', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        $max = (int) config('ai.llm.max_reply_chars', 3500);
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max).'…';
        }

        return trim($text);
    }

    private function preservesKeyFacts(string $base, string $generated): bool
    {
        $needles = [];
        if (preg_match('/9704/', $base)) {
            $needles[] = '9704';
        }
        if (preg_match('/\b5\s*%/', $base) || str_contains($base, '95%')) {
            $needles[] = '5%';
        }
        if (preg_match('/RLC[A-Z0-9]+/i', $base, $m)) {
            $needles[] = strtoupper($m[0]);
        }

        foreach ($needles as $n) {
            if (! str_contains($generated, $n) && ! str_contains(mb_strtolower($generated), mb_strtolower($n))) {
                // 5% có thể viết “năm phần trăm” — chỉ bắt buộc mã thẻ / RLC / MoMo
                if ($n === '5%') {
                    continue;
                }

                return false;
            }
        }

        return true;
    }

    /** Bỏ các dòng gạch đầu dòng chỉ lặp lại tên máy đã có trên thẻ sản phẩm. */
    private function stripProductEcho(string $text, array $products): string
    {
        if ($products === []) {
            return $text;
        }
        $squash = fn (string $s) => preg_replace('/[^a-z0-9]/', '', $this->guard->norm($s)) ?? '';
        $titles = array_filter(array_map(fn ($p) => $squash((string) ($p['title'] ?? '')), $products));
        $keep = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $n = $squash($line);
            $isBullet = (bool) preg_match('/^\s*([-•*]|\d+[.)])\s+/u', $line);
            $echo = $isBullet && array_filter($titles, fn ($t) => $t !== '' && str_contains($n, substr($t, 0, 14))) !== [];
            if (! $echo) {
                $keep[] = $line;
            }
        }
        $out = trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $keep)) ?? '');

        return $out !== '' ? $out : $text;
    }

    private function catalogContext(): string
    {
        return Cache::remember('relic.care.catalog_context', 600, function () {
            $cats = Category::query()->where('is_active', true)->orderBy('name')->limit(30)->pluck('name')->all();
            $brands = Brand::query()->orderBy('name')->limit(40)->pluck('name')->all();
            if ($cats === [] && $brands === []) {
                return '';
            }

            return 'Danh mục trên Relic: '.($cats ? implode(', ', $cats) : '—')
                ."\nHãng trên Relic: ".($brands ? implode(', ', $brands) : '—');
        });
    }

    private function isRefusal(string $text): bool
    {
        $t = $this->guard->norm($text);

        return str_contains($t, 'khong duoc tra loi thong tin nay')
            || str_contains($t, 'khong the tra loi thong tin nay');
    }
}
