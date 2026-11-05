<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'file'),

    'stores' => [
        'file' => [
            'driver'    => 'file',
            'path'      => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],
        'database' => [
            'driver'     => 'database',
            'connection' => env('DB_CONNECTION', 'mysql'),
            'table'      => 'cache',
            'lock_connection' => env('DB_CONNECTION', 'mysql'),
            'lock_table' => 'cache_locks',
        ],
        'redis' => [
            'driver'     => 'redis',
            'connection' => 'default',
            'lock_connection' => 'default',
        ],
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'cwa'), '_').'_cache_'),
];
