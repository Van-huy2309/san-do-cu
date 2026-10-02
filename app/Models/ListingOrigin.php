<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingOrigin extends Model
{
    protected $fillable = [
        'listing_id', 'serial_hash', 'serial_last4', 'imei_last4',
        'purchase_channel', 'purchase_date', 'invoice_path', 'box_photo_path',
        'seal_code', 'status', 'admin_note', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'verified' => 'Đã xác thực nguồn gốc',
            'rejected' => 'Từ chối hồ sơ',
            default => 'Chờ xác thực',
        };
    }
}
