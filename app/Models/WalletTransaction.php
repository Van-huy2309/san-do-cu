<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id', 'order_id', 'type', 'amount', 'status', 'note',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'topup' => 'Nạp ví',
            'withdraw' => 'Rút ví',
            'payout' => 'Nhận tiền escrow',
            'commission' => 'Phí sàn',
            'boost' => 'Đẩy tin',
            'refund' => 'Hoàn tiền',
            default => $this->type,
        };
    }
}
