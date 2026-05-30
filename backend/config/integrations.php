<?php

return [
    'oauth_state_ttl' => 600,

    'providers' => [
        'yandex_metrika' => [
            'client_id' => env('YANDEX_OAUTH_CLIENT_ID'),
            'client_secret' => env('YANDEX_OAUTH_CLIENT_SECRET'),
            'redirect_uri' => env('APP_URL').'/api/integrations/yandex_metrika/callback',
            'scopes' => ['metrika:read'],
        ],
        'yandex_webmaster' => [
            'client_id' => env('YANDEX_OAUTH_CLIENT_ID'),
            'client_secret' => env('YANDEX_OAUTH_CLIENT_SECRET'),
            'redirect_uri' => env('APP_URL').'/api/integrations/yandex_webmaster/callback',
            'scopes' => ['webmaster:verify', 'webmaster:hostinfo'],
        ],
        'yandex_wordstat' => [
            'client_id' => env('YANDEX_OAUTH_CLIENT_ID'),
            'client_secret' => env('YANDEX_OAUTH_CLIENT_SECRET'),
            'redirect_uri' => env('APP_URL').'/api/integrations/yandex_wordstat/callback',
            'scopes' => ['wordstat:api'],
        ],
        'google_analytics' => [
            'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
            'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
            'redirect_uri' => env('APP_URL').'/api/integrations/google_analytics/callback',
            'scopes' => [
                'https://www.googleapis.com/auth/analytics.readonly',
            ],
        ],
        'google_search_console' => [
            'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
            'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
            'redirect_uri' => env('APP_URL').'/api/integrations/google_search_console/callback',
            'scopes' => [
                'https://www.googleapis.com/auth/webmasters.readonly',
            ],
        ],
    ],

    'logos' => [
        // Logo URLs are resolved in the frontend (Vite assets). Do not use /integrations/*
        // as a static path — it conflicts with the SPA route /integrations.
        'yandex_metrika' => null,
        'yandex_webmaster' => null,
        'yandex_wordstat' => null,
        'topvisor' => env('TOPVISOR_LOGO_URL'),
        'keys_so' => env('KEYSSO_LOGO_URL'),
    ],
];
