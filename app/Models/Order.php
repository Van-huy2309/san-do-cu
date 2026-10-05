<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'code', 'user_id', 'name', 'address', 'phone', 'total_price', 'voucher_id', 'discount_amount', 'status',
        'escrow_status', 'escrow_amount', 'received_at', 'released_at',
        'shipping_status', 'ghn_order_code', 'ghn_total_fee', 'to_district_id', 'to_ward_code',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
            'discount_amount' => 'integer',
            'ghn_total_fee' => 'integer',
            'escrow_amount' => 'integer',
            'received_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function canConfirmReceived(): bool
    {
        return $this->user_id === (int) auth()->id()
            && in_array($this->status, ['paid', 'cod_ordered', 'cod_paid'], true)
            && in_array($this->escrow_status, ['held', 'cod'], true)
            && ! $this->received_at;
    }

    public function canDispute(): bool
    {
        return $this->user_id === (int) auth()->id()
            && in_array($this->escrow_status, ['held', 'cod'], true)
            && ! in_array($this->status, ['cancelled', 'refunded', 'completed'], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function latestPayment(): ?PaymentTransaction
    {
        return $this->paymentTransactions()->latest()->first();
    }

    public function canPayAgain(): bool
    {
        return in_array($this->status, ['pending'], true)
            && ! $this->ghn_order_code
            && $this->latestPayment()?->gateway === 'momo'
            && $this->latestPayment()?->status !== 'paid';
    }

    public function escrowLabel(): string
    {
        return match ($this->escrow_status) {
            'held' => 'Sàn đang giữ tiền',
            'released' => 'Đã giải ngân người bán',
            'refunded' => 'Đã hoàn người mua',
            'disputed' => 'Đang khiếu nại',
            'cod' => 'COD — không giữ ví',
            default => 'Không áp dụng',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Chờ thanh toán',
            'paid', 'paid_momo' => 'Đã thanh toán — đang giữ escrow',
            'cod_ordered' => 'COD đã ghi nhận',
            'cod_paid' => 'COD đã thu tiền',
            'completed' => 'Hoàn tất',
            'refunded' => 'Đã hoàn tiền',
            'cancelled' => 'Đã hủy',
            default => $this->status,
        };
    }

    public function ghnIsSandbox(): bool
    {
        return str_contains((string) config('services.ghn.base_url'), 'dev-online-gateway');
    }

    public function ghnTrackingUrl(): ?string
    {
        if (! $this->ghn_order_code) {
            return null;
        }

        $host = $this->ghnIsSandbox()
            ? 'https://tracking.ghn.dev'
            : 'https://donhang.ghn.vn';

        return $host.'/?order_code='.urlencode($this->ghn_order_code);
    }

    public function ghnPortalUrl(): string
    {
        return $this->ghnIsSandbox()
            ? 'https://5sao.ghn.dev'
            : 'https://khachhang.ghn.vn';
    }
}
