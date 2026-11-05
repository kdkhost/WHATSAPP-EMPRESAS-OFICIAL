<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $pwaThemeColor ?? '#0f172a' }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $pwaShortName ?? config('app.name') }}">
<link rel="manifest" href="{{ url('/manifest.json') }}">
<link rel="icon" href="{{ $pwaIcon192 ?? '/uploads/pwa/icons/icon-192.png' }}">
<link rel="apple-touch-icon" href="{{ $pwaIcon192 ?? '/uploads/pwa/icons/icon-192.png' }}">
@stack('head')
</head>
<body class="pwa-mobile">
@yield('content')

@stack('scripts')
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js?v={{ file_exists(public_path('sw.js')) ? filemtime(public_path('sw.js')) : time() }}')
            .catch(function (error) {
                console.warn('Service Worker nao registrado:', error);
            });
    });
}
</script>
</body>
</html>

