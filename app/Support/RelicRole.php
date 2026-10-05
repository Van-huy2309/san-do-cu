<?php

namespace App\Support;

class RelicRole
{
    public static function isFinance(): bool
    {
        $file = dirname(__DIR__, 2).'/storage/framework/relic-role';
        if (is_file($file)) {
            return trim((string) file_get_contents($file)) === 'finance';
        }

        return getenv('RELIC_ROLE') === 'finance';
    }
}
