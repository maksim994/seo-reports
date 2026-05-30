<?php

return [
    'storage_disk' => env('TECHNICAL_AUDIT_STORAGE_DISK', env('REPORTS_STORAGE_DISK', env('FILESYSTEM_DISK', 'local'))),

    'storage_path_prefix' => 'technical-audits',

    'cursor_api_key' => env('CURSOR_API_KEY'),

    'cursor_api_base_url' => env('CURSOR_API_BASE_URL', 'https://api.cursor.com'),

    'cursor_repo_url' => env('CURSOR_AUDIT_REPO_URL', 'https://github.com/maksim994/skills-seo-audit'),

    'cursor_repo_ref' => env('CURSOR_AUDIT_REPO_REF', 'main'),

    'cursor_model' => env('CURSOR_AUDIT_MODEL', 'composer-2.5'),

    /*
     * Public URL reachable from Cursor Cloud Agent VMs.
     * localhost will NOT work — use ngrok or your production domain.
     */
    'webhook_base_url' => env('TECHNICAL_AUDIT_WEBHOOK_BASE_URL', env('APP_URL')),

    'poll_interval_seconds' => (int) env('TECHNICAL_AUDIT_POLL_INTERVAL', 30),

    'poll_max_attempts' => (int) env('TECHNICAL_AUDIT_POLL_MAX_ATTEMPTS', 60),

    'docx_script' => base_path('scripts/technical-audit/generate-docx.py'),
];
