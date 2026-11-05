<?php

/*
 * Registry de modulos oficiais e descoberta de addons.
 * Inspiracao: WHMCS (modules) + WordPress (plugins).
 *
 * Cada modulo eh um Service Provider em app/Modules/<Nome>/Providers/.
 * Addons de terceiros ficam em app/Addons/<slug>/ com addon.json + AddonServiceProvider.
 */

return [
    'official' => [
        'settings'    => true,
        'seo'         => true,
        'maintenance' => true,
        'pwa'         => true,
        'whatsapp'    => true,
        'templates'   => true,
        'billing'     => true,
        'crm'         => true,
        'gateways' => [
            'mercadopago' => true,
            'efipro'      => true,
            'sumup'       => true,
        ],
    ],

    'addons' => [
        'autoload' => true,
        'path'     => app_path('Addons'),
    ],

    'cron' => [
        // Heartbeat (segundos) - intervalo minimo entre verificacoes pos-resposta
        'heartbeat_seconds' => (int) env('CRON_HEARTBEAT_SECONDS', 60),
        'external_token'    => env('CRON_EXTERNAL_TOKEN', ''),
    ],

    'upload' => [
        'max_size_mb'   => (int) env('UPLOAD_MAX_SIZE_MB', 50),
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif', 'application/pdf', 'video/mp4'],
    ],

    'email_queue' => [
        'interval_minutes'    => (int) env('EMAIL_QUEUE_INTERVAL_MINUTES', 1),
        'batch_size'          => (int) env('EMAIL_QUEUE_BATCH_SIZE', 100),
        'max_attempts'        => (int) env('EMAIL_QUEUE_MAX_ATTEMPTS', 5),
        'per_tenant_per_hour' => (int) env('EMAIL_QUEUE_PER_TENANT_PER_HOUR', 120),
    ],
];
