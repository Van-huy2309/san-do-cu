<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\Order;
use App\Models\User;

/**
 * Relic Care — AI hỗ trợ người mua/bán (tách riêng giống RelicOpsAi).
 * RelicAi chỉ NLU + tìm hàng; Care lo hội thoại hỗ trợ + RelicCareBrain (LLM).
 */
class RelicCareAi
{
    public function __construct(
        private RelicCareBrain $brain,
        private RelicCareKnowledge $knowledge,
        private RelicCareGuard $guard,
    ) {}

    public function reply(?User $user, string $message, array $parsed, RelicAi $catalog): array
    {
        $base = $this->compose($user, $message, $parsed, $catalog);

        // Câu từ chối bảo mật: không đưa qua LLM (tránh lộ % / user).
        if (! empty($base['_restricted'])) {
            unset($base['_restricted']);

            return [
                'reply' => (string) ($base['reply'] ?? ''),
                'links' => array_values($base['links'] ?? []),
                'products' => array_values($base['products'] ?? []),
            ];
        }

        return $this->brain->enrich($user, $message, $parsed, $base);
    }

    private function compose(?User $user, string $message, array $parsed, RelicAi $catalog): array
    {
        $q = ($parsed['expanded'] ?? '') !== '' ? $parsed['expanded'] : $this->norm($message);
        $raw = $this->norm($message);
        $intent = (string) ($parsed['intent'] ?? '');

        if ($this->guard->blocksQuestion($raw) || $this->guard->blocksQuestion($q)) {
            return $this->restrictedOut();
        }

        // Tra đơn đã kiểm tra quyền sở hữu — chạy trước để NLU đoán nhầm intent không chặn oan.
        if ($code = $this->orderCode($q) ?: $this->orderCode($raw)) {
            $this->clearOffer();

            return $this->orderStatus($code, $user);
        }

        if (in_array($intent, ['commission', 'ops', 'stats'], true)) {
            return $this->restrictedOut();
        }

        if ($follow = $this->consumeOffer($q)) {
            return $follow;
        }

        // “có điện thoại nào đẹp” → tìm hàng, không phải tán gẫu.
        if ($this->isProductQuestion($raw) && ! $this->isPersonalChitchat($raw)) {
            $this->clearOffer();
            $parsed['intent'] = 'search';

            return $catalog->searchProducts($q, $user, $catalog->searchOverrides($parsed));
        }

        if ($this->isGreet($q) || $intent === 'greet') {
            $this->clearOffer();

            return $this->greet($user, $catalog);
        }

        if ($intent === 'thanks' || $this->has($q, ['cam on', 'thanks', 'thank you', 'tks'])) {
            $this->clearOffer();

            return $this->out('Không có gì. Cần tìm máy hay xem đơn thì cứ hỏi tiếp.', [
                ['label' => 'Chợ Relic', 'url' => route('listings.index')],
            ]);
        }

        if ($intent === 'bye' || $this->has($q, ['tam biet', 'bye', 'pp', 'hen gap'])) {
            $this->clearOffer();

            return $this->out('Tạm biệt. Icon Care góc phải mở lại được bất cứ lúc nào.');
        }

        if ($this->has($raw, ['relic seal', 'seal la gi', 'nguon goc'])) {
            $this->clearOffer();

            return $this->out(
                'Relic Seal là dấu xác thực nguồn gốc: shop nộp serial/IMEI, hóa đơn, ảnh hộp và được admin xác minh. Mua tin có Seal sẽ yên tâm hơn về máy chính chủ.',
                [['label' => 'Relic Seal', 'url' => route('pages.show', 'authenticity')]]
            );
        }

        if ($this->has($raw, ['ban la ai', 'may la ai', 'ai day', 'relic la gi', 'care la gi'])) {
            $this->clearOffer();

            return $this->out(
                'Mình là Relic Care — trợ lý AI hỗ trợ mua/bán trên sàn Relic: tìm máy, theo dõi đơn, escrow MoMo, KYC, ví, khiếu nại.',
                [['label' => 'Chợ', 'url' => route('listings.index')]]
            );
        }

        if ($intent === 'chitchat' || $this->isChitchat($q)) {
            $this->clearOffer();

            return $this->chitchat($user, $catalog);
        }

        if ($intent === 'followup') {
            if (! session('relic.ai.last_query')) {
                return $this->out(
                    'Bạn chưa tìm máy nào. Gõ ip13, s23… hoặc “máy đang bán”.',
                    [['label' => 'Chợ Relic', 'url' => route('listings.index')]]
                );
            }

            return $catalog->followUp($q, $user);
        }

        if ($this->has($raw, ['giup gi', 'lam duoc gi', 'huong dan su dung', 'help', 'ho tro gi', 'ban lam gi'])) {
            $this->clearOffer();

            return $this->help($catalog);
        }

        // Hỗ trợ trước — tránh nhầm “phí sàn” thành tìm hàng.
        if ($hit = $this->routeSupport($q, $intent, $user, $catalog)) {
            return $hit;
        }

        if (($intent === 'search' || $catalog->shouldSearchCatalog($q, $parsed))
            && $catalog->hasStrongSearchSignal($q, $parsed)) {
            $this->clearOffer();

            return $catalog->searchProducts($q, $user, $catalog->searchOverrides($parsed));
        }

        if ($soft = $this->softRoute($q, $user, $catalog)) {
            return $soft;
        }

        return $this->clarify($user, $catalog, $parsed);
    }

    public function isFollowUpReply(string $q): bool
    {
        return $this->isAffirm($q) || $this->isDeny($q);
    }

    private function routeSupport(string $q, string $intent, ?User $user, RelicAi $catalog): ?array
    {
        $map = [
            'payment' => fn () => $this->has($q, ['the atm', 'paywithatm', '9704', 'thanh toan lai', 'thanh toan momo', 'loi momo', 'thanh toan khong duoc', 'khong thanh toan duoc', 'momo loi', 'the test', 'atm momo']) || $intent === 'payment',
            'escrow' => fn () => ($this->has($q, ['escrow', 'giu tien']) || ($this->has($q, ['momo', 'mo mo']) && ! $this->has($q, ['the', 'atm', '9704', 'loi', 'thanh toan']))) || $intent === 'escrow',
            'shipping' => fn () => $this->has($q, ['van don', 'ghn', 'ship', 'giao hang', 'ship cham', 'giao hang cham', 'ma van don', 'bao lau nhan']),
            'kyc' => fn () => $this->has($q, ['kyc', 'cccd', 'dinh danh', 'chung minh']) || $intent === 'kyc',
            'sell' => fn () => $this->has($q, ['dang tin', 'ban hang', 'kenh nguoi ban', 'day tin', 'an tin', 'da ban', 'up bai', 'muon ban']) || $intent === 'sell',
            'login' => fn () => $this->has($q, ['otp', 'dang nhap', 'quen mat khau', 'login google', 'apple']) || $intent === 'login',
            'account' => fn () => $this->has($q, ['doi sdt', 'doi ten', 'doi email', 'ho so', 'tai khoan']) || $intent === 'account',
            'gps' => fn () => $this->has($q, ['gps', 'ban kinh', 'gan toi', 'vi tri', 'near me']) || $intent === 'gps',
            'wallet' => fn () => $this->has($q, ['vi relic', 'nap tien', 'rut tien', 'so du', 'dong bang']) || $intent === 'wallet',
            'dispute' => fn () => $this->has($q, ['khieu nai', 'hoan tien', 'tra hang', 'unbox', 'refund']) || $intent === 'dispute',
            'chat' => fn () => $this->has($q, ['tra gia', 'offer', 'chat voi shop', 'pin may', 'icloud']) || $intent === 'chat',
            'alerts' => fn () => $this->has($q, ['yeu thich', 'luu tin', 'favorite']) || $intent === 'alerts',
            'orders' => fn () => $this->has($q, ['don hang', 'don cua toi', 'don mua', 'nhan hang', 'huy don']) || $intent === 'orders',
            'safety' => fn () => $this->has($q, ['lua dao', 'chuyen khoan ngoai', 'gap mat', 'doa nat', 'an toan']) || $intent === 'safety',
            'escalate' => fn () => $this->has($q, ['gap admin', 'goi admin', 'can admin', 'bao cao lua']) || $intent === 'escalate',
            'warranty' => fn () => $this->has($q, ['bao hanh', 'doi tra', 'doi may', 'tra lai may', 'loi may sau khi mua']),
            'condition' => fn () => $this->has($q, ['tinh trang may', 'like new', 'tinh trang la gi', 'may cu co tot', 'do moi', 'phan loai tinh trang']),
            'buying' => fn () => $this->has($q, ['cach mua', 'mua nhu the nao', 'mua the nao', 'dat hang', 'gio hang', 'quy trinh mua', 'thanh toan nhu the nao', 'cach thanh toan']),
            'inspect' => fn () => $this->has($q, ['kiem tra may', 'check may', 'imei', 'hang that', 'hang gia', 'may dung', 'may zin', 'khoa icloud']),
            'contact' => fn () => $this->has($q, ['lien he', 'hotline', 'tong dai', 'cham soc khach hang', 'cskh']),
            'payment_fallback' => fn () => $this->has($q, ['thanh toan']) || $intent === 'payment',
        ];

        foreach ($map as $name => $check) {
            if ($check()) {
                $method = $name === 'payment_fallback' ? 'payment' : $name;

                return $this->{$method}($user, $catalog);
            }
        }

        return null;
    }

    private function softRoute(string $q, ?User $user, RelicAi $catalog): ?array
    {
        if ($this->has($q, ['don moi', 'don gan day', 'don cua minh'])) {
            return $this->orders($user, $catalog);
        }
        if ($this->has($q, ['may dang ban', 'hang dang ban', 'san pham dang co'])) {
            return $catalog->searchProducts($q, $user);
        }

        return null;
    }

    private function restrictedOut(): array
    {
        $this->clearOffer();

        return [
            'reply' => RelicCareGuard::REFUSAL,
            'links' => [],
            'products' => [],
            '_restricted' => true,
        ];
    }

    private function offer(string $topic, string $short, string $detail, array $links = [], array $products = []): array
    {
        // Trả lời đầy đủ ngay (giống AI chat hiện đại); vẫn giữ offer nếu user muốn nghe lại.
        if (config('ai.care.full_answers', true)) {
            session(['relic.care.offer' => [
                'topic' => $topic,
                'detail' => $detail,
                'links' => $links,
                'products' => $products,
            ]]);

            $body = trim($detail);

            return $this->out($body !== '' ? $body : $short, $links, $products);
        }

        session(['relic.care.offer' => [
            'topic' => $topic,
            'detail' => $detail,
            'links' => $links,
            'products' => $products,
        ]]);
        $ask = [
            ' Muốn nghe thêm không?',
            ' Cần chi tiết hơn không?',
            ' Mình kể sâu hơn nhé?',
            ' Xem thêm chứ?',
        ][random_int(0, 3)];

        return $this->out($short.$ask, $links, $products);
    }

    private function consumeOffer(string $q): ?array
    {
        $offer = session('relic.care.offer');
        if (! is_array($offer) || ! isset($offer['detail'])) {
            return null;
        }
        if ($this->isDeny($q)) {
            $this->clearOffer();

            return $this->out('Ok. Cứ hỏi khi cần.');
        }
        if (! $this->isAffirm($q)) {
            return null;
        }
        $this->clearOffer();

        return $this->out(
            (string) $offer['detail'],
            $offer['links'] ?? [],
            $offer['products'] ?? []
        );
    }

    private function clearOffer(): void
    {
        session()->forget('relic.care.offer');
    }

    private function out(string $reply, array $links = [], array $products = []): array
    {
        return [
            'reply' => $reply,
            'links' => $links,
            'products' => $products,
        ];
    }

    private function greet(?User $user, RelicAi $catalog): array
    {
        $hi = [
            $user?->name ? "Chào {$user->name}." : 'Chào bạn.',
            $user?->name ? "Ê {$user->name}, Care đây." : 'Ê, Care đây.',
            $user?->name ? "Hi {$user->name}." : 'Hi.',
        ][random_int(0, 2)];

        return $this->out(
            $hi.' Cần gì — tìm máy, xem đơn, escrow hay KYC?',
            [['label' => 'Chợ Relic', 'url' => route('listings.index')]]
        );
    }

    private function help(RelicAi $catalog): array
    {
        return $this->out(
            "Mình hỗ trợ:\n"
            ."• Tìm máy theo tên (ip13, s23u, mba m1…), lọc giá\n"
            ."• Tra đơn RLC, mã GHN, hủy / nhận hàng / thanh toán lại\n"
            ."• Escrow MoMo, KYC, đăng bán, ví, khiếu nại, bảo hành",
            [['label' => 'Chợ Relic', 'url' => route('listings.index')]]
        );
    }

    private function chitchat(?User $user, RelicAi $catalog): array
    {
        $name = $user?->name ? ' '.$user->name : '';
        $lines = [
            "Haha{$name}, mình Care hỗ trợ mua bán trên Relic — máy thì gõ ip13 / “máy đang bán”.",
            'Mình không chấm đẹp trai đâu 😄. Cứ hỏi máy, đơn hay escrow.',
            'Chill. Bạn cần tìm hàng hay xem đơn?',
        ];

        return $this->out($lines[array_rand($lines)], [
            ['label' => 'Chợ Relic', 'url' => route('listings.index')],
        ]) + ['_free' => true];
    }

    private function clarify(?User $user, RelicAi $catalog, array $parsed = []): array
    {
        $hint = '';
        if (! empty($parsed['expanded'])) {
            $hint = ' Mình nghe gần giống: “'.$parsed['expanded'].'”.';
        }

        $pool = [
            'Mình chưa rõ ý bạn.'.$hint.' Bạn nói cụ thể hơn giúp mình — tên máy, mã đơn RLC hay vấn đề thanh toán/ship?',
            'Bạn nói rõ hơn chút nhé: muốn tìm máy nào, hỏi về đơn hàng, hay về thanh toán/KYC?'.$hint,
        ];

        return $this->out($pool[array_rand($pool)], [
            ['label' => 'Chợ', 'url' => route('listings.index')],
            ['label' => $user ? 'Đơn mua' : 'Đăng nhập', 'url' => $user ? route('user.orders.index') : route('login')],
        ]) + ['_free' => true];
    }

    private function orderStatus(string $code, ?User $user): array
    {
        $order = Order::with('items')->where('code', $code)->first();
        if (! $order) {
            return $this->out(
                'Không thấy đơn '.$code.'. Kiểm tra mã dạng RLC…',
                $user ? [['label' => 'Đơn mua', 'url' => route('user.orders.index')]] : []
            );
        }
        if ($user && $order->user_id !== $user->id && ! $user->isAdmin()) {
            return $this->out('Đơn này không thuộc tài khoản đang đăng nhập.', [
                ['label' => 'Đơn của bạn', 'url' => route('user.orders.index')],
            ]);
        }
        $next = [];
        if ($order->canPayAgain()) {
            $next[] = 'Có thể thanh toán MoMo lại.';
        }
        if ($order->canConfirmReceived()) {
            $next[] = 'Máy đúng mô tả → bấm “Đã nhận hàng” để giải ngân shop.';
        }
        if ($order->canDispute()) {
            $next[] = 'Sai mô tả → khiếu nại kèm video unbox.';
        }

        return $this->out(
            "Đơn {$order->code}: {$order->statusLabel()}.\nEscrow: {$order->escrowLabel()}.\nGHN: ".($order->ghn_order_code ?: 'chưa có').".\n"
            .($next ? implode(' ', $next) : 'Không còn thao tác khẩn.'),
            $user && $order->user_id === $user->id
                ? [['label' => 'Chi tiết đơn', 'url' => route('user.orders.show', $order)]]
                : []
        );
    }

    private function escrow(?User $user, RelicAi $catalog): array
    {
        return $this->offer(
            'escrow',
            'MoMo: Relic giữ tiền → giao hàng → bạn xác nhận nhận đúng mô tả → tiền mới giải ngân cho shop. COD không qua ví.',
            "Escrow MoMo:\n1) Bạn trả MoMo → Relic giữ tiền.\n2) GHN giao máy.\n3) Bạn bấm “Đã nhận đúng mô tả” → tiền mới giải ngân cho shop.\nCOD trả trực tiếp shipper. Không chuyển khoản ngoài sàn.",
            [
                ['label' => 'Cách escrow', 'url' => route('pages.show', 'how-it-works')],
                ['label' => $user ? 'Đơn mua' : 'Đăng nhập', 'url' => $user ? route('user.orders.index') : route('login')],
            ]
        );
    }

    private function payment(?User $user, RelicAi $catalog): array
    {
        return $this->offer(
            'payment',
            'Thẻ test MoMo: 9704 0000 0000 0018 — NGUYEN VAN A — 12/30 — OTP bất kỳ.',
            "Sandbox payWithATM:\n• Thẻ OK: 9704…0018 / NGUYEN VAN A / 12/30 / OTP bất kỳ.\n• Tránh Visa 4111 (dễ lỗi 1002).\n• Đơn chờ: Chi tiết đơn → Thanh toán lại MoMo.\nLỗi thanh toán: thử lại thẻ test hoặc đổi mạng.",
            [['label' => $user ? 'Đơn mua' : 'Đăng nhập', 'url' => $user ? route('user.orders.index') : route('login')]]
        );
    }

    private function commission(?User $user, RelicAi $catalog): array
    {
        return $this->restrictedOut();
    }

    private function shipping(?User $user, RelicAi $catalog): array
    {
        $extra = '';
        if ($user) {
            $last = $user->orders()->latest()->first();
            if ($last) {
                $extra = " Đơn gần nhất {$last->code}: GHN ".($last->ghn_order_code ?: 'chưa có mã').'.';
            }
        }

        return $this->offer(
            'shipping',
            'GHN gắn sau khi đơn được thanh toán/xác nhận. Thiếu mã → mở chi tiết đơn; chậm giao → theo dõi GHN hoặc báo trên đơn.'.$extra,
            "Ship Relic:\n• MoMo paid / COD → hệ thống tạo đơn GHN.\n• Có mã → tra cứu trên GHN.\n• Chưa có mã sau vài giờ: mở Chi tiết đơn hoặc nhắn Care kèm mã RLC.\n• Hủy đơn: chỉ khi còn trạng thái cho phép trên trang đơn.",
            $user
                ? [['label' => 'Đơn mua', 'url' => route('user.orders.index')]]
                : [['label' => 'Đăng nhập', 'url' => route('login')]]
        );
    }

    private function kyc(?User $user, RelicAi $catalog): array
    {
        $st = $user ? $user->kycLabel() : 'chưa đăng nhập';

        return $this->out(
            "Bán hàng cần KYC (CCCD 2 mặt rõ nét). Trạng thái của bạn: {$st}.",
            [['label' => 'Định danh KYC', 'url' => route('account.kyc')]]
        );
    }

    private function sell(?User $user, RelicAi $catalog): array
    {
        $extra = '';
        if ($user) {
            $mine = Listing::where('seller_id', $user->id)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
            if ($mine->isNotEmpty()) {
                $bits = [];
                foreach ($mine as $status => $c) {
                    $bits[] = "{$status}={$c}";
                }
                $extra = ' Tin của bạn: '.implode(', ', $bits).'.';
            }
        }

        return $this->offer(
            'sell',
            'Đăng bán: KYC → đăng tin → admin duyệt → lên chợ.'.$extra,
            "Đăng bán:\n1) Hoàn tất KYC.\n2) Đăng tin: ảnh thật, serial/IMEI, mô tả tình trạng.\n3) Chờ duyệt rồi tin lên chợ.\nBán xong: người mua xác nhận nhận hàng → tiền về ví Relic → rút về ngân hàng.".$extra,
            [
                ['label' => 'KYC', 'url' => route('account.kyc')],
                ['label' => 'Đăng tin', 'url' => route('seller.listings.create')],
                ['label' => 'Tin của tôi', 'url' => route('seller.listings.index')],
            ]
        );
    }

    private function login(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            'Đăng nhập bằng email/mật khẩu, Google, Apple hoặc OTP SMS. Quên mật khẩu: link trên trang đăng nhập.',
            $user
                ? [['label' => 'Đổi hồ sơ', 'url' => route('account.change.index')]]
                : [['label' => 'Đăng nhập', 'url' => route('login')]]
        );
    }

    private function account(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            'Đổi tên / SĐT / email trong Hồ sơ → Thay đổi (có mã xác nhận email).',
            [['label' => 'Hồ sơ', 'url' => route('account.profile')]]
        );
    }

    private function gps(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            'Trên Chợ bấm GPS, chọn bán kính (km). Tin hiện gần bạn khi shop gắn tọa độ lúc đăng.',
            [['label' => 'Chợ gần tôi', 'url' => route('listings.index', ['sort' => 'nearby'])]]
        );
    }

    private function wallet(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            'Relic không có ví nạp hoặc rút. Tiền mua MoMo được sàn giữ đến khi bạn xác nhận đã nhận máy.',
            [['label' => 'Cách hoạt động', 'url' => route('pages.show', 'how-it-works')]]
        );
    }

    private function dispute(?User $user, RelicAi $catalog): array
    {
        return $this->offer(
            'dispute',
            'Hàng lệch mô tả: quay video unbox → Khiếu nại trên đơn. Relic giữ escrow đến khi xử lý xong.',
            "1) Video unbox rõ.\n2) Đơn mua → Khiếu nại.\n3) Không chuyển khoản riêng.\nNghi lừa đảo → nói “gặp admin”.",
            $user
                ? [['label' => 'Đơn mua', 'url' => route('user.orders.index')]]
                : [['label' => 'Đăng nhập', 'url' => route('login')]]
        );
    }

    private function chat(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            'Hỏi shop trên chat: pin, iCloud, hộp/BH, trầy. Trả giá trong tin nhắn — đừng CK ngoài sàn.',
            $user
                ? [['label' => 'Tin nhắn', 'url' => route('messages.index')]]
                : [['label' => 'Chợ', 'url' => route('listings.index')]]
        );
    }

    private function alerts(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            '♡ Yêu thích để lưu tin. Máy mới thì tìm lại trên Chợ.',
            $user
                ? [['label' => 'Yêu thích', 'url' => route('favorites.index')]]
                : [['label' => 'Chợ', 'url' => route('listings.index')]]
        );
    }

    private function orders(?User $user, RelicAi $catalog): array
    {
        if ($user) {
            $last = $user->orders()->latest()->first();
            $txt = $last
                ? "Đơn mới nhất {$last->code}: {$last->statusLabel()} / {$last->escrowLabel()}. GHN: ".($last->ghn_order_code ?: 'chưa').'. Gửi mã RLC… nếu cần chi tiết.'
                : 'Bạn chưa có đơn. Thêm máy vào giỏ rồi thanh toán MoMo/COD.';

            return $this->out($txt, [['label' => 'Đơn mua', 'url' => route('user.orders.index')]]);
        }

        return $this->out('Đăng nhập để xem đơn, hoặc gửi mã RLC…', [
            ['label' => 'Đăng nhập', 'url' => route('login')],
        ]);
    }

    private function warranty(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            "Bảo hành theo mô tả của từng tin (hỏi shop trước khi mua).\nNhận máy lệch mô tả: quay video unbox → Khiếu nại trên đơn, tiền vẫn được Relic giữ đến khi xử lý xong.",
            $user
                ? [['label' => 'Đơn mua', 'url' => route('user.orders.index')]]
                : [['label' => 'Cách hoạt động', 'url' => route('pages.show', 'how-it-works')]]
        );
    }

    private function condition(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            'Mỗi tin ghi tình trạng (mới, như mới, tốt, khá…) kèm mô tả trầy xước, pin. Nên xem kỹ ảnh thật và hỏi shop về pin, sửa chữa, iCloud trước khi mua.',
            [['label' => 'Chợ Relic', 'url' => route('listings.index')]]
        );
    }

    private function buying(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            "Mua trên Relic:\n1) Chọn máy → Thêm vào giỏ.\n2) Điền địa chỉ, chọn MoMo (escrow giữ tiền) hoặc COD.\n3) Nhận máy, kiểm tra → bấm “Đã nhận hàng”.",
            [
                ['label' => 'Chợ Relic', 'url' => route('listings.index')],
                ['label' => 'Giỏ hàng', 'url' => route('user.cart.index')],
            ]
        );
    }

    private function inspect(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            'Khi nhận máy: đối chiếu IMEI/serial với tin, kiểm tra iCloud/Google đã thoát, pin, màn hình, camera, loa. Quay video unbox để khiếu nại nếu cần.',
            [['label' => 'Cách hoạt động', 'url' => route('pages.show', 'how-it-works')]]
        );
    }

    private function contact(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            'Vấn đề với đơn: mở Chi tiết đơn → Khiếu nại. Cần người hỗ trợ trực tiếp: xem trang Trợ giúp.',
            [['label' => 'Trợ giúp', 'url' => route('pages.show', 'help')]]
        );
    }

    private function safety(?User $user, RelicAi $catalog): array
    {
        return $this->escalate($user, $catalog);
    }

    private function escalate(?User $user, RelicAi $catalog): array
    {
        return $this->out(
            'An toàn: không CK ngoài Relic, không đưa đủ tiền gặp mặt trước, cảnh giác link lạ. Giữ giao dịch + escrow trên sàn.',
            [
                ['label' => 'Cách hoạt động', 'url' => route('pages.show', 'how-it-works')],
                ['label' => $user ? 'Đơn mua' : 'Đăng nhập', 'url' => $user ? route('user.orders.index') : route('login')],
            ]
        );
    }

    private function isGreet(string $q): bool
    {
        $t = trim($q);
        if (in_array($t, ['hello', 'hi', 'hey', 'alo', 'chao', 'xin chao', 'chao ban', 'chao care', 'care oi'], true)) {
            return true;
        }

        return (bool) preg_match('/^(xin chao|chao|hello|hi|hey|alo)\b.{0,24}$/u', $t);
    }

    private function isProductQuestion(string $q): bool
    {
        if (! preg_match('/\b(dien thoai|smartphone|iphone|ipad|samsung|xiaomi|oppo|vivo|pixel|laptop|macbook|may tinh|may anh|camera|tai nghe|airpods|loa|dong ho|apple watch|ps5|ps4|nintendo|switch|console|man hinh|tablet|may tinh bang)\b/u', $q)) {
            return false;
        }
        if (preg_match('/\b(bao hanh|doi tra|khieu nai|hoan tien|don hang|van don|ship|giao hang|kiem tra|imei|icloud|thanh toan|dang ban|dang tin|ban hang|muon ban|can ban|cach ban|escrow|kyc|vi relic|cau hinh|so sanh|khac gi|nen chon)\b/u', $q)) {
            return false;
        }

        return (bool) preg_match('/\b(co|nao|tim|xem|mua|goi y|de xuat|dep|tot|re|ngon|dang co|duoi|tam|khoang|tr|trieu)\b/u', $q);
    }

    private function isPersonalChitchat(string $q): bool
    {
        return (bool) preg_match('/\b(dep trai|dep gai|xinh khong|toi dep|minh dep|em dep|anh dep|ngau khong|co ny|yeu chua)\b/u', $q);
    }

    private function isChitchat(string $q): bool
    {
        return $this->has($q, [
            'dep trai', 'dep gai', 'xinh khong', 'ngau khong', 'khoe khong', 'an com',
            'buon qua', 'vui qua', 'met qua', 'chill', 'haha', 'hihi', 'kkk', 'lol',
            'troi mua', 'thoi tiet', 'co ny', 'yeu chua',
        ]);
    }

    private function isAffirm(string $q): bool
    {
        $t = trim($q);
        if ($t === '' || mb_strlen($t) > 36) {
            return false;
        }

        return (bool) preg_match('/^(co|co a|co nhe|ok|oke|okay|u|uh|uk|dc|duoc|duoc roi|yes|y|chi tiet|chi tiet hon|them|muon|vang|da|tiep|xem|cho xem|ua|hay)$/u', $t);
    }

    private function isDeny(string $q): bool
    {
        return (bool) preg_match('/^(khong|ko|thoi|huy|no|de sau|thoi di|bo qua)$/u', trim($q));
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
