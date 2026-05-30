<?php

return [
    'storage_disk' => env('REPORTS_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),

    'storage_path_prefix' => 'reports',

    'api_cache_enabled' => env('REPORT_API_CACHE_ENABLED', true),

    'api_cache_ttl' => (int) env('REPORT_API_CACHE_TTL', 3600),

    'template_logo_disk' => env('TEMPLATE_LOGO_DISK', 'local'),

    'apexcharts_version' => '4.4.0',

    'apexcharts_legacy_cdn_urls' => [
        'https://cdn.jsdelivr.net/npm/apexcharts@4.4.0',
        'https://cdn.jsdelivr.net/npm/apexcharts@4.4.0/dist/apexcharts.min.js',
    ],
];
