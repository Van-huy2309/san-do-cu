<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    public const BUYER_IN = 'buyer_in';

    public const PLATFORM_FEE = 'platform_fee';

    public const SELLER_PAYOUT = 'seller_payout';

    public const BUYER_REFUND = 'buyer_refund';

    protected $connection = 'finance';

    protected $fillable = [
        'order_id', 'seller_id', 'type', 'amount', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public static function labelFor(string $type): string
    {
        return match ($type) {
            self::BUYER_IN => 'Tiền người mua vào tài khoản admin',
            self::PLATFORM_FEE => 'Phí sàn giữ lại',
            self::SELLER_PAYOUT => 'Chuyển về tài khoản shop',
            self::BUYER_REFUND => 'Hoàn người mua',
            default => $type,
        };
    }

    public function typeLabel(): string
    {
        return self::labelFor($this->type);
    }
}
