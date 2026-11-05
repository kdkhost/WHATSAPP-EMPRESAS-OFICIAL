const CACHE_NAME = 'kdkhost-whatsapp-pwa-v8';
const RUNTIME_CACHE = 'kdkhost-whatsapp-runtime-v8';

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
    '/cron'
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

    // Nao intercepta requisicoes nao-GET de forma alguma
    if (request.method !== 'GET') {
        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    if (BYPASS_PATHS.some(function (path) {
        return url.pathname.startsWith(path);
    })) {
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
