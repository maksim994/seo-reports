<?php

return [
    'base_url' => rtrim((string) env('KEYSSO_API_BASE_URL', 'https://api.keys.so'), '/'),
    'rate_limit_max' => (int) env('KEYSSO_RATE_LIMIT_MAX', 10),
    'rate_limit_window' => (int) env('KEYSSO_RATE_LIMIT_WINDOW', 10),
];
