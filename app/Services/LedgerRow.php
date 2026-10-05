<?php

namespace App\Services;

use App\Models\Finance\LedgerEntry;
use Illuminate\Support\Carbon;

class LedgerRow
{
    public function __construct(
        public int $order_id,
        public int $seller_id,
        public string $type,
        public int $amount,
        public ?Carbon $occurred_at,
    ) {}

    public static function fromEntry(LedgerEntry $entry): self
    {
        return new self(
            (int) $entry->order_id,
            (int) $entry->seller_id,
            (string) $entry->type,
            (int) $entry->amount,
            $entry->occurred_at ? Carbon::parse($entry->occurred_at) : null,
        );
    }

    public static function fromArray(array $row): self
    {
        $at = $row['occurred_at'] ?? null;

        return new self(
            (int) $row['order_id'],
            (int) ($row['seller_id'] ?? 0),
            (string) $row['type'],
            (int) $row['amount'],
            $at ? Carbon::parse($at) : null,
        );
    }

    public function typeLabel(): string
    {
        return LedgerEntry::labelFor($this->type);
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->order_id,
            'seller_id' => $this->seller_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'occurred_at' => $this->occurred_at?->format('Y-m-d H:i:s'),
        ];
    }
}
