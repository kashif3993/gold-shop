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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gold / silver spot-rate feed
    |--------------------------------------------------------------------------
    | api.gold-api.com returns USD per troy ounce with no API key. We convert
    | to PKR per gram (24K / fine) using `usd_pkr`, then the shop's configured
    | adjustment is applied per purity. `stale_after_hours` decides when a
    | still-current rate is flagged as possibly outdated.
    */
    'gold_api' => [
        'base_url' => env('GOLD_API_URL', 'https://api.gold-api.com'),
        // Fallback USD→PKR when the `usd_pkr` setting is unset. The live value is
        // managed on the Rate Management screen (manual or auto-fetched).
        'usd_pkr' => (float) env('GOLD_API_USD_PKR', 278.0),
        'fx_url' => env('FX_RATE_URL', 'https://open.er-api.com/v6/latest/USD'),
        'stale_after_hours' => (int) env('RATE_STALE_HOURS', 12),
        'timeout' => (int) env('GOLD_API_TIMEOUT', 8),
    ],

];
