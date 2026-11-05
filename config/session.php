<?php

use Illuminate\Support\Str;

return [
    'driver'          => env('SESSION_DRIVER', 'database'),
    'lifetime'        => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt'         => true,  // Encripta dados da session
    'files'           => storage_path('framework/sessions'),
    'connection'      => env('SESSION_CONNECTION'),
    'table'           => 'sessions',
    'store'           => env('SESSION_STORE'),
    'lottery'         => [2, 100],
    'cookie'          => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'cwa'), '_') . '_session'),
    'path'            => '/',
    'domain'          => env('SESSION_DOMAIN'),
    'secure'          => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production'), // true em produção com HTTPS
    'http_only'       => true,   // Impede acesso via JS (XSS protection)
    'same_site'       => 'lax',  // Protege contra CSRF cross-site
    'partitioned'     => false,
];
