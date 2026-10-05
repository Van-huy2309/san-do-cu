<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $connection = 'finance';

    protected $fillable = [
        'user_id', 'bank_name', 'account_holder', 'account_number', 'account_last4',
    ];

    protected $hidden = [
        'account_holder', 'account_number',
    ];

    protected function casts(): array
    {
        return [
            'account_holder' => 'encrypted',
            'account_number' => 'encrypted',
        ];
    }

    public function masked(): string
    {
        return $this->bank_name.' · ****'.$this->account_last4;
    }
}
