<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;

class EscrowService
{
    public function __construct(
        private WalletService $wallet,
        private FinanceBook $finance,
    ) {}

    public function holdPaidOrder(Order $order): void
    {
        $amount = max(0, (int) $order->items->sum(fn ($item) => $item->price * $item->quantity) - (int) $order->discount_amount);
        $order->update([
            'escrow_status' => 'held',
            'escrow_amount' => $amount,
        ]);
        $this->finance->receiveBuyer($order->fresh('items'));
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
        if (! in_array($order->escrow_status, ['held', 'disputed', 'cod'], true)) {
            $order->update([
                'status' => 'completed',
                'received_at' => $order->received_at ?? now(),
            ]);

            return;
        }

        $order->loadMissing(['items', 'voucher']);
        $bySeller = $order->items->groupBy('seller_id');
        $discount = (int) $order->discount_amount;
        $subtotal = (int) $order->items->sum(fn ($item) => $item->price * $item->quantity);
        $allocated = 0;
        $lastSellerId = $bySeller->keys()->last();
        $settlements = [];
        foreach ($bySeller as $sellerId => $items) {
            $gross = (int) $items->sum(fn ($item) => $item->price * $item->quantity);
            $share = 0;
            if ($discount > 0 && $subtotal > 0) {
                $voucher = $order->voucher;
                if (! $voucher || $voucher->seller_id === null) {
                    if ((int) $sellerId === (int) $lastSellerId) {
                        $share = max(0, $discount - $allocated);
                    } else {
                        $share = (int) floor($gross * $discount / $subtotal);
                        $allocated += $share;
                    }
                } elseif ((int) $voucher->seller_id === (int) $sellerId) {
                    $share = min($discount, $gross);
                }
            }
            $gross = max(0, $gross - $share);
            $fee = (int) round($gross * WalletService::COMMISSION_RATE);
            $net = max(0, $gross - $fee);
            $seller = User::find($sellerId);
            if ($seller) {
                if ($net > 0) {
                    $this->wallet->credit($seller, $net, 'payout', $note, $order);
                }
                if ($fee > 0) {
                    $this->wallet->recordCommission($seller, $fee, $order);
                }
            }
            $settlements[] = ['seller_id' => (int) $sellerId, 'fee' => $fee, 'net' => $net];
        }

        $this->finance->settle($order, $settlements);

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
        $this->finance->refundBuyer($order);
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
