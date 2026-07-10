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

    // AI chatbot (read-only Q&A agent) — see app/Modules/Chatbot.
    'chatbot' => [
        'provider' => env('LLM_PROVIDER', 'gemini'),
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            // gemini-3.1-flash-lite is the current default — gemini-3.5-flash is
            // often overloaded (HTTP 503 "high demand"), and gemini-2.5-flash is
            // deprecated. Model is env-configurable so rotation is a config change.
            'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => (int) env('GEMINI_TIMEOUT', 30),
        ],
        'max_tokens' => (int) env('CHATBOT_MAX_TOKENS', 2048),
        'temperature' => 0.2,
        'max_history_messages' => 12,
        'max_tool_iterations' => 5,
        'max_tool_result_items' => 20,
        'rate_limit_per_hour' => (int) env('CHATBOT_RATE_LIMIT_PER_HOUR', 30),
        'system_prompt' => env('CHATBOT_SYSTEM_PROMPT'),
    ],

];
