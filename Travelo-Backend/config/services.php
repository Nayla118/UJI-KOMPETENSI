<?php

return [

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

    // Firebase Configuration
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'database_url' => env('FIREBASE_DATABASE_URL'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
        'credentials_file' => env('FIREBASE_CREDENTIALS', base_path('firebase-credentials.json')),
    ],

    // Midtrans Configuration
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
    ],

];
