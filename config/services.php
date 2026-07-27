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

    /*
    |--------------------------------------------------------------------------
    | Masuk lewat penyedia pihak ketiga
    |--------------------------------------------------------------------------
    |
    | Tombol penyedia hanya muncul di layar kalau client_id dan client_secret-nya
    | benar-benar terisi (lihat SocialProvider::isConfigured()). Jadi mengosongkan
    | kredensial sudah cukup untuk mematikan sebuah penyedia — tidak perlu
    | mengubah kode maupun tampilan.
    |
    | Redirect URI yang harus didaftarkan di konsol masing-masing penyedia:
    |   {APP_URL}/auth/google/callback
    |   {APP_URL}/auth/apple/callback
    |   {APP_URL}/auth/facebook/callback
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/auth/google/callback'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/auth/apple/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/auth/facebook/callback'),
    ],

];
