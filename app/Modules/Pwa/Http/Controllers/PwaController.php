<?php

namespace App\Modules\Pwa\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class PwaController extends Controller
{
    private function tenantHomeUrl(): string
    {
        if (Route::has('tenant.dashboard')) {
            return route('tenant.dashboard');
        }
        if (Route::has('user.dashboard')) {
            return route('user.dashboard');
        }
        if (Route::has('client.dashboard')) {
            return route('client.dashboard');
        }
        if (Route::has('dashboard')) {
            return route('dashboard');
        }
        return url('/painel');
    }

    private function adminHomeUrl(): string
    {
        if (Route::has('admin.dashboard')) {
            return route('admin.dashboard');
        }
        return url('/admin');
    }

    private function loginUrl(): string
    {
        if (Route::has('login')) {
            return route('login');
        }
        return url('/login');
    }

    private function resolveRedirectUrl(): string
    {
        if (session()->has('admin_assist_mode') && auth()->check()) {
            return $this->tenantHomeUrl();
        }
        if (auth()->check()) {
            return $this->tenantHomeUrl();
        }
        if (auth('admin')->check()) {
            return $this->adminHomeUrl();
        }
        return $this->loginUrl();
    }

    public function splash(SettingsManager $settings): Response
    {
        return response()
            ->view('pwa.splash', [
                'appName' => $settings->get('pwa.name', $settings->get('app.name', config('app.name'))),
                'shortName' => $settings->get('pwa.short_name', 'Cobranca'),
                'icon192' => $settings->get('pwa.icon_192', '/uploads/pwa/icons/icon-192.png'),
                'targetUrl' => $this->resolveRedirectUrl(),
                'delayMs' => 1000,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function entry(): Response
    {
        return redirect()->to($this->resolveRedirectUrl());
    }

    public function dashboard(): Response
    {
        return redirect()->to($this->resolveRedirectUrl());
    }

    /** Manifest dinamico (gerado a partir das settings do admin). */
    public function manifest(SettingsManager $s): JsonResponse
    {
        $name      = $s->get('pwa.name', $s->get('app.name', config('app.name')));
        $shortName = $s->get('pwa.short_name', 'Cobranca');
        $themeColor = $s->get('pwa.theme_color', '#0d6efd');
        $bgColor    = $s->get('pwa.background_color', '#ffffff');
        $themeColorDark = $s->get('pwa.theme_color_dark', '#1a1d21');
        $bgColorDark    = $s->get('pwa.background_color_dark', '#1a1d21');
        $startUrl   = url('/pwa/splash');

        $iconMap = [
            ['key' => 'pwa.icon_72', 'size' => '72x72'],
            ['key' => 'pwa.icon_96', 'size' => '96x96'],
            ['key' => 'pwa.icon_128', 'size' => '128x128'],
            ['key' => 'pwa.icon_144', 'size' => '144x144'],
            ['key' => 'pwa.icon_152', 'size' => '152x152'],
            ['key' => 'pwa.icon_192', 'size' => '192x192'],
            ['key' => 'pwa.icon_384', 'size' => '384x384'],
            ['key' => 'pwa.icon_512', 'size' => '512x512'],
        ];

        $icons = [];
        foreach ($iconMap as $entry) {
            $src = $s->get($entry['key']);
            if (! $src) {
                continue;
            }
            $icons[] = ['src' => $src, 'sizes' => $entry['size'], 'type' => 'image/png'];
        }
        $maskable192 = $s->get('pwa.maskable_icon_192');
        $maskable512 = $s->get('pwa.maskable_icon_512');
        if ($maskable192) {
            $icons[] = ['src' => $maskable192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'];
        }
        if ($maskable512) {
            $icons[] = ['src' => $maskable512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
        }
        if ($icons === []) {
            $icons[] = ['src' => '/uploads/pwa/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'];
            $icons[] = ['src' => '/uploads/pwa/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'];
        }

        return response()->json([
            'name'             => $name,
            'short_name'       => $shortName,
            'description'      => $s->get('pwa.description', 'Sistema de cobranca via WhatsApp'),
            'start_url'        => $startUrl,
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => $bgColor,
            'theme_color'      => $themeColor,
            'lang'             => 'pt-BR',
            // Dark mode automatico (Chrome 93+)
            'theme_colors' => [
                ['color' => $themeColor,     'media' => '(prefers-color-scheme: light)'],
                ['color' => $themeColorDark, 'media' => '(prefers-color-scheme: dark)'],
            ],
            'background_colors' => [
                ['color' => $bgColor,     'media' => '(prefers-color-scheme: light)'],
                ['color' => $bgColorDark, 'media' => '(prefers-color-scheme: dark)'],
            ],
            'icons' => $icons,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function serviceWorker(): Response
    {
        $settings = app(SettingsManager::class);
        $notificationIcon = $settings->get('app.favicon', '/uploads/pwa/icon-192.png') ?: '/uploads/pwa/icon-192.png';
        $version = preg_replace('/[^a-zA-Z0-9_.-]/', '-', (string) ($settings->get('pwa.cache_version') ?? config('app.version') ?? '2.0.0')) . '-pwa-fix2';

        $sw = <<<'JS'
const CACHE_VERSION = '__CACHE_VERSION__';
const CACHE_NAME = 'kdkhost-whatsapp-pwa-' + CACHE_VERSION;
const RUNTIME_CACHE = 'kdkhost-whatsapp-runtime-' + CACHE_VERSION;

const BYPASS_PATHS = [
    '/login',
    '/logout',
    '/cadastro',
    '/esqueci-senha',
    '/redefinir-senha',
    '/admin',
    '/painel',
    '/pwa',
    '/api',
    '/sanctum',
    '/csrf-cookie',
    '/webhooks',
    '/cron',
    '/contrato',
    '/admin/tenancy/tenants',
    '/painel/voltar-admin',
    '/supervised-login',
    '/impersonation'
];

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys
                    .filter(function (key) {
                        return key.indexOf('kdkhost-whatsapp-pwa-') === 0 || key.indexOf('kdkhost-whatsapp-runtime-') === 0;
                    })
                    .filter(function (key) {
                        return ![CACHE_NAME, RUNTIME_CACHE].includes(key);
                    })
                    .map(function (key) {
                        return caches.delete(key);
                    })
            );
        })
    );

    self.clients.claim();
});

self.addEventListener('fetch', function (event) {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET') {
        event.respondWith(fetch(request));
        return;
    }

    if (url.origin !== self.location.origin) {
        event.respondWith(fetch(request));
        return;
    }

    if (BYPASS_PATHS.some(function (path) {
        return url.pathname.startsWith(path);
    })) {
        event.respondWith(fetch(request));
        return;
    }

    event.respondWith(
        caches.match(request).then(function (cached) {
            if (cached) {
                return cached;
            }

            return fetch(request).then(function (response) {
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }

                const cloned = response.clone();

                caches.open(RUNTIME_CACHE).then(function (cache) {
                    cache.put(request, cloned);
                });

                return response;
            });
        })
    );
});

self.addEventListener('push', function (event) {
    if (!event.data) return;
    const data = event.data.json();
    event.waitUntil(self.registration.showNotification(data.title || 'Notificacao', {
        body: data.body || '',
        icon: data.icon || '__NOTIFICATION_ICON__',
        badge: '/uploads/pwa/badge.png',
        data: { url: data.url || '/' },
    }));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url || '/'));
});
JS;

        $sw = str_replace('__CACHE_VERSION__', $version, $sw);
        $sw = str_replace('__NOTIFICATION_ICON__', addslashes($notificationIcon), $sw);

        return response($sw, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
