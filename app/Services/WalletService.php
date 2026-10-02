<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public const COMMISSION_RATE = 0.05;

    public const BOOST_FEE = 50000;

    public function credit(User $user, int $amount, string $type, ?string $note = null, ?Order $order = null, string $status = 'completed'): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $note, $order, $status) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($status === 'completed') {
                $locked->increment('wallet_balance', $amount);
            }

            return WalletTransaction::create([
                'user_id' => $locked->id,
                'order_id' => $order?->id,
                'type' => $type,
                'amount' => $amount,
                'status' => $status,
                'note' => $note,
            ]);
        });
    }

    public function debit(User $user, int $amount, string $type, ?string $note = null, ?Order $order = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $note, $order) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->wallet_balance < $amount) {
                throw new \RuntimeException('Số dư ví không đủ.');
            }
            $locked->decrement('wallet_balance', $amount);

            return WalletTransaction::create([
                'user_id' => $locked->id,
                'order_id' => $order?->id,
                'type' => $type,
                'amount' => $amount,
                'status' => 'completed',
                'note' => $note,
            ]);
        });
    }

    public function approveTopup(WalletTransaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            $tx = WalletTransaction::whereKey($tx->id)->lockForUpdate()->firstOrFail();
            if ($tx->status !== 'pending' || $tx->type !== 'topup') {
                throw new \RuntimeException('Giao dịch không thể duyệt.');
            }
            User::whereKey($tx->user_id)->lockForUpdate()->firstOrFail()->increment('wallet_balance', $tx->amount);
            $tx->update(['status' => 'completed']);
        });
    }

    public function approveWithdraw(WalletTransaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            $tx = WalletTransaction::whereKey($tx->id)->lockForUpdate()->firstOrFail();
            if ($tx->status !== 'pending' || $tx->type !== 'withdraw') {
                throw new \RuntimeException('Giao dịch không thể duyệt.');
            }
            $user = User::whereKey($tx->user_id)->lockForUpdate()->firstOrFail();
            if ((int) $user->wallet_frozen < $tx->amount) {
                throw new \RuntimeException('Số tiền đóng băng không khớp.');
            }
            $user->decrement('wallet_frozen', $tx->amount);
            $tx->update(['status' => 'completed']);
        });
    }

    public function requestWithdraw(User $user, int $amount, string $note): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $note) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->wallet_balance < $amount) {
                throw new \RuntimeException('Số dư ví không đủ để rút.');
            }
            $locked->decrement('wallet_balance', $amount);
            $locked->increment('wallet_frozen', $amount);

            return WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => 'withdraw',
                'amount' => $amount,
                'status' => 'pending',
                'note' => $note,
            ]);
        });
    }

    public function recordCommission(User $seller, int $amount, Order $order): WalletTransaction
    {
        $percent = (int) round(self::COMMISSION_RATE * 100);

        return WalletTransaction::create([
            'user_id' => $seller->id,
            'order_id' => $order->id,
            'type' => 'commission',
            'amount' => $amount,
            'status' => 'completed',
            'note' => 'Phí sàn '.$percent.'% đơn #'.$order->id,
        ]);
    }
}
