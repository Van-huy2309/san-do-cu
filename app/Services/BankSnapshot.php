<?php

namespace App\Services;

use App\Models\Finance\BankAccount;

class BankSnapshot
{
    public function __construct(
        public int $user_id,
        public string $bank_name,
        public string $account_last4,
    ) {}

    public static function fromAccount(BankAccount $account): self
    {
        return new self((int) $account->user_id, (string) $account->bank_name, (string) $account->account_last4);
    }

    public static function fromArray(array $row): self
    {
        return new self((int) $row['user_id'], (string) $row['bank_name'], (string) $row['account_last4']);
    }

    public function masked(): string
    {
        return $this->bank_name.' · ****'.$this->account_last4;
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'bank_name' => $this->bank_name,
            'account_last4' => $this->account_last4,
        ];
    }
}
