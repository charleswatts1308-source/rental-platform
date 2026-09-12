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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.eu.mailgun.net'),
        'scheme' => 'https',
        'webhook_signing_key' => env('MAILGUN_WEBHOOK_SIGNING_KEY'),
        // Required per-environment, deliberately no default: CaseNotice throws if
        // either is missing, so a misconfigured env fails loudly instead of sending
        // production-shaped identity through the wrong domain.
        'cases_from_address' => env('MAILGUN_CASES_FROM_ADDRESS'),
        'inbound_domain' => env('MAILGUN_INBOUND_DOMAIN'),
    ],

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


    /*
    |--------------------------------------------------------------------------
    | Postcode lookup (#51)
    |--------------------------------------------------------------------------
    |
    | postcodes.io. Free, UK-wide, no API key, no registration, open data.
    | Defaults are PRODUCTION-SAFE: enabled, short timeouts, long cache.
    |
    | `enabled` is a kill switch, not a feature flag. Turning it off falls
    | straight back to the shape regex — the same behaviour the form had
    | before #51 — because the lookup must never be the reason a tenant
    | cannot register a property.
    |
    */

    'postcodes' => [
        'enabled' => env('POSTCODE_LOOKUP_ENABLED', true),
        'base_url' => env('POSTCODE_LOOKUP_BASE_URL', 'https://api.postcodes.io'),
        'timeout' => env('POSTCODE_LOOKUP_TIMEOUT', 3),
        'connect_timeout' => env('POSTCODE_LOOKUP_CONNECT_TIMEOUT', 2),
        'cache_days' => env('POSTCODE_LOOKUP_CACHE_DAYS', 30),
    ],
];
