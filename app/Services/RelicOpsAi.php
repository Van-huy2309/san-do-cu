<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Dispute;
use App\Models\Listing;
use App\Models\ListingOrigin;
use App\Models\ListingReport;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RelicOpsAi
{
    private const BANNED = [
        'chuyen khoan truoc', 'ck truoc', 'gap mat dua het', 'khong qua san',
        'hack', 'jailbreak full', 'clone', 'hang coll', 'fake 1-1',
    ];

    public function reply(string $message): array
    {
        $q = $this->norm($message);

        if ($follow = $this->consumeFollowUp($q)) {
            return $follow;
        }

        if ($code = $this->orderCode($q)) {
            $this->clearOffer();

            return $this->orderBrief($code);
        }

        if ($this->isOpsGreet($q)) {
            $this->clearOffer();

            return $this->opsHello();
        }

        $expand = $this->wantsExpand($q);

        if ($entity = $this->tryEntityBrief($q)) {
            $this->clearOffer();

            return $entity;
        }

        if ($this->wantsToday($q)) {
            return $this->todayDigest($expand);
        }

        if ($nav = $this->tryNavigate($q)) {
            $this->clearOffer();

            return $nav;
        }

        if ($exp = $this->tryExport($q)) {
            $this->clearOffer();

            return $exp;
        }

        if ($this->has($q, ['giup gi', 'lam duoc gi', 'huong dan', 'help', 'ban giup', 'co the lam', 'ban biet gi'])) {
            return $this->help();
        }

        if ($fc = $this->tryRevenueForecast($q)) {
            $this->clearOffer();

            return $fc;
        }

        if ($ev = $this->tryRevenueEvaluate($q)) {
            $this->clearOffer();

            return $ev;
        }

        if ($this->has($q, [
            'doanh thu', 'analytics', 'bao cao doanh thu', 'bieu do doanh thu', 'bieu do',
            'revenue', 'hoa hong hom nay', 'phi day tin',
        ])) {
            return $this->revenueBrief($expand);
        }

        if ($this->has($q, [
            'uu tien', 'uu tien hom nay', 'dashboard', 'tong quan', 'lam gi truoc', 'hang uu tien',
            'tinh hinh', 'bao cao hom nay', 'viec gap', 'backlog',
        ])) {
            return $this->priority($expand);
        }

        if ($this->has($q, ['thong ke', 'so lieu', 'bao cao san', 'overview', 'summary san', 'san dang the nao', 'tinh trang san'])) {
            return $this->stats($expand);
        }

        if ($this->wantsUsers($q)) {
            return $this->usersDirectory($q, $expand);
        }

        if ($this->wantsOrders($q)) {
            return $this->ordersDirectory($q, $expand);
        }

        if ($this->has($q, ['kyc', 'cccd', 'dinh danh'])) {
            return $this->kycQueue($expand);
        }

        if ($this->has($q, ['khieu nai', 'dispute', 'tranh chap', 'hoan tien'])) {
            return $this->disputeQueue($q, $expand);
        }

        if ($this->has($q, ['tai chinh', 'escrow', 'doi soat', 'hoa hong', 'phi san', 'commission', 'nap vi', 'rut vi', 'vi cho', 'giao dich vi'])) {
            return $this->finance($expand);
        }

        if ($this->has($q, ['spam', 'trung lap', 'clone', 'nhai'])) {
            return $this->spamScan($expand);
        }
        if ($this->has($q, ['seal', 'nguon goc', 'xac thuc'])) {
            return $this->sealQueue($expand);
        }
        if ($this->has($q, ['tin cho', 'cho duyet', 'kiem duyet', 'rui ro', 'duyet tin', 'moderation', 'tin dang cho'])) {
            return $this->listingQueue($expand);
        }

        if ($this->has($q, ['tat ca tin', 'cac tin', 'tin dang', 'listing', 'bao nhieu tin', 'tin active', 'tin an'])) {
            return $this->listingsSummary($q, $expand);
        }

        if ($this->has($q, ['bao cao tin', 'report tin', 'tin bi bao cao'])
            || ($this->has($q, ['bao cao', 'report']) && ! $this->has($q, ['doanh thu', 'excel', 'xuat', 'bieu do', 'san pham']))) {
            return $this->reports($expand);
        }
        if ($this->has($q, ['khoa', 'mo khoa', 'ban user', 'nick bi khoa', 'lua dao'])) {
            return $this->userRisk($q, $expand);
        }
        if ($this->has($q, ['danh muc', 'hang thieu', 'brand thieu'])) {
            return $this->catalogGaps($expand);
        }
        if ($shop = $this->shopQuery($q)) {
            $this->clearOffer();

            return $this->shopBrief($shop);
        }
        if ($this->has($q, ['soan', 'draft', 'mau ly do', 'ghi chu'])) {
            return $this->draftTemplates($q);
        }
        if ($this->has($q, ['ghn', 'van don', 'ship'])) {
            return $this->shippingFlags($expand);
        }

        if ($soft = $this->softRoute($q, $expand)) {
            return $soft;
        }

        return $this->clarify($message);
    }

    public function isFollowUpReply(string $q): bool
    {
        return $this->isAffirm($q) || $this->isDeny($q);
    }

    private function consumeFollowUp(string $q): ?array
    {
        $offer = session('relic.ops.offer');
        if (! is_array($offer) || ! isset($offer['action'])) {
            return null;
        }

        if ($this->isDeny($q)) {
            $this->clearOffer();

            return $this->out('Ok, thôi. Cần gì cứ hỏi tiếp.', [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ]);
        }

        if (! $this->isAffirm($q)) {
            return null;
        }

        $action = $offer['action'];
        $payload = $offer;
        $this->clearOffer();

        return match ($action) {
            'users' => $this->usersDirectory((string) ($payload['q'] ?? 'liet ke nguoi dung'), true),
            'orders' => $this->ordersDirectory((string) ($payload['q'] ?? 'liet ke don hang'), true),
            'listings' => $this->listingsSummary((string) ($payload['q'] ?? 'cac tin'), true),
            'kyc' => $this->kycQueue(true),
            'disputes' => $this->disputeQueue((string) ($payload['q'] ?? 'khieu nai'), true),
            'finance' => $this->finance(true),
            'moderation' => $this->listingQueue(true),
            'seal' => $this->sealQueue(true),
            'spam' => $this->spamScan(true),
            'reports' => $this->reports(true),
            'shipping' => $this->shippingFlags(true),
            'risk' => $this->userRisk((string) ($payload['q'] ?? 'khoa'), true),
            'catalog' => $this->catalogGaps(true),
            'priority' => $this->priority(true),
            'stats' => $this->stats(true),
            'revenue' => $this->revenueBrief(true),
            'forecast' => $this->revenueForecastReply('month', null),
            'evaluate' => $this->revenueEvaluateReply('month'),
            'today' => $this->todayDigest(true),
            'catalog' => $this->catalogGaps(true),
            default => $this->out('Mình quên ngữ cảnh rồi — hỏi lại giúp nhé.'),
        };
    }

    private function isAffirm(string $q): bool
    {
        $t = trim($q);
        if ($t === '' || mb_strlen($t) > 36) {
            return false;
        }

        return (bool) preg_match('/^(co|co a|co nhe|ok|oke|okay|u|uh|uk|dc|duoc|duoc roi|yes|y|liet ke|liet ke di|xem|xem di|cho xem|cho xem tiep|tiep|them|muon|vang|da|chi tiet|chi tiet hon|day du|ua|u|dui|ok luon|hay|di)$/u', $t);
    }

    private function isDeny(string $q): bool
    {
        $t = trim($q);

        return (bool) preg_match('/^(khong|ko|khong a|thoi|thoi nhe|huy|no|khong can|khong can dau|de sau|thoi di|bo qua)$/u', $t);
    }

    private function wantsExpand(string $q): bool
    {
        return $this->has($q, [
            'liet ke', 'danh sach', 'xem het', 'chi tiet', 'day du', 'liet ke ra',
            'cho xem danh sach', 'ke ra', 'show', 'list het', 'ke chi tiet', 'noi ro',
        ]);
    }

    private function softCloser(): string
    {
        $pool = [
            'Xem list luôn không?',
            'Kể chi tiết thêm?',
            'Cần danh sách không?',
            'Mở rộng ra không?',
            'Muốn mình kể tiếp?',
        ];

        return $pool[array_rand($pool)];
    }

    private function offer(string $action, string $reply, array $links = [], array $extra = []): array
    {
        session(['relic.ops.offer' => array_merge(['action' => $action], $extra)]);
        $trimmed = rtrim($reply);
        if (! str_ends_with($trimmed, '?') && ! str_ends_with($trimmed, '？')) {
            $reply = $trimmed."\n".$this->softCloser();
        }

        return $this->out($reply, $links);
    }

    private function clearOffer(): void
    {
        session()->forget('relic.ops.offer');
    }

    private function out(string $reply, array $links = [], array $products = [], ?string $open = null): array
    {
        $payload = [
            'reply' => $reply,
            'links' => $links,
            'products' => $products,
        ];
        if ($open) {
            $payload['open'] = $open;
        }

        return $payload;
    }

    /** @return array{label: string, url: string}|null */
    private function resolveAdminPage(string $q): ?array
    {
        if ($this->has($q, ['tin cho', 'cho duyet', 'tin pending', 'pending review'])) {
            return ['label' => 'Tin chờ duyệt', 'url' => route('admin.listings', ['status' => 'pending_review'])];
        }
        if ($this->has($q, ['tin dang ban', 'tin active']) && $this->has($q, ['mo', 'vao', 'xem', 'toi'])) {
            return ['label' => 'Tin đang bán', 'url' => route('admin.listings', ['status' => 'active'])];
        }
        if ($this->has($q, ['vi cho', 'rut cho', 'nap cho', 'giao dich cho', 'pending vi', 'duyet vi'])) {
            return ['label' => 'Tài chính (ví chờ)', 'url' => route('admin.finance')];
        }

        $map = [
            [['doanh thu', 'analytics', 'bao cao doanh thu', 'bieu do doanh thu', 'revenue'], 'Doanh thu', 'admin.analytics'],
            [['dashboard', 'tong quan', 'uu tien', 'home admin'], 'Tổng quan', 'admin.dashboard'],
            [['tin dang', 'kiem duyet', 'listing', 'moderation', 'duyet tin'], 'Tin đăng', 'admin.listings'],
            [['don hang', 'orders', 'cac don'], 'Đơn hàng', 'admin.orders'],
            [['nguoi dung', 'users', 'thanh vien', 'cac user'], 'Người dùng', 'admin.users'],
            [['bao cao tin', 'report tin', 'tin bi bao', 'bao cao'], 'Báo cáo tin', 'admin.reports'],
            [['kyc', 'cccd', 'dinh danh'], 'KYC', 'admin.kyc'],
            [['khieu nai', 'dispute', 'tranh chap'], 'Khiếu nại', 'admin.disputes'],
            [['tai chinh', 'escrow', 'finance', 'doi soat'], 'Tài chính', 'admin.finance'],
            [['danh muc', 'category', 'categories'], 'Danh mục', 'admin.categories'],
        ];
        foreach ($map as [$keys, $label, $route]) {
            if ($this->has($q, $keys)) {
                return ['label' => $label, 'url' => route($route)];
            }
        }

        return null;
    }

    private function tryNavigate(string $q): ?array
    {
        if ($this->has($q, ['mo khoa', 'unlock', 'ban user'])) {
            return null;
        }

        $wants = $this->has($q, [
            'mo trang', 'mo muc', 'mo phan', 'di toi', 'chuyen toi', 'vao trang', 'dua toi',
            'nhay toi', 'nhay sang', 'mo cho toi', 'mo admin', 'mo dashboard', 'mo doanh thu', 'mo tin dang',
            'mo don hang', 'mo nguoi dung', 'mo kyc', 'mo khieu nai', 'mo tai chinh', 'mo danh muc',
            'mo bao cao', 'vao doanh thu', 'vao tin dang', 'vao don hang', 'vao kyc', 'vao tai chinh',
            'xem trang doanh thu', 'mo analytics', 'mo tin cho', 'cho minh xem', 'di admin',
            'xem phan', 'sang muc', 'mo vi cho',
        ]) || (bool) preg_match(
            '/\b(mo|vao|toi|di den|mo ra|nhay|sang)\b.{0,48}\b(dashboard|tong quan|doanh thu|analytics|tin dang|tin cho|don hang|nguoi dung|user|kyc|khieu nai|tai chinh|danh muc|bao cao|finance|vi cho)\b/u',
            $q
        );

        if (! $wants) {
            return null;
        }

        $page = $this->resolveAdminPage($q);
        if (! $page) {
            return $this->out(
                'Mình mở được hầu hết mục admin. Nói rõ hơn nhé — ví dụ “mở tin chờ”, “mở KYC”, “mở doanh thu”.',
                [['label' => 'Dashboard', 'url' => route('admin.dashboard')]]
            );
        }

        $openers = [
            "Ok, mở {$page['label']}…",
            "Đang chuyển sang {$page['label']}.",
            "Đây — {$page['label']}.",
        ];

        return $this->out(
            $openers[array_rand($openers)],
            [['label' => $page['label'], 'url' => $page['url']]],
            [],
            $page['url']
        );
    }

    private function tryExport(string $q): ?array
    {
        if (! $this->has($q, ['xuat', 'export', 'tai file', 'download', 'excel', 'csv', 'xuat bao cao', 'xuat file'])) {
            return null;
        }

        $kind = 'revenue';
        if ($this->has($q, ['san pham', 'product', 'top ban', 'top san pham'])) {
            $kind = 'products';
        } elseif ($this->has($q, ['don hang', 'cac don', 'order'])) {
            $kind = 'orders';
        }

        $days = 30;
        if ($this->has($q, ['7 ngay', 'mot tuan', '1 tuan'])) {
            $days = 7;
        } elseif ($this->has($q, ['14 ngay', 'hai tuan', '2 tuan'])) {
            $days = 14;
        } elseif ($this->has($q, ['90 ngay', '3 thang'])) {
            $days = 90;
        }

        $url = route('admin.analytics.export', ['kind' => $kind, 'days' => $days]);
        $labels = ['revenue' => 'doanh thu', 'products' => 'sản phẩm', 'orders' => 'đơn hàng'];

        return $this->out(
            'Đang xuất Excel '.$labels[$kind]." ({$days} ngày)… File CSV mở được bằng Excel.",
            [
                ['label' => 'Tải lại file', 'url' => $url],
                ['label' => 'Xem biểu đồ', 'url' => route('admin.analytics')],
            ],
            [],
            $url
        );
    }

    private function revenueBrief(bool $expand = false): array
    {
        /** @var AdminAnalyticsService $analytics */
        $analytics = app(AdminAnalyticsService::class);
        $s = $analytics->summary();
        $short = 'Doanh thu '.number_format($s['revenue']).'₫ (HH '.number_format($s['commission']).' + đẩy '.number_format($s['boost']).') · '
            .$s['orders'].' đơn · escrow giữ '.number_format($s['gmv_held']).'₫.';

        if (! $expand) {
            return $this->offer(
                'revenue',
                $short."\nMuốn chi tiết, dự tính 3–30 ngày, hay đánh giá doanh số không?",
                [
                    ['label' => 'Doanh thu', 'url' => route('admin.analytics')],
                    ['label' => 'Xuất Excel', 'url' => route('admin.analytics.export', ['kind' => 'revenue', 'days' => 30])],
                ]
            );
        }

        $this->clearOffer();
        $top = $analytics->topProducts(5);
        $lines = [
            $short,
            'GMV giải ngân '.number_format($s['gmv_released']).'₫ · user mới '.$s['new_users'].' · tin bán '.$s['listings_active'].'.',
            'Gõ “dự tính doanh thu 30 ngày” hoặc “đánh giá doanh số”.',
        ];
        if ($top->isNotEmpty()) {
            $lines[] = 'Top SP:';
            foreach ($top as $p) {
                $lines[] = '• '.$p->title.' · '.number_format((int) $p->revenue).'₫';
            }
        }

        return $this->out(implode("\n", $lines), [
            ['label' => 'Doanh thu + biểu đồ', 'url' => route('admin.analytics')],
            ['label' => 'Xuất Excel DT', 'url' => route('admin.analytics.export', ['kind' => 'revenue', 'days' => 30])],
            ['label' => 'Xuất Excel SP', 'url' => route('admin.analytics.export', ['kind' => 'products', 'days' => 30])],
        ]);
    }

    private function revenueBasis(string $q): string
    {
        return $this->has($q, ['quy', 'quý', 'quarter', '90 ngay', '3 thang']) ? 'quarter' : 'month';
    }

    private function forecastHorizon(string $q): ?int
    {
        if (preg_match('/\b(3|10|15|30)\s*ngay\b/u', $q, $m)) {
            return (int) $m[1];
        }
        if ($this->has($q, ['ba ngay', '3 ngay toi'])) {
            return 3;
        }
        if ($this->has($q, ['muoi ngay', '10 ngay toi'])) {
            return 10;
        }
        if ($this->has($q, ['muoi lam ngay', '15 ngay toi'])) {
            return 15;
        }
        if ($this->has($q, ['ba muoi ngay', 'mot thang toi', '1 thang toi', '30 ngay toi'])) {
            return 30;
        }

        return null;
    }

    private function tryRevenueForecast(string $q): ?array
    {
        $hit = $this->has($q, [
            'du tinh', 'du bao', 'du doan', 'uoc tinh', 'uoc luong', 'forecast', 'predict',
            'doanh thu toi', 'doanh thu sap toi', 'doanh thu trong', 'tinh doanh thu',
        ]) || (bool) preg_match('/\b(du tinh|du bao|uoc).{0,24}\b(doanh thu|revenue|hoa hong)\b/u', $q)
            || (bool) preg_match('/\b(doanh thu|revenue).{0,30}\b(3|10|15|30)\s*ngay\b/u', $q);

        if (! $hit) {
            return null;
        }

        return $this->revenueForecastReply($this->revenueBasis($q), $this->forecastHorizon($q));
    }

    private function tryRevenueEvaluate(string $q): ?array
    {
        $hit = $this->has($q, [
            'danh gia doanh', 'danh gia doanh so', 'danh gia doanh thu', 'danh gia so lieu',
            'phan tich doanh', 'phan tich doanh thu', 'phan tich so lieu', 'nhan xet doanh',
            'doanh so the nao', 'doanh thu the nao', 'tinh hinh doanh', 'cham diem doanh',
            'evaluate revenue', 'sales review', 'thang nay ban', 'ban hang the nao',
            'thang nay the nao', 'quy nay the nao', 'doanh so hom nay', 'ban ra sao',
        ]) || (bool) preg_match('/\b(danh gia|phan tich|nhan xet|cham diem)\b.{0,40}\b(doanh|so lieu|revenue|ban hang)\b/u', $q)
            || (bool) preg_match('/\b(thang|quy)\s+nay\b.{0,20}\b(ban|doanh|sao|the nao)\b/u', $q);

        if (! $hit) {
            return null;
        }

        return $this->revenueEvaluateReply($this->revenueBasis($q));
    }

    private function revenueForecastReply(string $basis, ?int $horizon = null): array
    {
        /** @var AdminAnalyticsService $analytics */
        $analytics = app(AdminAnalyticsService::class);
        $f = $analytics->forecast($basis);
        $label = $basis === 'quarter' ? 'quý (~90 ngày)' : 'tháng (~30 ngày)';
        $lines = [
            "Dự tính theo nền {$label}: TB ".number_format($f['avg_daily']).'₫/ngày · xu hướng '
            .($f['trend_pct'] >= 0 ? '+' : '').$f['trend_pct'].'% (so 7 ngày gần vs trước đó).',
        ];

        $targets = $horizon && isset($f['horizons'][$horizon])
            ? [$f['horizons'][$horizon]]
            : array_values($f['horizons']);

        foreach ($targets as $h) {
            $lines[] = sprintf(
                '• %d ngày tới: ~%s₫ (dải %s–%s) · ~%d đơn',
                $h['days'],
                number_format($h['mid']),
                number_format($h['low']),
                number_format($h['high']),
                $h['orders_mid']
            );
        }
        $lines[] = 'Ước lượng tham khảo — hỏi “đánh giá doanh số” để xem sức khỏe vận hành.';

        return $this->out(implode("\n", $lines), [
            ['label' => 'Doanh thu + dự tính', 'url' => route('admin.analytics')],
        ]);
    }

    private function revenueEvaluateReply(string $basis): array
    {
        /** @var AdminAnalyticsService $analytics */
        $analytics = app(AdminAnalyticsService::class);
        $e = $analytics->evaluate($basis);
        $lines = [$e['verdict']];
        foreach (array_slice($e['notes'], 0, 6) as $note) {
            $lines[] = '• '.$note;
        }
        $lines[] = 'Muốn dự tính tiếp? Gõ “dự tính doanh thu 15 ngày” (hoặc 3/10/30).';

        return $this->out(implode("\n", $lines), [
            ['label' => 'Xem trang Doanh thu', 'url' => route('admin.analytics')],
        ]);
    }

    private function softRoute(string $q, bool $expand = false): ?array
    {
        $asking = $this->has($q, [
            'hien tai', 'dang co', 'liet ke', 'cho xem', 'cho toi', 'xem', 'bao nhieu',
            'co bao nhieu', 'danh sach', 'tren san', 'moi nhat', 'hom nay',
        ]);
        if (! $asking) {
            return null;
        }
        if ($this->has($q, ['nguoi', 'user', 'thanh vien', 'khach', 'nick', 'tk ', 'tai khoan'])) {
            return $this->usersDirectory($q, $expand);
        }
        if ($this->has($q, ['don', 'order'])) {
            return $this->ordersDirectory($q, $expand);
        }
        if ($this->has($q, ['tin', 'listing', 'san pham', 'may', 'hang'])) {
            return $this->listingsSummary($q, $expand);
        }
        if ($this->has($q, ['vi', 'tien', 'escrow', 'tai chinh'])) {
            return $this->finance($expand);
        }

        return null;
    }

    private function isOpsGreet(string $q): bool
    {
        $compact = trim($q);
        if (in_array($compact, ['hello', 'hi', 'hey', 'alo', 'chao', 'xin chao', 'chao ban', 'chao ops'], true)) {
            return true;
        }

        return (bool) preg_match('/^(xin chao|chao|hello|hi|hey|alo)\b.{0,20}$/u', $compact);
    }

    private function opsHello(): array
    {
        $lines = [
            'Ê, Ops đây. Cần gì cứ nói.',
            'Chào admin. Hỏi gì mình nghe.',
            'Hi. Mình đang online — bảo việc đi.',
            'Alo alo. Cứ hỏi tự nhiên nhé.',
            'Chào. Không cần lịch sự đâu, hỏi thẳng luôn.',
        ];

        return $this->out($lines[array_rand($lines)]);
    }

    private function wantsUsers(string $q): bool
    {
        if ($this->userSearchNeedle($q)) {
            return true;
        }
        // “tk”, “bao nhiêu tk người dùng”, …
        if (preg_match('/\btk\b/u', $q) && $this->has($q, ['nguoi', 'user', 'dung', 'bao nhieu', 'co ', 'hien tai', 'may'])) {
            return true;
        }
        if ($this->has($q, [
            'nguoi dung', 'user', 'users', 'thanh vien', 'khach hang', 'tai khoan',
            'danh sach user', 'cac nick', 'ai dang ky', 'ai dang co', 'cac user',
            'thanh vien san', 'nick hien tai', 'ai tren san', 'bao nhieu user',
            'bao nhieu nguoi', 'co bao nhieu thanh vien', 'seller', 'nguoi ban',
            'buyer', 'nguoi mua', 'cac shop', 'bao nhieu tk', 'so tk',
        ])) {
            return true;
        }

        return (bool) preg_match('/\b(xem|liet ke|cho toi|co bao nhieu|danh sach|cac).{0,40}\b(user|nguoi|thanh vien|khach|nick|tai khoan|shop|tk)\b/u', $q)
            || (bool) preg_match('/\b(nguoi dung|thanh vien|khach hang|user).{0,24}\b(hien tai|dang co|tren san|moi|het)\b/u', $q)
            || (bool) preg_match('/\b(ai dang|co ai).{0,24}\b(dang ky|tren san|dung|mua|ban)\b/u', $q);
    }

    private function isCountQuestion(string $q): bool
    {
        return $this->has($q, ['bao nhieu', 'co bao nhieu', 'so luong', 'bao nhieu tk', 'may tk', 'may nguoi', 'may user'])
            || (bool) preg_match('/\b(bao nhieu|may)\b.{0,24}\b(user|nguoi|tk|tai khoan|don|tin|kyc)\b/u', $q);
    }

    private function wantsOrders(string $q): bool
    {
        if ($this->has($q, ['don hang', 'don moi', 'cac don', 'order', 'orders', 'don dang', 'don gan day'])) {
            return true;
        }

        return (bool) preg_match('/\b(xem|liet ke|cho toi|cac|bao nhieu).{0,24}\b(don|order)\b/u', $q)
            || (bool) preg_match('/\b(don|order).{0,20}\b(hien tai|moi|gan day|hom nay)\b/u', $q);
    }

    private function help(): array
    {
        $this->clearOffer();

        return $this->out(
            'Mình hỗ trợ vận hành: xem số liệu, mở trang, xuất Excel, dự tính/đánh giá doanh thu, tóm tắt hôm nay, xem tin/user theo ID. Cứ hỏi tự nhiên.',
            [
                ['label' => 'Doanh thu', 'url' => route('admin.analytics')],
            ]
        );
    }

    private function stats(bool $expand = false): array
    {
        $users = User::count();
        $active = Listing::where('status', 'active')->count();
        $pending = Listing::where('status', 'pending_review')->count();
        $orders = Order::count();
        $held = (int) Order::where('escrow_status', 'held')->sum('escrow_amount');
        $kyc = User::where('kyc_status', 'pending')->count();
        $openDis = Dispute::where('status', 'open')->count();

        if (! $expand) {
            return $this->offer(
                'stats',
                "Sàn: {$users} user · {$active} tin bán · {$pending} chờ duyệt · {$orders} đơn · KYC chờ {$kyc} · khiếu nại {$openDis} · escrow ".number_format($held)."₫.\nMuốn mình nói sâu hơn không?",
                [['label' => 'Dashboard', 'url' => route('admin.dashboard')]]
            );
        }

        $sellers = User::where(function ($w) {
            $w->where('is_seller', true)->orWhere('kyc_status', 'verified');
        })->count();
        $banned = User::where('is_banned', true)->count();

        return $this->out(
            "Chi tiết: seller/KYC ~{$sellers}, đang khóa {$banned}. Escrow giữ ".number_format($held)."₫. Hỏi “user” / “đơn” / “tin chờ” nếu cần list.",
            [
                ['label' => 'Người dùng', 'url' => route('admin.users')],
                ['label' => 'Đơn hàng', 'url' => route('admin.orders')],
            ]
        );
    }

    private function usersDirectory(string $q, bool $expand = false): array
    {
        $filter = 'tất cả';
        $needle = $this->userSearchNeedle($q);
        $query = User::query()->latest();

        if ($needle) {
            $query->where(function ($w) use ($needle) {
                $w->where('name', 'like', "%{$needle}%")
                    ->orWhere('email', 'like', "%{$needle}%")
                    ->orWhere('phone', 'like', "%{$needle}%");
            });
            $filter = '"'.$needle.'"';
        } elseif ($this->has($q, ['khoa', 'banned', 'bi khoa'])) {
            $query->where('is_banned', true);
            $filter = 'đang khóa';
        } elseif ($this->has($q, ['kyc', 'cho duyet', 'pending']) && ! $this->has($q, ['verified', 'da kyc']) && ! $this->isCountQuestion($q)) {
            $query->where('kyc_status', 'pending');
            $filter = 'KYC chờ';
        } elseif ($this->has($q, ['seller', 'nguoi ban', 'verified', 'da kyc'])) {
            $query->where(function ($w) {
                $w->where('is_seller', true)->orWhere('kyc_status', 'verified');
            });
            $filter = 'seller';
        } elseif ($this->has($q, ['buyer', 'nguoi mua']) || ($this->has($q, ['khach']) && ! $this->has($q, ['hang']))) {
            $query->where('role', '!=', 'admin')->where(function ($w) {
                $w->where('is_seller', false)->orWhereNull('is_seller');
            })->where('kyc_status', '!=', 'verified');
            $filter = 'buyer';
        } elseif ($this->has($q, ['admin'])) {
            $query->where('role', 'admin');
            $filter = 'admin';
        }

        $total = (clone $query)->count();
        $all = User::count();
        $banned = User::where('is_banned', true)->count();
        $kycPending = User::where('kyc_status', 'pending')->count();
        $sellers = User::where('is_seller', true)->count();
        $admins = User::where('role', 'admin')->count();
        $buyers = max(0, $all - $admins - $sellers);

        // Hỏi số → trả số, đúng trọng tâm.
        if (! $needle && $this->isCountQuestion($q) && ! $expand) {
            $this->clearOffer();
            if ($filter === 'tất cả') {
                return $this->out(
                    "Hiện có {$all} tài khoản người dùng (buyer ~{$buyers}, seller {$sellers}, admin {$admins}).",
                    [['label' => 'Người dùng', 'url' => route('admin.users')]]
                );
            }

            return $this->out(
                "Có {$total} user ({$filter}).",
                [['label' => 'Người dùng', 'url' => route('admin.users')]]
            );
        }

        // Tìm tên / yêu cầu list / expand → liệt kê ngắn.
        if ($needle || $expand || $this->wantsExpand($q) || $this->has($q, ['hien tai', 'dang co', 'cac nguoi', 'cac user', 'danh sach', 'cho xem', 'xem'])) {
            return $this->usersList($query, $filter === 'tất cả' ? 'gần đây' : $filter, $total);
        }

        // Câu mơ hồ về user → số + hỏi nhẹ (không dump).
        return $this->offer(
            'users',
            "Hiện có {$all} user · KYC chờ {$kycPending} · đang khóa {$banned}. Cần list tên không?",
            [['label' => 'Người dùng', 'url' => route('admin.users')]],
            ['q' => $q]
        );
    }

    private function usersList($query, string $filter, int $total): array
    {
        $this->clearOffer();
        $rows = $query->take(12)->get();
        if ($rows->isEmpty()) {
            return $this->out("Không có user nào khớp ({$filter}).", [
                ['label' => 'Người dùng', 'url' => route('admin.users')],
            ]);
        }

        $lines = ["{$total} user ({$filter}):"];
        foreach ($rows as $u) {
            $role = $u->isAdmin() ? 'admin' : ($u->is_seller || $u->kyc_status === 'verified' ? 'seller' : 'buyer');
            $ban = $u->is_banned ? ' · khóa' : '';
            $lines[] = sprintf(
                '• %s · %s · KYC %s · ví %s₫%s',
                $u->name,
                $role,
                $u->kyc_status ?: 'none',
                number_format((int) $u->wallet_balance),
                $ban
            );
        }

        $cards = $rows->map(function (User $u) {
            $role = $u->isAdmin() ? 'admin' : ($u->is_seller || $u->kyc_status === 'verified' ? 'seller' : 'buyer');

            return [
                'title' => $u->name,
                'price' => number_format((int) $u->wallet_balance).'₫ ví',
                'meta' => $role.' · KYC '.($u->kyc_status ?: 'none').($u->is_banned ? ' · khóa' : ''),
                'url' => route('admin.users'),
                'image' => null,
                'seal' => ($u->kyc_status ?? '') === 'verified',
            ];
        })->values()->all();

        return $this->out(implode("\n", $lines), [
            ['label' => 'Người dùng', 'url' => route('admin.users')],
        ], $cards);
    }

    private function userSearchNeedle(string $q): ?string
    {
        if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/u', $q, $m)) {
            return $m[0];
        }
        if (preg_match('/(?:tim(?:\s+kiem)?|shop|seller|user|nick|email)\s+(.+)$/u', $q, $m)) {
            $needle = trim($m[1]);
            $needle = preg_replace('/^(shop|seller|user|nick|email)\s+/u', '', $needle) ?? $needle;
            $needle = preg_replace('/\b(hien tai|giup|cho toi|xem|voi)\b/u', '', $needle) ?? $needle;
            $needle = trim($needle);
            if (mb_strlen($needle) >= 2 && ! in_array($needle, ['user', 'users', 'nguoi', 'dung', 'shop', 'seller', 'bi khoa'], true)) {
                return $needle;
            }
        }

        return null;
    }

    private function ordersDirectory(string $q, bool $expand = false): array
    {
        $query = Order::with('user')->latest();
        $filter = 'mới nhất';
        if ($this->has($q, ['paid', 'da thanh toan', 'escrow'])) {
            $query->where('status', 'paid');
            $filter = 'đã thanh toán';
        } elseif ($this->has($q, ['cod'])) {
            $query->where('status', 'cod_ordered');
            $filter = 'COD';
        } elseif ($this->has($q, ['cho thanh toan', 'pending'])) {
            $query->where('status', 'pending');
            $filter = 'chờ thanh toán';
        } elseif ($this->has($q, ['chua ghn', 'thieu ghn', 'no ghn'])) {
            $query->whereNull('ghn_order_code')->whereIn('status', ['paid', 'cod_ordered']);
            $filter = 'thiếu GHN';
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->clearOffer();

            return $this->out('Chưa có đơn nào.', [['label' => 'Đơn hàng', 'url' => route('admin.orders')]]);
        }

        if ($this->isCountQuestion($q) && ! $expand) {
            $this->clearOffer();

            return $this->out("Hiện có {$total} đơn ({$filter}).", [['label' => 'Đơn hàng', 'url' => route('admin.orders')]]);
        }

        // Hỏi đơn mới / list → liệt kê ngay (ngắn).
        if ($expand || $this->wantsExpand($q) || $this->has($q, ['moi', 'hien tai', 'gan day', 'cac don', 'liet ke', 'danh sach', 'xem'])) {
            $this->clearOffer();
            $rows = $query->take(8)->get();
            $lines = ["{$total} đơn:"];
            foreach ($rows as $o) {
                $lines[] = sprintf('• %s · %s · %s₫ · %s', $o->code, $o->statusLabel(), number_format((int) $o->total_price), $o->user?->name ?? '?');
            }
            $cards = $rows->map(fn (Order $o) => [
                'title' => $o->code,
                'price' => number_format((int) $o->total_price).'₫',
                'meta' => $o->statusLabel().' · '.($o->user?->name ?? '?'),
                'url' => route('admin.orders'),
                'image' => null,
                'seal' => $o->escrow_status === 'held',
            ])->values()->all();

            return $this->out(implode("\n", $lines), [['label' => 'Đơn hàng', 'url' => route('admin.orders')]], $cards);
        }

        return $this->offer(
            'orders',
            "Có {$total} đơn. Cần list mã đơn không?",
            [['label' => 'Đơn hàng', 'url' => route('admin.orders')]],
            ['q' => $q]
        );
    }

    private function listingsSummary(string $q, bool $expand = false): array
    {
        $byStatus = Listing::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        $active = (int) ($byStatus['active'] ?? 0);
        $pending = (int) ($byStatus['pending_review'] ?? 0);
        $hidden = (int) ($byStatus['hidden'] ?? 0);
        $sold = (int) ($byStatus['sold'] ?? 0);

        $status = 'active';
        $label = 'đang bán';
        if ($this->has($q, ['cho', 'pending', 'duyet'])) {
            $status = 'pending_review';
            $label = 'chờ duyệt';
        } elseif ($this->has($q, ['an', 'hidden'])) {
            $status = 'hidden';
            $label = 'đã ẩn';
        } elseif ($this->has($q, ['ban', 'sold'])) {
            $status = 'sold';
            $label = 'đã bán';
        }

        if (! $expand) {
            return $this->offer(
                'listings',
                "Tin: {$active} bán · {$pending} chờ · {$hidden} ẩn · {$sold} đã bán. Muốn liệt kê tin {$label} không?",
                [['label' => 'Tin đăng', 'url' => route('admin.listings')]],
                ['q' => $q]
            );
        }

        $this->clearOffer();
        $rows = Listing::with('seller')->where('status', $status)->latest()->take(8)->get();
        if ($rows->isEmpty()) {
            return $this->out("Chưa có tin {$label}.", [['label' => 'Tin đăng', 'url' => route('admin.listings')]]);
        }
        $lines = ["Tin {$label}:"];
        foreach ($rows as $l) {
            $lines[] = sprintf('• %s · %s₫ · %s', Str::limit($l->title, 36), number_format((int) $l->price), $l->seller?->name ?? '?');
        }
        $cards = $rows->map(fn (Listing $l) => [
            'title' => $l->title,
            'price' => number_format((int) $l->price).'₫',
            'meta' => $l->seller?->name ?? '?',
            'url' => route('admin.listings'),
            'image' => null,
            'seal' => false,
        ])->values()->all();

        return $this->out(implode("\n", $lines), [
            ['label' => 'Tin đăng', 'url' => route('admin.listings')],
            ['label' => 'Chợ', 'url' => route('listings.index')],
        ], $cards);
    }

    private function clarify(string $message): array
    {
        $this->clearOffer();
        $pool = [
            'Chưa nắm ý lắm. Thử hỏi kiểu: “hôm nay có gì mới?”, “bao nhiêu user?”, “mở tin chờ”.',
            'Hmm, mình chưa chắc. Bạn muốn xem số liệu, mở trang, hay xuất Excel?',
            'Nói cụ thể hơn một chút nhé — ví dụ “KYC chờ”, “đánh giá doanh số”, “đơn mới”.',
        ];

        return $this->out(
            $pool[array_rand($pool)],
            [['label' => 'Dashboard', 'url' => route('admin.dashboard')]]
        );
    }

    private function wantsToday(string $q): bool
    {
        if ($this->has($q, ['doanh thu', 'analytics', 'xuat', 'excel', 'du tinh', 'danh gia'])) {
            return false;
        }

        return $this->has($q, [
            'hom nay co gi', 'co gi moi', 'viec hom nay', 'moi hom nay',
            'cap nhat hom nay', 'digest', 'backlog', 'viec gap', 'con viec gi',
            'hom nay the nao', 'lam gi hom nay',
        ]) || (bool) preg_match('/\bhom nay\b.{0,24}\b(co gi|viec|moi|cap nhat|lam gi|gap)\b/u', $q)
            || (bool) preg_match('/\b(co gi moi|viec gap|backlog)\b/u', $q);
    }

    private function todayDigest(bool $expand = false): array
    {
        $start = now()->startOfDay();
        $newUsers = User::where('created_at', '>=', $start)->count();
        $newOrders = Order::where('created_at', '>=', $start)->count();
        $newListings = Listing::where('created_at', '>=', $start)->count();
        $pendingListings = Listing::where('status', 'pending_review')->count();
        $kyc = User::where('kyc_status', 'pending')->count();
        $dis = Dispute::where('status', 'open')->count();
        $wal = WalletTransaction::where('status', 'pending')->count();
        $reports = ListingReport::where('status', 'open')->count();

        $short = "Hôm nay: +{$newUsers} user · +{$newOrders} đơn · +{$newListings} tin mới. "
            ."Hàng đợi: {$pendingListings} tin chờ · {$kyc} KYC · {$dis} khiếu nại · {$wal} ví chờ · {$reports} báo cáo.";

        if (! $expand) {
            return $this->offer('today', $short, [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Tin chờ', 'url' => route('admin.listings', ['status' => 'pending_review'])],
            ]);
        }

        $this->clearOffer();
        $lines = [$short, 'Gợi ý: duyệt tin/KYC trước, rồi ví chờ và khiếu nại.'];
        $latest = Order::with('user')->where('created_at', '>=', $start)->latest()->take(3)->get();
        if ($latest->isNotEmpty()) {
            $lines[] = 'Đơn hôm nay:';
            foreach ($latest as $o) {
                $lines[] = '• '.$o->code.' · '.$o->status.' · '.number_format((int) $o->total_price).'₫';
            }
        }

        return $this->out(implode("\n", $lines), [
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'KYC', 'url' => route('admin.kyc')],
            ['label' => 'Tài chính', 'url' => route('admin.finance')],
        ]);
    }

    private function tryEntityBrief(string $q): ?array
    {
        if (preg_match('/\b(?:tin|listing)\s*#?\s*(\d+)\b/u', $q, $m)) {
            return $this->listingBrief((int) $m[1]);
        }
        if (preg_match('/\b(?:user|nguoi dung|tk|thanh vien)\s*#?\s*(\d+)\b/u', $q, $m)) {
            return $this->userBrief((int) $m[1]);
        }
        if (preg_match('/\b(?:tx|giao dich|vi)\s*#?\s*(\d+)\b/u', $q, $m)) {
            return $this->walletTxBrief((int) $m[1]);
        }

        return null;
    }

    private function listingBrief(int $id): array
    {
        $listing = Listing::with(['seller', 'category', 'origin'])->find($id);
        if (! $listing) {
            return $this->out("Không thấy tin #{$id}.", [['label' => 'Tin đăng', 'url' => route('admin.listings')]]);
        }
        $score = $this->scoreListing($listing);
        $origin = $listing->origin?->status ?? '—';

        return $this->out(
            "Tin #{$listing->id}: {$listing->title} · {$listing->statusLabel()} · ".number_format((int) $listing->price).'₫ · shop '
            .($listing->seller?->name ?? '?')." · DM ".($listing->category?->name ?? '—')
            ." · Seal {$origin} · rủi ro {$score['level']} ({$score['score']})"
            .($score['reasons'] ? ' — '.implode(', ', $score['reasons']) : '').'.',
            [['label' => 'Tin đăng', 'url' => route('admin.listings', ['status' => $listing->status])]]
        );
    }

    private function userBrief(int $id): array
    {
        $user = User::find($id);
        if (! $user) {
            return $this->out("Không thấy user #{$id}.", [['label' => 'Người dùng', 'url' => route('admin.users')]]);
        }
        $active = Listing::where('seller_id', $user->id)->where('status', 'active')->count();
        $orders = Order::where('user_id', $user->id)->count();

        return $this->out(
            "User #{$user->id}: {$user->name} · {$user->email} · KYC ".($user->kyc_status ?? '—')
            .' · '.($user->is_banned ? 'ĐANG KHÓA' : 'đang mở')
            ." · {$active} tin bán · {$orders} đơn mua.",
            [['label' => 'Người dùng', 'url' => route('admin.users')]]
        );
    }

    private function walletTxBrief(int $id): array
    {
        $tx = WalletTransaction::with('user')->find($id);
        if (! $tx) {
            return $this->out("Không thấy giao dịch ví #{$id}.", [['label' => 'Tài chính', 'url' => route('admin.finance')]]);
        }

        return $this->out(
            "TX #{$tx->id}: {$tx->typeLabel()} · ".number_format((int) $tx->amount).'₫ · '.$tx->status
            .' · '.($tx->user?->name ?? '?').' · '.($tx->note ?: 'không ghi chú'),
            [['label' => 'Tài chính', 'url' => route('admin.finance')]]
        );
    }

    private function priority(bool $expand = false): array
    {
        $dis = Dispute::where('status', 'open')->count();
        $wal = WalletTransaction::where('status', 'pending')->count();
        $kyc = User::where('kyc_status', 'pending')->count();
        $pendingListings = Listing::where('status', 'pending_review')->count();
        $seals = ListingOrigin::where('status', 'pending')->count();
        $reports = ListingReport::where('status', 'open')->count();
        $noGhn = Order::where('status', 'paid')->whereNull('ghn_order_code')->count();
        $held = (int) Order::where('escrow_status', 'held')->sum('escrow_amount');

        $bits = [];
        foreach ([
            [$dis, "{$dis} khiếu nại"],
            [$wal, "{$wal} nạp/rút chờ"],
            [$kyc, "{$kyc} KYC"],
            [$noGhn, "{$noGhn} paid thiếu GHN"],
            [$pendingListings, "{$pendingListings} tin chờ"],
            [$seals, "{$seals} Seal"],
            [$reports, "{$reports} báo cáo"],
        ] as [$n, $label]) {
            if ($n > 0) {
                $bits[] = $label;
            }
        }

        if ($bits === []) {
            $this->clearOffer();

            return $this->out(
                'Hàng đợi sạch. Escrow đang giữ '.number_format($held).'₫.',
                [['label' => 'Dashboard', 'url' => route('admin.dashboard')]]
            );
        }

        $summary = 'Ưu tiên: '.implode(' · ', $bits).'. Escrow '.number_format($held).'₫.';
        if (! $expand) {
            return $this->offer(
                'priority',
                $summary.' Muốn chi tiết từng mục không?',
                [
                    ['label' => 'Khiếu nại', 'url' => route('admin.disputes')],
                    ['label' => 'KYC', 'url' => route('admin.kyc')],
                    ['label' => 'Tài chính', 'url' => route('admin.finance')],
                ]
            );
        }

        $this->clearOffer();
        $lines = [$summary, 'Gợi ý làm trước (bạn bấm duyệt):'];
        $todo = [
            [$dis, 'Khiếu nại'],
            [$wal, 'Nạp/rút → Tài chính'],
            [$kyc, 'KYC'],
            [$noGhn, 'Paid thiếu GHN'],
            [$pendingListings, 'Tin chờ'],
            [$seals, 'Seal'],
            [$reports, 'Báo cáo'],
        ];
        usort($todo, fn ($a, $b) => $b[0] <=> $a[0]);
        foreach ($todo as [$n, $line]) {
            if ($n > 0) {
                $lines[] = "• {$line}";
            }
        }

        return $this->out(implode("\n", $lines), [
            ['label' => 'Khiếu nại', 'url' => route('admin.disputes')],
            ['label' => 'Tài chính', 'url' => route('admin.finance')],
            ['label' => 'KYC', 'url' => route('admin.kyc')],
        ]);
    }

    private function listingQueue(bool $expand = false): array
    {
        $pending = Listing::where('status', 'pending_review')->count();
        $sealPending = ListingOrigin::where('status', 'pending')->count();
        if ($pending === 0 && $sealPending === 0) {
            $this->clearOffer();

            return $this->out('Không có tin chờ duyệt / Seal.', [['label' => 'Tin đăng', 'url' => route('admin.listings')]]);
        }

        if (! $expand) {
            return $this->offer(
                'moderation',
                "Có {$pending} tin chờ duyệt · {$sealPending} Seal chờ. Muốn chấm rủi ro chi tiết không?",
                [['label' => 'Tin đăng', 'url' => route('admin.listings', ['status' => 'pending_review'])]]
            );
        }

        $this->clearOffer();
        $rows = Listing::with(['seller', 'images', 'origin', 'brand', 'category'])
            ->whereIn('status', ['pending_review', 'active'])
            ->where(function ($q) {
                $q->where('status', 'pending_review')
                    ->orWhereHas('origin', fn ($o) => $o->where('status', 'pending'));
            })
            ->latest()
            ->take(12)
            ->get();

        $scored = $rows->map(fn (Listing $l) => $this->scoreListing($l))
            ->sortByDesc('score')
            ->values()
            ->take(5);

        $lines = ['Rủi ro cao → làm trước:'];
        foreach ($scored as $row) {
            $lines[] = sprintf(
                '• [%s] #%d %s — %s₫ · %s',
                $row['level'],
                $row['id'],
                $row['title'],
                number_format($row['price']),
                implode('; ', array_slice($row['reasons'], 0, 2)) ?: 'ổn'
            );
        }

        return $this->out(implode("\n", $lines), [
            ['label' => 'Tin đăng', 'url' => route('admin.listings', ['status' => 'pending_review'])],
        ]);
    }

    private function scoreListing(Listing $listing): array
    {
        $reasons = [];
        $score = 0;
        $images = $listing->images->count();
        if ($images === 0) {
            $score += 40;
            $reasons[] = 'không có ảnh';
        } elseif ($images < 2) {
            $score += 15;
            $reasons[] = 'ít ảnh';
        }
        if (mb_strlen((string) $listing->description) < 40) {
            $score += 15;
            $reasons[] = 'mô tả ngắn';
        }
        $text = $this->norm($listing->title.' '.$listing->description);
        foreach (self::BANNED as $bad) {
            if (str_contains($text, $bad)) {
                $score += 35;
                $reasons[] = 'keyword cấm';
            }
        }
        if ($listing->original_price && (int) $listing->original_price > 0) {
            $ratio = (int) $listing->price / (int) $listing->original_price;
            if ($ratio < 0.25) {
                $score += 25;
                $reasons[] = 'giá bất thường';
            }
        }
        $seller = $listing->seller;
        if ($seller?->is_banned) {
            $score += 50;
            $reasons[] = 'shop khóa';
        }
        if ($seller && $seller->kyc_status !== 'verified') {
            $score += 30;
            $reasons[] = 'chưa KYC';
        }

        $level = $score >= 50 ? 'CAO' : ($score >= 25 ? 'TB' : 'THẤP');

        return [
            'id' => $listing->id,
            'title' => Str::limit($listing->title, 40),
            'price' => (int) $listing->price,
            'score' => $score,
            'level' => $level,
            'reasons' => $reasons,
        ];
    }

    private function sealQueue(bool $expand = false): array
    {
        $n = ListingOrigin::where('status', 'pending')->count();
        if ($n === 0) {
            $this->clearOffer();

            return $this->out('Không có Seal chờ.', [['label' => 'Tin đăng', 'url' => route('admin.listings')]]);
        }
        if (! $expand) {
            return $this->offer('seal', "Có {$n} hồ sơ Seal chờ. Muốn liệt kê không?", [
                ['label' => 'Tin đăng', 'url' => route('admin.listings')],
            ]);
        }
        $this->clearOffer();
        $rows = ListingOrigin::with('listing')->where('status', 'pending')->latest()->take(6)->get();
        $lines = ['Seal chờ:'];
        foreach ($rows as $origin) {
            $flags = [];
            if (! $origin->box_photo_path) {
                $flags[] = 'thiếu hộp';
            }
            if (! $origin->serial_last4) {
                $flags[] = 'thiếu serial';
            }
            $lines[] = '• Tin #'.($origin->listing?->id ?? '?').' '.Str::limit($origin->listing?->title ?? '', 28).' — '.($flags ? implode(', ', $flags) : 'đủ giấy tờ');
        }

        return $this->out(implode("\n", $lines), [['label' => 'Tin đăng', 'url' => route('admin.listings')]]);
    }

    private function spamScan(bool $expand = false): array
    {
        $active = Listing::public()->with('seller')->latest()->take(80)->get();
        $groups = $active->groupBy(fn (Listing $l) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $l->title) ?? $l->title)));
        $dupTitles = $groups->filter(fn (Collection $g) => $g->count() >= 2);
        $serialDup = ListingOrigin::query()
            ->selectRaw('serial_last4, COUNT(*) as c')
            ->whereNotNull('serial_last4')->where('serial_last4', '!=', '')
            ->groupBy('serial_last4')->having('c', '>', 1)->count();

        $dupN = $dupTitles->count();
        if ($dupN === 0 && $serialDup === 0) {
            $this->clearOffer();

            return $this->out('Không thấy title/serial trùng trong mẫu gần đây.', [
                ['label' => 'Tin đăng', 'url' => route('admin.listings')],
            ]);
        }
        if (! $expand) {
            return $this->offer('spam', "Phát hiện {$dupN} cụm title trùng · {$serialDup} serial trùng. Muốn xem chi tiết không?", [
                ['label' => 'Tin đăng', 'url' => route('admin.listings')],
            ]);
        }
        $this->clearOffer();
        $lines = ['Spam / trùng:'];
        foreach ($dupTitles->take(5) as $title => $group) {
            $lines[] = '• “'.Str::limit($title, 36).'” ×'.$group->count().' — '.$group->pluck('seller.name')->unique()->implode(', ');
        }

        return $this->out(implode("\n", $lines), [
            ['label' => 'Tin đăng', 'url' => route('admin.listings')],
        ]);
    }

    private function kycQueue(bool $expand = false): array
    {
        $n = User::where('kyc_status', 'pending')->count();
        if ($n === 0) {
            $this->clearOffer();

            return $this->out('Không có KYC chờ.', [['label' => 'KYC', 'url' => route('admin.kyc')]]);
        }
        if (! $expand && $n > 5) {
            return $this->offer('kyc', "Có {$n} KYC chờ duyệt. Cần list tên không?", [
                ['label' => 'KYC', 'url' => route('admin.kyc')],
            ]);
        }
        $this->clearOffer();
        $pending = User::where('kyc_status', 'pending')->latest('updated_at')->take(8)->get();
        $lines = ["{$n} KYC chờ:"];
        foreach ($pending as $user) {
            $flags = [];
            if (! $user->kyc_front_path || ! $user->kyc_back_path) {
                $flags[] = 'thiếu ảnh';
            }
            if ($user->kyc_id_last4) {
                $dupId = User::where('kyc_id_last4', $user->kyc_id_last4)->where('id', '!=', $user->id)->count();
                if ($dupId) {
                    $flags[] = 'CCCD trùng';
                }
            }
            $lines[] = '• '.$user->name.' — '.($flags ? implode(', ', $flags) : 'ổn để soi');
        }

        return $this->out(implode("\n", $lines), [['label' => 'Duyệt KYC', 'url' => route('admin.kyc')]]);
    }

    private function disputeQueue(string $q, bool $expand = false): array
    {
        $n = Dispute::where('status', 'open')->count();
        if ($n === 0) {
            $this->clearOffer();

            return $this->out('Không có khiếu nại mở.', [['label' => 'Khiếu nại', 'url' => route('admin.disputes')]]);
        }
        if (! $expand && $n > 3) {
            return $this->offer('disputes', "Có {$n} khiếu nại mở. Cần đề xuất chi tiết không?", [
                ['label' => 'Khiếu nại', 'url' => route('admin.disputes')],
            ], ['q' => $q]);
        }
        $this->clearOffer();
        $open = Dispute::with(['order.user', 'order.items', 'user'])->where('status', 'open')->latest()->take(6)->get();
        $lines = ["{$n} khiếu nại mở:"];
        foreach ($open as $dispute) {
            $order = $dispute->order;
            $evidence = 0;
            if ($dispute->evidence_path) {
                $evidence += 2;
            }
            if (mb_strlen((string) $dispute->detail) >= 40) {
                $evidence += 1;
            }
            $action = $evidence >= 2 ? 'Đề xuất HOÀN VÍ BUYER' : 'Đề xuất GIẢI NGÂN SHOP';
            $lines[] = sprintf('• #%d %s · %s · %s', $dispute->id, $order?->code ?? '?', $dispute->user?->name ?? '?', $action);
        }

        return $this->out(implode("\n", $lines), [['label' => 'Trung tâm khiếu nại', 'url' => route('admin.disputes')]]);
    }

    private function finance(bool $expand = false): array
    {
        $held = (int) Order::where('escrow_status', 'held')->sum('escrow_amount');
        $commission = (int) WalletTransaction::where('type', 'commission')->where('status', 'completed')->sum('amount');
        $pendingN = WalletTransaction::where('status', 'pending')->count();
        if (! $expand) {
            return $this->offer(
                'finance',
                'Escrow giữ '.number_format($held).'₫ · hoa hồng '.number_format($commission)."₫ · {$pendingN} giao dịch ví chờ. Muốn liệt kê pending không?",
                [['label' => 'Tài chính', 'url' => route('admin.finance')]]
            );
        }
        $this->clearOffer();
        $pending = WalletTransaction::with('user')->where('status', 'pending')->latest()->take(8)->get();
        $lines = [
            'Escrow '.number_format($held).'₫ · hoa hồng '.number_format($commission).'₫.',
            $pending->isEmpty() ? 'Không có nạp/rút pending.' : 'Pending:',
        ];
        foreach ($pending as $tx) {
            $flags = [];
            if ((int) $tx->amount >= 5_000_000) {
                $flags[] = 'số lớn';
            }
            if ($tx->type === 'withdraw') {
                $recentPayout = WalletTransaction::where('user_id', $tx->user_id)
                    ->where('type', 'payout')->where('status', 'completed')
                    ->where('created_at', '>=', now()->subHours(6))->exists();
                if ($recentPayout) {
                    $flags[] = 'cờ đỏ';
                }
            }
            $lines[] = sprintf(
                '• #%d %s %s · %s₫%s',
                $tx->id,
                $tx->typeLabel(),
                $tx->user?->name ?? '?',
                number_format((int) $tx->amount),
                $flags ? ' ['.implode(', ', $flags).']' : ''
            );
        }

        return $this->out(implode("\n", $lines), [
            ['label' => 'Tài chính', 'url' => route('admin.finance')],
        ]);
    }

    private function shippingFlags(bool $expand = false): array
    {
        $paidN = Order::where('status', 'paid')->whereNull('ghn_order_code')->count();
        $codN = Order::where('status', 'cod_ordered')->whereNull('ghn_order_code')->count();
        if ($paidN + $codN === 0) {
            $this->clearOffer();

            return $this->out('Không có đơn thiếu mã GHN.', [['label' => 'Đơn hàng', 'url' => route('admin.orders')]]);
        }
        if (! $expand) {
            return $this->offer('shipping', "Có {$paidN} MoMo paid + {$codN} COD thiếu GHN. Muốn liệt kê không?", [
                ['label' => 'Đơn hàng', 'url' => route('admin.orders')],
            ]);
        }
        $this->clearOffer();
        $rows = Order::whereNull('ghn_order_code')->whereIn('status', ['paid', 'cod_ordered'])->latest()->take(6)->get();
        $lines = ['Thiếu GHN:'];
        foreach ($rows as $o) {
            $lines[] = "• {$o->code} · {$o->statusLabel()}";
        }

        return $this->out(implode("\n", $lines), [['label' => 'Đơn hàng', 'url' => route('admin.orders')]]);
    }

    private function reports(bool $expand = false): array
    {
        $n = ListingReport::where('status', 'open')->count();
        if ($n === 0) {
            $this->clearOffer();

            return $this->out('Không có báo cáo mở.', [['label' => 'Báo cáo', 'url' => route('admin.reports')]]);
        }
        if (! $expand) {
            return $this->offer('reports', "Có {$n} báo cáo tin mở. Muốn liệt kê không?", [
                ['label' => 'Báo cáo', 'url' => route('admin.reports')],
            ]);
        }
        $this->clearOffer();
        $rows = ListingReport::with(['listing', 'user'])->where('status', 'open')->latest()->take(5)->get();
        $lines = ['Báo cáo mở:'];
        foreach ($rows as $r) {
            $lines[] = '• #'.$r->id.' '.Str::limit($r->listing?->title ?? '?', 32).' · '.$r->user?->name;
        }

        return $this->out(implode("\n", $lines), [['label' => 'Báo cáo', 'url' => route('admin.reports')]]);
    }

    private function userRisk(string $q, bool $expand = false): array
    {
        $banned = User::where('is_banned', true)->count();
        $dupN = User::query()
            ->selectRaw('kyc_id_last4, COUNT(*) as c')
            ->whereNotNull('kyc_id_last4')->where('kyc_id_last4', '!=', '')
            ->groupBy('kyc_id_last4')->having('c', '>', 1)->count();

        if ($shop = $this->shopQuery($q)) {
            $this->clearOffer();

            return $this->shopBrief($shop);
        }

        if (! $expand) {
            return $this->offer(
                'risk',
                "Đang khóa {$banned} nick · {$dupN} cụm CCCD trùng. Muốn xem chi tiết không?",
                [['label' => 'Người dùng', 'url' => route('admin.users')]],
                ['q' => $q]
            );
        }
        $this->clearOffer();
        $lines = ["Khóa: {$banned}. CCCD trùng: {$dupN}."];
        $dupLast4 = User::query()
            ->selectRaw('kyc_id_last4, COUNT(*) as c')
            ->whereNotNull('kyc_id_last4')->where('kyc_id_last4', '!=', '')
            ->groupBy('kyc_id_last4')->having('c', '>', 1)->orderByDesc('c')->take(5)->get();
        foreach ($dupLast4 as $row) {
            $names = User::where('kyc_id_last4', $row->kyc_id_last4)->pluck('name')->implode(', ');
            $lines[] = "• …{$row->kyc_id_last4} ×{$row->c}: {$names}";
        }
        $lines[] = 'Draft khóa: “Vi phạm chính sách Relic. Khóa tạm để xác minh.”';

        return $this->out(implode("\n", $lines), [['label' => 'Người dùng', 'url' => route('admin.users')]]);
    }

    private function catalogGaps(bool $expand = false): array
    {
        $total = Category::where('is_active', true)->count();
        if (! $expand) {
            return $this->offer(
                'catalog',
                "Có {$total} danh mục đang bật. Muốn xem số tin theo từng mục?",
                [['label' => 'Danh mục', 'url' => route('admin.categories')]]
            );
        }
        $this->clearOffer();
        $cats = Category::withCount(['listings' => fn ($q) => $q->where('status', 'active')])
            ->where('is_active', true)
            ->orderByDesc('listings_count')
            ->take(10)
            ->get();
        $lines = ['Danh mục (tin đang bán):'];
        foreach ($cats as $c) {
            $flag = (int) $c->listings_count === 0 ? ' · trống' : ((int) $c->listings_count < 3 ? ' · mỏng' : '');
            $lines[] = "• {$c->name}: {$c->listings_count}{$flag}";
        }

        return $this->out(implode("\n", $lines), [
            ['label' => 'Danh mục', 'url' => route('admin.categories')],
        ]);
    }

    private function shopQuery(string $q): ?User
    {
        if (! preg_match('/(?:shop|nguoi ban|seller|user)\s+(.+)$/u', $q, $m)) {
            return null;
        }
        $needle = trim($m[1]);
        if ($needle === '') {
            return null;
        }

        return User::query()
            ->where(function ($w) use ($needle) {
                $w->where('email', 'like', "%{$needle}%")
                    ->orWhere('name', 'like', "%{$needle}%");
            })
            ->first();
    }

    private function shopBrief(User $shop): array
    {
        $active = Listing::where('seller_id', $shop->id)->where('status', 'active')->count();

        return $this->out(
            sprintf(
                '%s <%s>: KYC %s%s · %d tin bán · ví %s₫.',
                $shop->name,
                $shop->email,
                $shop->kyc_status,
                $shop->is_banned ? ' · đang khóa' : '',
                $active,
                number_format((int) $shop->wallet_balance)
            ),
            [
                ['label' => 'Người dùng', 'url' => route('admin.users')],
                ['label' => 'Tin đăng', 'url' => route('admin.listings')],
            ]
        );
    }

    private function draftTemplates(string $q): array
    {
        $this->clearOffer();
        if ($this->has($q, ['khoa', 'ban'])) {
            return $this->out(
                "Mẫu khóa: “Vi phạm chính sách Relic. Khóa tạm để xác minh.”\n"
                ."Mẫu mở khóa: “Đã xác minh. Mở khóa — tái phạm sẽ khóa vĩnh viễn.”",
                [['label' => 'Người dùng', 'url' => route('admin.users')]]
            );
        }
        if ($this->has($q, ['kyc', 'tu choi kyc'])) {
            return $this->out(
                "Mẫu từ chối KYC: “Ảnh CCCD chưa rõ / không khớp thông tin. Vui lòng chụp lại mặt trước–sau trong điều kiện đủ sáng.”\n"
                ."Mẫu duyệt: không cần ghi chú — bấm Duyệt trên trang KYC.",
                [['label' => 'KYC', 'url' => route('admin.kyc')]]
            );
        }
        if ($this->has($q, ['seal', 'tu choi tin', 'reject'])) {
            return $this->out(
                "Mẫu từ chối tin: “Tin thiếu ảnh thật / mô tả chưa đủ. Bổ sung rồi gửi lại.”\n"
                ."Mẫu Seal: “Thiếu ảnh hộp/serial — chưa đủ điều kiện Relic Seal.”",
                [['label' => 'Tin đăng', 'url' => route('admin.listings', ['status' => 'pending_review'])]]
            );
        }
        if ($this->has($q, ['khieu nai', 'dispute', 'hoan'])) {
            return $this->out(
                "Mẫu giải ngân shop: “Đã đối chiếu bằng chứng — giải ngân người bán.”\n"
                ."Mẫu hoàn buyer: “Sai mô tả / bằng chứng đủ — hoàn ví người mua.”",
                [['label' => 'Khiếu nại', 'url' => route('admin.disputes')]]
            );
        }

        return $this->out(
            'Mình có mẫu: khóa user, từ chối KYC, từ chối tin/Seal, xử lý khiếu nại. Nói “soạn lý do khóa” hoặc “soạn từ chối KYC”.',
            [['label' => 'Dashboard', 'url' => route('admin.dashboard')]]
        );
    }

    private function orderBrief(string $code): array
    {
        $order = Order::with(['user', 'items', 'disputes', 'paymentTransactions'])->where('code', $code)->first();
        if (! $order) {
            return $this->out('Không thấy đơn '.$code.'.', [['label' => 'Đơn hàng', 'url' => route('admin.orders')]]);
        }
        $openDisputes = $order->disputes->where('status', 'open')->count();
        $tip = $openDisputes
            ? 'Ưu tiên xử lý khiếu nại.'
            : ($order->escrow_status === 'held' ? 'Escrow đang giữ — chờ nhận hàng / khiếu nại.' : 'Không cần đụng escrow gấp.');

        return $this->out(
            "{$order->code}: {$order->statusLabel()} / {$order->escrowLabel()} · ".number_format((int) $order->total_price).'₫ · buyer '.$order->user?->name.' · GHN '.($order->ghn_order_code ?: 'chưa').". {$tip}",
            [['label' => 'Đơn hàng', 'url' => route('admin.orders')]]
        );
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
