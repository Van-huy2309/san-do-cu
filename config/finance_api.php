<?php

return [
    'url' => env('FINANCE_API_URL', ''),
    'key_id' => env('FINANCE_API_KEY_ID', ''),
    'secret' => env('FINANCE_API_SECRET', ''),
    'secret_previous' => env('FINANCE_API_SECRET_PREVIOUS', ''),
    'skew' => (int) env('FINANCE_API_SKEW', 60),
];
