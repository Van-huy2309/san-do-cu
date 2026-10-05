<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpBan extends Model
{
    public $timestamps = false;

    protected $fillable = ['ip', 'banned_until', 'source'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'banned_until' => 'datetime',
        ];
    }
}
