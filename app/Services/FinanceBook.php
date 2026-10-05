<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class FinanceBook
{
    public function __construct(
        private FinanceStore $store,
        private FinanceClient $client,
    ) {}

    public function hasBank(int $userId): bool
    {
        if ($this->client->enabled()) {
            return (bool) $this->client->post('/internal/finance/bank/has', ['user_id' => $userId])['exists'];
        }

        return $this->store->hasBank($userId);
    }

    public function bankFor(int $userId): ?BankSnapshot
    {
        if ($this->client->enabled()) {
            $account = $this->client->post('/internal/finance/bank/show', ['user_id' => $userId])['account'] ?? null;

            return is_array($account) ? BankSnapshot::fromArray($account) : null;
        }

        return $this->store->bankFor($userId);
    }

    public function saveBank(int $userId, string $bankName, string $holder, string $number): BankSnapshot
    {
        if ($this->client->enabled()) {
            $account = $this->client->post('/internal/finance/bank/save', [
                'user_id' => $userId,
                'bank_name' => $bankName,
                'account_holder' => $holder,
                'account_number' => $number,
            ])['account'];

            return BankSnapshot::fromArray($account);
        }

        return $this->store->saveBank($userId, $bankName, $holder, $number);
    }

    public function receiveBuyer(Order $order): void
    {
        $this->send('receive', [
            'order_id' => (int) $order->id,
            'amount' => $this->merchandise($order),
        ], fn () => $this->store->receive((int) $order->id, $this->merchandise($order)));
    }

    public function refundBuyer(Order $order): void
    {
        $amount = (int) $order->escrow_amount;
        if ($amount < 1) {
            $amount = $this->merchandise($order);
        }
        $this->send('refund', [
            'order_id' => (int) $order->id,
            'amount' => $amount,
        ], fn () => $this->store->refund((int) $order->id, $amount));
    }

    /**
     * @param  iterable<int, array{seller_id: int, fee: int, net: int}>  $rows
     */
    public function settle(Order $order, iterable $rows): void
    {
        $payloadRows = [];
        foreach ($rows as $row) {
            $payloadRows[] = [
                'seller_id' => (int) $row['seller_id'],
                'fee' => (int) $row['fee'],
                'net' => (int) $row['net'],
            ];
        }
        $merchandise = $this->merchandise($order);
        $this->send('settle', [
            'order_id' => (int) $order->id,
            'merchandise' => $merchandise,
            'rows' => $payloadRows,
        ], fn () => $this->store->settle((int) $order->id, $merchandise, $payloadRows));
    }

    /**
     * @param  list<int>  $orderIds
     * @return array{income: int, expense: int, series: array, entries: list<LedgerRow>, account: ?BankSnapshot}
     */
    public function sellerReport(int $sellerId, array $orderIds, int $days = 14): array
    {
        if ($this->client->enabled()) {
            $data = $this->client->post('/internal/finance/ledger/seller', [
                'seller_id' => $sellerId,
                'order_ids' => array_values(array_map('intval', $orderIds)),
                'days' => $days,
            ]);

            return $this->hydrateSeller($data);
        }

        return $this->store->sellerReport($sellerId, $orderIds, $days);
    }

    /**
     * @return array{totals: array, series: array, entries: list<LedgerRow>, accounts: list<BankSnapshot>}
     */
    public function adminReport(int $days = 14, int $limit = 40): array
    {
        if ($this->client->enabled()) {
            return $this->hydrateAdmin($this->client->post('/internal/finance/ledger/admin', [
                'days' => $days,
                'limit' => $limit,
            ]));
        }

        return $this->store->adminReport($days, $limit);
    }

    public function merchandise(Order $order): int
    {
        $order->loadMissing('items');

        return max(0, (int) $order->items->sum(fn ($item) => $item->price * $item->quantity) - (int) $order->discount_amount);
    }

    private function send(string $action, array $payload, \Closure $local): void
    {
        try {
            if ($this->client->enabled()) {
                $this->client->post('/internal/finance/ledger/'.$action, $payload);

                return;
            }
            $local();
        } catch (\Throwable $e) {
            Log::error('Finance ledger write failed', [
                'action' => $action,
                'order_id' => $payload['order_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function hydrateSeller(array $data): array
    {
        $account = $data['account'] ?? null;

        return [
            'income' => (int) ($data['income'] ?? 0),
            'expense' => (int) ($data['expense'] ?? 0),
            'series' => $data['series'] ?? ['labels' => [], 'income' => [], 'expense' => []],
            'entries' => array_map(fn (array $row) => LedgerRow::fromArray($row), $data['entries'] ?? []),
            'account' => is_array($account) ? BankSnapshot::fromArray($account) : null,
        ];
    }

    private function hydrateAdmin(array $data): array
    {
        return [
            'totals' => $data['totals'] ?? ['buyer' => 0, 'fee' => 0, 'payout' => 0, 'refund' => 0, 'holding' => 0],
            'series' => $data['series'] ?? ['labels' => [], 'buyer' => [], 'fee' => [], 'payout' => []],
            'entries' => array_map(fn (array $row) => LedgerRow::fromArray($row), $data['entries'] ?? []),
            'accounts' => array_map(fn (array $row) => BankSnapshot::fromArray($row), $data['accounts'] ?? []),
        ];
    }
}
