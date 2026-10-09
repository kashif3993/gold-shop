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
        // How often the live rate is allowed to auto-refresh from the API.
        // Triggered opportunistically whenever the rate ticker is polled from
        // any open screen — so it self-refreshes during business hours even
        // without a working server cron.
        'auto_refresh_minutes' => (int) env('RATE_AUTO_REFRESH_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bank QR payments
    |--------------------------------------------------------------------------
    |
    | Manual bank-transfer payments at POS: the shop's own static account QR
    | (uploaded once, admin-only) is shown with a per-sale reference number
    | and amount. `expiry_minutes` is how long that reference stays
    | confirmable before it's permanently retired — see BankQrPayment.
    */
    'bank_qr' => [
        'expiry_minutes' => (int) env('BANK_QR_EXPIRY_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bank slip OCR
    |--------------------------------------------------------------------------
    |
    | Reads a photo of a bank transfer slip at the Bank QR confirm step and
    | tries to find the transaction ID on it — free, local, offline (no cloud
    | API, no cost). On Windows the `tesseract` binary isn't on PATH by
    | default; set TESSERACT_BINARY_PATH to its full exe path if the scan
    | fails with "tesseract is not recognized". Left blank, it's resolved
    | from PATH as normal (e.g. on the Ubuntu CI/production image).
    */
    'tesseract' => [
        'binary_path' => env('TESSERACT_BINARY_PATH'),
    ],

];
