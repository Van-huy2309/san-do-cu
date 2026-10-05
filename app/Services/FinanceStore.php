<?php

namespace App\Services;

use App\Models\Finance\BankAccount;
use App\Models\Finance\LedgerEntry;
use Illuminate\Support\Carbon;

class FinanceStore
{
    public function hasBank(int $userId): bool
    {
        return BankAccount::query()->where('user_id', $userId)->exists();
    }

    public function bankFor(int $userId): ?BankSnapshot
    {
        $row = BankAccount::query()->where('user_id', $userId)->first();

        return $row ? BankSnapshot::fromAccount($row) : null;
    }

    public function saveBank(int $userId, string $bankName, string $holder, string $number): BankSnapshot
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';
        $row = BankAccount::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'bank_name' => $bankName,
                'account_holder' => $holder,
                'account_number' => $digits,
                'account_last4' => substr($digits, -4),
            ]
        );

        return BankSnapshot::fromAccount($row);
    }

    public function receive(int $orderId, int $amount): void
    {
        $this->write($orderId, 0, LedgerEntry::BUYER_IN, $amount);
    }

    public function refund(int $orderId, int $amount): void
    {
        $this->write($orderId, 0, LedgerEntry::BUYER_REFUND, $amount);
    }

    /**
     * @param  iterable<int, array{seller_id: int, fee: int, net: int}>  $rows
     */
    public function settle(int $orderId, int $merchandise, iterable $rows): void
    {
        if (! LedgerEntry::query()->where('order_id', $orderId)->where('type', LedgerEntry::BUYER_IN)->exists()) {
            $this->receive($orderId, $merchandise);
        }

        foreach ($rows as $row) {
            $sellerId = (int) $row['seller_id'];
            $this->write($orderId, $sellerId, LedgerEntry::PLATFORM_FEE, (int) $row['fee']);
            $this->write($orderId, $sellerId, LedgerEntry::SELLER_PAYOUT, (int) $row['net']);
        }
    }

    /**
     * @param  list<int>  $orderIds
     * @return array{income: int, expense: int, series: array{labels: list<string>, income: list<int>, expense: list<int>}, entries: list<LedgerRow>, account: ?BankSnapshot}
     */
    public function sellerReport(int $sellerId, array $orderIds, int $days): array
    {
        $entries = [];
        if ($orderIds !== []) {
            $entries = LedgerEntry::query()
                ->where('seller_id', $sellerId)
                ->whereIn('order_id', $orderIds)
                ->whereIn('type', [LedgerEntry::PLATFORM_FEE, LedgerEntry::SELLER_PAYOUT])
                ->get()
                ->map(fn (LedgerEntry $entry) => LedgerRow::fromEntry($entry))
                ->all();
        }

        return [
            'income' => $this->sumType(LedgerEntry::SELLER_PAYOUT, $sellerId),
            'expense' => $this->sumType(LedgerEntry::PLATFORM_FEE, $sellerId),
            'series' => $this->sellerSeries($sellerId, $days),
            'entries' => $entries,
            'account' => $this->bankFor($sellerId),
        ];
    }

    /**
     * @return array{totals: array{buyer: int, fee: int, payout: int, refund: int, holding: int}, series: array{labels: list<string>, buyer: list<int>, fee: list<int>, payout: list<int>}, entries: list<LedgerRow>, accounts: list<BankSnapshot>}
     */
    public function adminReport(int $days, int $limit): array
    {
        $buyer = $this->sumType(LedgerEntry::BUYER_IN);
        $fee = $this->sumType(LedgerEntry::PLATFORM_FEE);
        $payout = $this->sumType(LedgerEntry::SELLER_PAYOUT);
        $refund = $this->sumType(LedgerEntry::BUYER_REFUND);
        $query = LedgerEntry::query();
        $entries = ($limit > 0 ? $query->latest('occurred_at')->limit($limit) : $query->orderBy('occurred_at'))
            ->get()
            ->map(fn (LedgerEntry $entry) => LedgerRow::fromEntry($entry))
            ->all();
        $accounts = BankAccount::query()->latest()->limit(30)->get()
            ->map(fn (BankAccount $account) => BankSnapshot::fromAccount($account))
            ->all();

        return [
            'totals' => [
                'buyer' => $buyer,
                'fee' => $fee,
                'payout' => $payout,
                'refund' => $refund,
                'holding' => max(0, $buyer - $fee - $payout - $refund),
            ],
            'series' => $this->adminSeries($days),
            'entries' => $entries,
            'accounts' => $accounts,
        ];
    }

    private function write(int $orderId, int $sellerId, string $type, int $amount): void
    {
        if ($amount < 1 && $type !== LedgerEntry::PLATFORM_FEE) {
            return;
        }

        LedgerEntry::query()->firstOrCreate(
            ['order_id' => $orderId, 'seller_id' => $sellerId, 'type' => $type],
            ['amount' => $amount, 'occurred_at' => now()]
        );
    }

    private function sumType(string $type, ?int $sellerId = null): int
    {
        $query = LedgerEntry::query()->where('type', $type);
        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }

        return (int) $query->sum('amount');
    }

    /**
     * @return array{labels: list<string>, income: list<int>, expense: list<int>}
     */
    private function sellerSeries(int $sellerId, int $days): array
    {
        $built = $this->daily(
            $days,
            [LedgerEntry::SELLER_PAYOUT, LedgerEntry::PLATFORM_FEE],
            $sellerId
        );

        return [
            'labels' => $built['labels'],
            'income' => $built[LedgerEntry::SELLER_PAYOUT],
            'expense' => $built[LedgerEntry::PLATFORM_FEE],
        ];
    }

    /**
     * @return array{labels: list<string>, buyer: list<int>, fee: list<int>, payout: list<int>}
     */
    private function adminSeries(int $days): array
    {
        $built = $this->daily($days, [LedgerEntry::BUYER_IN, LedgerEntry::PLATFORM_FEE, LedgerEntry::SELLER_PAYOUT]);

        return [
            'labels' => $built['labels'],
            'buyer' => $built[LedgerEntry::BUYER_IN],
            'fee' => $built[LedgerEntry::PLATFORM_FEE],
            'payout' => $built[LedgerEntry::SELLER_PAYOUT],
        ];
    }

    /**
     * @param  list<string>  $types
     * @return array<string, mixed>
     */
    private function daily(int $days, array $types, ?int $sellerId = null): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $query = LedgerEntry::query()
            ->where('occurred_at', '>=', $from)
            ->whereIn('type', $types);
        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }
        $rows = $query
            ->select('type', \Illuminate\Support\Facades\DB::raw('DATE(occurred_at) as day'), \Illuminate\Support\Facades\DB::raw('SUM(amount) as total'))
            ->groupBy('type', \Illuminate\Support\Facades\DB::raw('DATE(occurred_at)'))
            ->get();

        $labels = [];
        $columns = [];
        foreach ($types as $type) {
            $columns[$type] = [];
        }
        for ($i = 0; $i < $days; $i++) {
            $point = $from->copy()->addDays($i);
            $day = $point->toDateString();
            $labels[] = $point->format('d/m');
            foreach ($types as $type) {
                $match = $rows->first(function ($row) use ($type, $day) {
                    return $row->type === $type && $this->dayValue($row->day) === $day;
                });
                $columns[$type][] = (int) ($match->total ?? 0);
            }
        }
        $columns['labels'] = $labels;

        return $columns;
    }

    private function dayValue(mixed $day): string
    {
        if ($day instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($day))->toDateString();
        }

        return substr((string) $day, 0, 10);
    }
}
