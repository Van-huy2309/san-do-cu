<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    public const REASONS = [
        'not_as_described' => 'Không đúng mô tả',
        'damaged' => 'Hàng hỏng / thiếu phụ kiện',
        'wrong_item' => 'Gửi nhầm máy',
        'other' => 'Khác',
    ];

    protected $fillable = [
        'order_id', 'user_id', 'reason', 'detail', 'evidence_path',
        'status', 'resolution', 'admin_note', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => 'Đang xử lý',
            'resolved' => 'Đã xử lý',
            default => $this->status,
        };
    }
}
