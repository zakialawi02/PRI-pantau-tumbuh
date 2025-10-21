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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_CALLBACK_URL'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_CALLBACK_URL'),
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_CALLBACK_URL'),
    ],

    'copernicus' => [
        'client_id' => env('COPERNICUS_CLIENT_ID'),
        'client_secret' => env('COPERNICUS_CLIENT_SECRET'),
        'token_endpoint' => env('COPERNICUS_TOKEN_ENDPOINT', 'https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token'),
        'token_cache_seconds' => env('COPERNICUS_TOKEN_CACHE_SECONDS', 3300),
    ],

    'currency' => [
        'default_currency' => env('CURRENCY_DEFAULT_CURRENCY', 'IDR'),
        'fallback_currency' => env('CURRENCY_FALLBACK_CURRENCY', 'USD'),
        'fallback_country' => env('CURRENCY_FALLBACK_COUNTRY', 'US'),
        'country_currency_map' => [
            'ID' => 'IDR',
        ],
        'rate_api' => env('CURRENCY_RATE_API_URL', 'https://open.er-api.com/v6/latest/{base}'),
        'default_rates' => [
            'IDR' => [
                'USD' => (float) env('CURRENCY_RATE_IDR_TO_USD', 0.000063),
            ],
            'USD' => [
                'IDR' => (float) env('CURRENCY_RATE_USD_TO_IDR', 15800),
            ],
        ],
        'refresh_days' => (int) env('CURRENCY_RATE_REFRESH_DAYS', 7),
        'ip_cache_seconds' => (int) env('CURRENCY_IP_CACHE_SECONDS', 86400),
        'request_timeout' => (int) env('CURRENCY_RATE_TIMEOUT', 10),
        'request_retries' => (int) env('CURRENCY_RATE_RETRIES', 2),
        'ipinfo_token' => env('IPINFO_TOKEN', env('IPINFO_ACCESS_TOKEN')),
    ],

];
