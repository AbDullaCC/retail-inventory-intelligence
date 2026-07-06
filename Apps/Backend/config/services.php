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

    // Shopify connector (custom-app token; GraphQL Admin API) — see app/Modules/Shopify.
    'shopify' => [
        'domain' => env('SHOPIFY_SHOP_DOMAIN'),
        'token' => env('SHOPIFY_ADMIN_TOKEN'),
        'version' => env('SHOPIFY_API_VERSION', '2026-01'),
        'timeout' => (int) env('SHOPIFY_TIMEOUT', 30),
        'history_days' => 730,
        'throttle_delay_ms' => 1000,
    ],

    // Python forecasting sidecar (Apps/Forecast) — see app/Modules/Forecast.
    'forecast' => [
        'url' => env('FORECAST_SERVICE_URL', 'http://127.0.0.1:8100'),
        'timeout' => (int) env('FORECAST_SERVICE_TIMEOUT', 120),
        'chunk_size' => 50,
        'horizon_days' => 28,
        'history_days' => 730,
        'stale_after_hours' => 48,
    ],

];
