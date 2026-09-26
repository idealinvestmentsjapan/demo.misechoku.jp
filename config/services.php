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
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'line' => [
        'client_id' => env('LINE_CLIENT_ID'),
        'client_secret' => env('LINE_CLIENT_SECRET'),
        // LINE_REDIRECT_URI を明示した場合のみ true（コールバックURLは env の値をそのまま使う）
        'redirect_is_explicit' => (function () {
            $explicit = env('LINE_REDIRECT_URI');

            return $explicit !== null && trim((string) $explicit) !== '';
        })(),
        // LINE Login のコールバック。未設定時は APP_URL と一致させる（登録URLとズレると invalid redirect_uri になる）
        'redirect' => (function () {
            $explicit = env('LINE_REDIRECT_URI');
            if ($explicit !== null && trim((string) $explicit) !== '') {
                return rtrim(trim((string) $explicit), '/');
            }

            return rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/login/line/callback';
        })(),
        'bot_prompt' => 'normal',
        'message' => [
            'channel_id'=>env('LINE_MESSAGE_CHANNEL_ID'),
            'channel_secret'=>env('LINE_MESSAGE_CHANNEL_SECRET'),
            'channel_token'=>env('LINE_MESSAGE_CHANNEL_TOKEN')
        ]

    ],

    /*
    |--------------------------------------------------------------------------
    | Web Push (PWA 通知)
    |--------------------------------------------------------------------------
    | VAPID キーは php artisan push:vapid で生成し .env に設定してください。
    */
    'push' => [
        'vapid_public' => env('VAPID_PUBLIC_KEY'),
        'vapid_private' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@' . (parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost')),
    ],
];
