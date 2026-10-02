<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL points at the XAMPP sub-folder; tests hit the app at the domain root.
        config([
            'app.url' => 'http://localhost',
            'services.momo.partner_code' => config('services.momo.partner_code') ?: 'MOMOBKUN20180529',
            'services.momo.access_key' => config('services.momo.access_key') ?: 'klm05TvNBzhg7h7j',
            'services.momo.secret_key' => config('services.momo.secret_key') ?: 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
            'services.momo.request_type' => config('services.momo.request_type') ?: 'payWithATM',
        ]);
        URL::forceRootUrl('http://localhost');
    }
}
