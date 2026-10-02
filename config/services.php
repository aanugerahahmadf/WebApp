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

    'ai_core_url' => env('AI_CORE_URL', 'http://127.0.0.1:5000'),
    'ai_core_timeout' => env('AI_CORE_TIMEOUT', 15),
    'cbir_min_similarity' => env('CBIR_MIN_SIMILARITY', 30.0),
    'cbir_api_url' => env('CBIR_API_URL', 'http://127.0.0.1:5000'),

    // WhatsApp via Fonnte API — https://fonnte.com
    'fonnte_token' => env('FONNTE_TOKEN', ''),

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    // Google Sheets untuk cadangan data review.
    // Sheet hanya menampung teks -- file foto tidak bisa diunggah ke sini,
    // yang dicatat hanyalah path dan URL publiknya.
    'google_sheets' => [
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID', ''),
        'service_account_json' => env('GOOGLE_SHEETS_SERVICE_ACCOUNT_JSON', ''),
        'tab' => env('GOOGLE_SHEETS_TAB', 'ReviewsBackup'),
    ],

    'geonames' => [
        'username' => env('GEONAMES_USERNAME'),
    ],

    'clerk_sync_secret' => env('CLERK_SYNC_SECRET', ''),

    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY', ''),
    ],
];
