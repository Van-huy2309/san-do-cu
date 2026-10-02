<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;

class EscrowService
{
    public function __construct(private WalletService $wallet) {}

    public function holdPaidOrder(Order $order): void
    {
        $amount = (int) $order->items->sum(fn ($item) => $item->price * $item->quantity);
        $order->update([
            'escrow_status' => 'held',
            'escrow_amount' => $amount,
        ]);
    }

    public function markCod(Order $order): void
    {
        $order->update([
            'escrow_status' => 'cod',
            'escrow_amount' => 0,
        ]);
    }

    public function releaseToSellers(Order $order, string $note = 'Người mua xác nhận đã nhận đúng mô tả'): void
    {
        if (! in_array($order->escrow_status, ['held', 'disputed'], true)) {
            $order->update([
                'status' => 'completed',
                'received_at' => $order->received_at ?? now(),
            ]);

            return;
        }

        $order->loadMissing('items');
        $bySeller = $order->items->groupBy('seller_id');
        foreach ($bySeller as $sellerId => $items) {
            $gross = (int) $items->sum(fn ($item) => $item->price * $item->quantity);
            $fee = (int) round($gross * WalletService::COMMISSION_RATE);
            $net = max(0, $gross - $fee);
            $seller = User::find($sellerId);
            if (! $seller) {
                continue;
            }
            if ($net > 0) {
                $this->wallet->credit($seller, $net, 'payout', $note, $order);
            }
            if ($fee > 0) {
                $this->wallet->recordCommission($seller, $fee, $order);
            }
        }

        $order->update([
            'escrow_status' => 'released',
            'status' => 'completed',
            'received_at' => $order->received_at ?? now(),
            'released_at' => now(),
        ]);
    }

    public function refundBuyer(Order $order, string $note = 'Hoàn tiền khiếu nại'): void
    {
        if ($order->escrow_status !== 'held' && $order->escrow_status !== 'disputed') {
            throw new \RuntimeException('Đơn này không còn tiền escrow để hoàn.');
        }

        $buyer = $order->user;
        $paid = (int) ($order->paymentTransactions()->where('status', 'paid')->latest('id')->value('amount') ?? 0);
        $amount = max((int) $order->escrow_amount, $paid);
        if ($amount > 0) {
            $this->wallet->credit($buyer, $amount, 'refund', $note, $order);
        }

        $order->update([
            'escrow_status' => 'refunded',
            'status' => 'refunded',
            'released_at' => now(),
        ]);
    }

    public function openDispute(Order $order, User $user, array $data): Dispute
    {
        if (! in_array($order->escrow_status, ['held', 'cod'], true) || $order->status === 'cancelled') {
            throw new \RuntimeException('Đơn này không thể khiếu nại.');
        }

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'reason' => $data['reason'],
            'detail' => $data['detail'] ?? null,
            'evidence_path' => $data['evidence_path'] ?? null,
            'status' => 'open',
        ]);

        $order->update(['escrow_status' => 'disputed']);

        return $dispute;
    }
}
