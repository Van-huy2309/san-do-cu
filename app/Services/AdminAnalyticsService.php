<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminAnalyticsService
{
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subDays(30)->startOfDay();
        $to = $to ?? now()->endOfDay();

        $commission = (int) WalletTransaction::query()
            ->where('type', 'commission')->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])->sum('amount');
        $boost = (int) WalletTransaction::query()
            ->where('type', 'boost')->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])->sum('amount');
        $gmvReleased = (int) Order::query()
            ->where('escrow_status', 'released')
            ->whereBetween('released_at', [$from, $to])->sum('escrow_amount');
        $gmvHeld = (int) Order::where('escrow_status', 'held')->sum('escrow_amount');
        $orders = Order::whereBetween('created_at', [$from, $to])->count();
        $paidOrders = Order::whereIn('status', ['paid', 'completed', 'cod_ordered'])
            ->whereBetween('created_at', [$from, $to])->count();

        return [
            'from' => $from,
            'to' => $to,
            'commission' => $commission,
            'boost' => $boost,
            'revenue' => $commission + $boost,
            'gmv_released' => $gmvReleased,
            'gmv_held' => $gmvHeld,
            'orders' => $orders,
            'paid_orders' => $paidOrders,
            'users' => User::count(),
            'new_users' => User::whereBetween('created_at', [$from, $to])->count(),
            'listings_active' => Listing::where('status', 'active')->count(),
            'listings_pending' => Listing::where('status', 'pending_review')->count(),
            'kyc_pending' => User::where('kyc_status', 'pending')->count(),
            'disputes_open' => Dispute::where('status', 'open')->count(),
            'reports_open' => ListingReport::where('status', 'open')->count(),
            'wallet_pending' => WalletTransaction::where('status', 'pending')->count(),
        ];
    }

    /** @return array{labels: list<string>, commission: list<int>, boost: list<int>, orders: list<int>} */
    public function dailySeries(int $days = 30): array
    {
        $days = max(7, min(90, $days));
        $start = now()->subDays($days - 1)->startOfDay();
        $labels = [];
        $commission = [];
        $boost = [];
        $orders = [];

        $commMap = WalletTransaction::query()
            ->selectRaw('DATE(created_at) as d, SUM(amount) as s')
            ->where('type', 'commission')->where('status', 'completed')
            ->where('created_at', '>=', $start)
            ->groupBy('d')->pluck('s', 'd');
        $boostMap = WalletTransaction::query()
            ->selectRaw('DATE(created_at) as d, SUM(amount) as s')
            ->where('type', 'boost')->where('status', 'completed')
            ->where('created_at', '>=', $start)
            ->groupBy('d')->pluck('s', 'd');
        $orderMap = Order::query()
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->where('created_at', '>=', $start)
            ->groupBy('d')->pluck('c', 'd');

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('d/m');
            $commission[] = (int) ($commMap[$key] ?? 0);
            $boost[] = (int) ($boostMap[$key] ?? 0);
            $orders[] = (int) ($orderMap[$key] ?? 0);
        }

        return compact('labels', 'commission', 'boost', 'orders');
    }

    /** @return array<string, int> */
    public function ordersByStatus(): array
    {
        return Order::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<string, int> */
    public function listingsByStatus(): array
    {
        return Listing::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<string, int> */
    public function usersByKyc(): array
    {
        return User::query()->selectRaw('kyc_status, COUNT(*) as c')->groupBy('kyc_status')->pluck('c', 'kyc_status')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<string, int> */
    public function disputesByStatus(): array
    {
        return Dispute::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<string, int> */
    public function reportsByStatus(): array
    {
        return ListingReport::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v)->all();
    }

    /** @return Collection<int, object> */
    public function topProducts(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = $from ?? now()->subDays(30)->startOfDay();
        $to = $to ?? now()->endOfDay();

        return OrderItem::query()
            ->select('title', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(price * quantity) as revenue'))
            ->whereHas('order', function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                    ->whereIn('status', ['paid', 'completed', 'cod_ordered']);
            })
            ->groupBy('title')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /**
     * Dự tính doanh thu (hoa hồng + đẩy tin) cho các horizon tới.
     * basis: month (30 ngày) | quarter (90 ngày).
     *
     * @return array{
     *   basis: string,
     *   basis_days: int,
     *   basis_revenue: int,
     *   avg_daily: int,
     *   trend_pct: float,
     *   horizons: array<int, array{days: int, mid: int, low: int, high: int, orders_mid: int}>
     * }
     */
    public function forecast(string $basis = 'month'): array
    {
        $basis = $basis === 'quarter' ? 'quarter' : 'month';
        $basisDays = $basis === 'quarter' ? 90 : 30;
        $series = $this->dailySeries($basisDays);
        $daily = [];
        for ($i = 0; $i < count($series['labels']); $i++) {
            $daily[] = (int) ($series['commission'][$i] ?? 0) + (int) ($series['boost'][$i] ?? 0);
        }
        $n = count($daily);
        $basisRevenue = array_sum($daily);
        $avgDaily = $n > 0 ? $basisRevenue / $n : 0.0;

        $win = min(7, max(1, intdiv($n, 2)));
        $recent = array_slice($daily, -$win);
        $prior = array_slice($daily, -$win * 2, $win);
        $recentAvg = count($recent) ? array_sum($recent) / count($recent) : 0.0;
        $priorAvg = count($prior) ? array_sum($prior) / count($prior) : $recentAvg;
        $trendFactor = $priorAvg > 0 ? ($recentAvg / $priorAvg) : ($recentAvg > 0 ? 1.1 : 1.0);
        $trendFactor = max(0.55, min(1.55, $trendFactor));
        $trendPct = round(($trendFactor - 1) * 100, 1);

        $orderAvg = $n > 0 ? array_sum($series['orders']) / $n : 0.0;
        $horizons = [];
        foreach ([3, 10, 15, 30] as $h) {
            $mid = (int) round($avgDaily * $trendFactor * $h);
            $horizons[$h] = [
                'days' => $h,
                'mid' => $mid,
                'low' => (int) round($mid * 0.82),
                'high' => (int) round($mid * 1.18),
                'orders_mid' => (int) max(0, round($orderAvg * $trendFactor * $h)),
            ];
        }

        return [
            'basis' => $basis,
            'basis_days' => $basisDays,
            'basis_revenue' => $basisRevenue,
            'avg_daily' => (int) round($avgDaily),
            'trend_pct' => $trendPct,
            'horizons' => $horizons,
        ];
    }

    /**
     * Đánh giá doanh số + số liệu liên quan so với kỳ trước cùng độ dài.
     *
     * @return array{
     *   basis: string,
     *   basis_days: int,
     *   current: array,
     *   previous: array,
     *   deltas: array<string, array{now: int, was: int, pct: float|null}>,
     *   score: int,
     *   grade: string,
     *   verdict: string,
     *   notes: list<string>
     * }
     */
    public function evaluate(string $basis = 'month'): array
    {
        $basis = $basis === 'quarter' ? 'quarter' : 'month';
        $days = $basis === 'quarter' ? 90 : 30;
        $curFrom = now()->subDays($days - 1)->startOfDay();
        $curTo = now()->endOfDay();
        $prevFrom = now()->subDays($days * 2 - 1)->startOfDay();
        $prevTo = now()->subDays($days)->endOfDay();

        $current = $this->summary($curFrom, $curTo);
        $previous = $this->summary($prevFrom, $prevTo);

        $keys = ['revenue', 'commission', 'boost', 'orders', 'paid_orders', 'gmv_released', 'new_users'];
        $deltas = [];
        foreach ($keys as $key) {
            $now = (int) ($current[$key] ?? 0);
            $was = (int) ($previous[$key] ?? 0);
            $deltas[$key] = [
                'now' => $now,
                'was' => $was,
                'pct' => $this->pctChange($now, $was),
            ];
        }

        $score = 50;
        $revPct = $deltas['revenue']['pct'];
        if ($revPct === null) {
            $score += $deltas['revenue']['now'] > 0 ? 15 : -5;
        } else {
            $score += (int) max(-20, min(25, round($revPct / 2)));
        }
        $ordPct = $deltas['orders']['pct'];
        if ($ordPct !== null) {
            $score += (int) max(-10, min(15, round($ordPct / 3)));
        }
        if ($current['disputes_open'] > 5) {
            $score -= 8;
        } elseif ($current['disputes_open'] > 0) {
            $score -= 3;
        }
        if ($current['kyc_pending'] > 10) {
            $score -= 5;
        }
        if ($current['listings_pending'] > 15) {
            $score -= 4;
        }
        if ($current['gmv_held'] > 0 && $current['revenue'] > 0) {
            $score += 3;
        }
        $score = max(0, min(100, $score));

        $grade = match (true) {
            $score >= 80 => 'Tốt',
            $score >= 65 => 'Khá',
            $score >= 45 => 'Trung bình',
            $score >= 30 => 'Yếu',
            default => 'Cần cải thiện',
        };

        $notes = [];
        $notes[] = $this->deltaNote('Doanh thu sàn', $deltas['revenue'], true);
        $notes[] = $this->deltaNote('Đơn hàng', $deltas['orders'], false);
        $notes[] = $this->deltaNote('GMV giải ngân', $deltas['gmv_released'], true);
        $notes[] = $this->deltaNote('User mới', $deltas['new_users'], false);
        if ($current['disputes_open'] > 0) {
            $notes[] = 'Có '.$current['disputes_open'].' khiếu nại mở — ảnh hưởng niềm tin & giải ngân.';
        } else {
            $notes[] = 'Không có khiếu nại mở — tín hiệu vận hành ổn.';
        }
        if ($current['kyc_pending'] > 0) {
            $notes[] = $current['kyc_pending'].' KYC chờ duyệt — tắc KYC làm chậm tin mới.';
        }
        if ($current['listings_pending'] > 0) {
            $notes[] = $current['listings_pending'].' tin chờ duyệt — duyệt sớm để giữ cung.';
        }
        if ($deltas['boost']['now'] === 0 && $deltas['commission']['now'] > 0) {
            $notes[] = 'Chưa có thu đẩy tin trong kỳ — có thể gợi ý shop boost tin nổi.';
        }

        $label = $basis === 'quarter' ? 'quý (~90 ngày)' : 'tháng (~30 ngày)';
        $verdict = "Đánh giá {$label}: điểm {$score}/100 ({$grade}). "
            .($revPct === null
                ? ($deltas['revenue']['now'] > 0 ? 'Có doanh thu kỳ này (kỳ trước trống).' : 'Chưa có doanh thu đủ để so sánh.')
                : ('Doanh thu '.($revPct >= 0 ? 'tăng' : 'giảm').' '.abs($revPct).'% so với kỳ trước.'));

        return [
            'basis' => $basis,
            'basis_days' => $days,
            'current' => $current,
            'previous' => $previous,
            'deltas' => $deltas,
            'score' => $score,
            'grade' => $grade,
            'verdict' => $verdict,
            'notes' => $notes,
        ];
    }

    private function pctChange(int $now, int $was): ?float
    {
        if ($was === 0) {
            return $now > 0 ? 100.0 : null;
        }

        return round(($now - $was) / $was * 100, 1);
    }

    /** @param array{now: int, was: int, pct: float|null} $delta */
    private function deltaNote(string $label, array $delta, bool $money): string
    {
        $now = $money ? number_format($delta['now']).'₫' : (string) $delta['now'];
        $was = $money ? number_format($delta['was']).'₫' : (string) $delta['was'];
        if ($delta['pct'] === null) {
            return "{$label}: {$now} (kỳ trước {$was}).";
        }
        $arrow = $delta['pct'] > 0 ? '↑' : ($delta['pct'] < 0 ? '↓' : '→');

        return "{$label}: {$now} {$arrow} ".abs($delta['pct'])."% (kỳ trước {$was}).";
    }

    /** Xuất CSV Excel-friendly (UTF-8 BOM). */
    public function exportCsv(string $kind = 'revenue', ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subDays(30)->startOfDay();
        $to = $to ?? now()->endOfDay();
        $kind = in_array($kind, ['revenue', 'products', 'orders'], true) ? $kind : 'revenue';

        $rows = match ($kind) {
            'products' => $this->productExportRows($from, $to),
            'orders' => $this->orderExportRows($from, $to),
            default => $this->revenueExportRows($from, $to),
        };

        $filename = 'relic-'.$kind.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'-'.Str::lower(Str::random(4)).'.csv';
        $relative = 'reports/'.$filename;

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        rewind($fh);
        $content = stream_get_contents($fh) ?: '';
        fclose($fh);

        Storage::disk('local')->makeDirectory('reports');
        Storage::disk('local')->put($relative, $content);

        return [
            'content' => $content,
            'path' => Storage::disk('local')->path($relative),
            'relative' => $relative,
            'filename' => $filename,
            'kind' => $kind,
            'rows' => max(0, count($rows) - 1),
            'from' => $from,
            'to' => $to,
        ];
    }

    /** @return list<list<string|int>> */
    private function revenueExportRows(Carbon $from, Carbon $to): array
    {
        $rows = [['Ngày', 'Loại', 'Số tiền (₫)', 'Ghi chú', 'User ID', 'Order ID']];
        $txs = WalletTransaction::query()
            ->whereIn('type', ['commission', 'boost'])
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->get();
        foreach ($txs as $tx) {
            $rows[] = [
                $tx->created_at?->format('Y-m-d H:i') ?? '',
                $tx->type,
                (int) $tx->amount,
                (string) $tx->note,
                (int) $tx->user_id,
                $tx->order_id ?? '',
            ];
        }

        return $rows;
    }

    /** @return list<list<string|int>> */
    private function productExportRows(Carbon $from, Carbon $to): array
    {
        $rows = [['Sản phẩm', 'Số lượng', 'Doanh thu item (₫)']];
        foreach ($this->topProducts(100, $from, $to) as $p) {
            $rows[] = [$p->title, (int) $p->qty, (int) $p->revenue];
        }

        return $rows;
    }

    /** @return list<list<string|int>> */
    private function orderExportRows(Carbon $from, Carbon $to): array
    {
        $rows = [['Mã đơn', 'Trạng thái', 'Escrow', 'Tổng (₫)', 'Escrow amount', 'Buyer', 'Ngày']];
        $orders = Order::with('user')->whereBetween('created_at', [$from, $to])->latest()->get();
        foreach ($orders as $o) {
            $rows[] = [
                $o->code,
                $o->status,
                $o->escrow_status,
                (int) $o->total_price,
                (int) $o->escrow_amount,
                $o->user?->email ?? '',
                $o->created_at?->format('Y-m-d H:i') ?? '',
            ];
        }

        return $rows;
    }
}
