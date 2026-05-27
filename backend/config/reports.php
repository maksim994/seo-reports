<?php

return [
    'storage_disk' => env('REPORTS_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),

    'storage_path_prefix' => 'reports',
];
