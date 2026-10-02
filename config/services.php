<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'momo' => [
        'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
        'partner_code' => env('MOMO_PARTNER_CODE', 'MOMOBKUN20180529'),
        'access_key' => env('MOMO_ACCESS_KEY', 'klm05TvNBzhg7h7j'),
        'secret_key' => env('MOMO_SECRET_KEY', 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa'),
        'verify_ssl' => env('MOMO_VERIFY_SSL', true),
        'redirect_url' => env('MOMO_REDIRECT_URL'),
        'ipn_url' => env('MOMO_IPN_URL'),
        'partner_name' => env('MOMO_PARTNER_NAME', 'Relic'),
        'request_type' => env('MOMO_REQUEST_TYPE', 'payWithATM'),
    ],

    'ghn' => [
        'base_url' => env('GHN_BASE_URL'),
        'token' => env('GHN_TOKEN'),
        'shop_id' => env('GHN_SHOP_ID'),
        'verify_ssl' => env('GHN_VERIFY_SSL', true),
        'from_district_id' => env('GHN_FROM_DISTRICT_ID'),
        'from_ward_code' => env('GHN_FROM_WARD_CODE'),
        'from_name' => env('GHN_FROM_NAME', 'Relic'),
        'from_phone' => env('GHN_FROM_PHONE', '0367828305'),
        'from_address' => env('GHN_FROM_ADDRESS', '12 Nguyen Chi Thanh'),
        'default_weight' => env('GHN_DEFAULT_WEIGHT', 400),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/google/callback'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI', env('APP_URL') . '/auth/apple/callback'),
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'twilio_sid' => env('TWILIO_SID'),
        'twilio_token' => env('TWILIO_TOKEN'),
        'twilio_from' => env('TWILIO_FROM'),
        'esms_api_key' => env('ESMS_API_KEY'),
        'esms_secret' => env('ESMS_SECRET'),
    ],

    'elasticsearch' => [
        'host' => env('ELASTICSEARCH_HOST'),
        'user' => env('ELASTICSEARCH_USER'),
        'password' => env('ELASTICSEARCH_PASSWORD'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'base' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'fallback_model' => env('GROQ_FALLBACK_MODEL', 'openai/gpt-oss-20b'),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'base' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_MODEL', 'meta-llama/llama-3.3-70b-instruct:free'),
    ],

];
