<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
    $appName = $settingsManager->get('app.name', config('app.name'));
    $appLogo = $settingsManager->get('app.logo');
    $appFavicon = $settingsManager->get('app.favicon');
    $normalizeTrackingId = function (?string $value, string $prefix): ?string {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $normalized = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $value) ?? $value);
        $prefix = strtoupper($prefix);
        return str_starts_with($normalized, $prefix) ? $normalized : $prefix.ltrim($normalized, '-');
    };
    $gaId = $normalizeTrackingId($settingsManager->get('google.ga_id') ?: $settingsManager->get('seo.ga_id'), 'G-');
    $gadsId = $normalizeTrackingId($settingsManager->get('google.gads_id') ?: $settingsManager->get('seo.gads_id'), 'AW-');
    $gtmId = $normalizeTrackingId($settingsManager->get('google.gtm_id') ?: $settingsManager->get('seo.gtm_id'), 'GTM-');
    $hasGtag = filled($gaId) || filled($gadsId);
@endphp
<title>{{ $appName }}</title>
@if($appFavicon)
<link rel="icon" href="{{ $appFavicon }}">
<link rel="shortcut icon" href="{{ $appFavicon }}">
<link rel="apple-touch-icon" href="{{ $appFavicon }}">
@endif
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0d6efd">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">

{{-- SEO basico - module Seo enriquece via @push('seo') --}}
<meta name="description" content="@setting('seo.description')">
@stack('seo')

@if($gtmId)
<!-- Google Tag Manager -->
<script>
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{{ $gtmId }}');
</script>
<!-- End Google Tag Manager -->
@endif

@if($hasGtag)
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId ?: $gadsId }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
@if($gaId)
gtag('config', '{{ $gaId }}');
@endif
@if($gadsId)
gtag('config', '{{ $gadsId }}');
@endif
</script>
<!-- End Google tag (gtag.js) -->
@endif

{{-- Build do React/Tailwind 4 (frontend/dist) --}}
@php
    $manifest = public_path('build/.vite/manifest.json');
    if (!is_file($manifest)) {
        $manifest = public_path('build/manifest.json'); // Fallback older Vite
    }
    $assets = is_file($manifest) ? json_decode(file_get_contents($manifest), true) : null;
    $hasReactBuild = $assets && isset($assets['src/main.tsx']);
@endphp
@if($hasReactBuild)
    <link rel="stylesheet" href="/build/{{ $assets['src/main.tsx']['css'][0] ?? '' }}">
    <script type="module" src="/build/{{ $assets['src/main.tsx']['file'] }}"></script>
@else
    {{-- Fallback SSR caso o build do React nao exista ainda --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
@endif
</head>
<body>
@if($gtmId)
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
@endif

@if($hasReactBuild)
    {{-- Preload Premium Personalizável --}}
    <x-preload />
    <div id="root"></div>
@else
    {{-- Landing fallback (quando o React nao foi compilado) --}}
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/">
                @if($appLogo)
                    <img src="{{ $appLogo }}" alt="{{ $appName }}" style="max-height:34px;max-width:180px;object-fit:contain;">
                @else
                    <i class="bi bi-whatsapp"></i>
                @endif
                <span>{{ $appName }}</span>
            </a>
            <div class="d-flex gap-2">
                <a href="/admin/login" class="btn btn-light btn-sm">
                    <i class="bi bi-box-arrow-in-right"></i> Entrar
                </a>
            </div>
        </div>
    </nav>

    <header class="py-5 text-center text-white" style="background: linear-gradient(135deg, #0d6efd, #6610f2); min-height: 60vh; display:flex; align-items:center;">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">{{ $appName }}</h1>
            <p class="lead mb-4">Sistema de cobranca automatizada via WhatsApp</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="/admin/login" class="btn btn-light btn-lg">
                    <i class="bi bi-speedometer2"></i> Painel administrativo
                </a>
                @isset($slides)
                    @if($slides->isEmpty())
                        <a href="/install" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-gear"></i> Instalar / Configurar
                        </a>
                    @endif
                @endisset
            </div>
        </div>
    </header>

    @isset($slides)
        @if($slides->isNotEmpty())
            <section class="py-5">
                <div class="container">
                    <div class="row g-4">
                        @foreach($slides as $slide)
                            <div class="col-md-4">
                                <div class="card h-100 shadow-sm">
                                    @if(!empty($slide->image))
                                        <img src="{{ $slide->image }}" class="card-img-top" alt="{{ $slide->title ?? '' }}">
                                    @endif
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $slide->title ?? '' }}</h5>
                                        <p class="card-text text-muted">{{ $slide->subtitle ?? '' }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endisset

    @isset($testimonials)
        @if($testimonials->isNotEmpty())
            <section class="py-5 bg-body-tertiary">
                <div class="container">
                    <h2 class="text-center mb-4">Depoimentos</h2>
                    <div class="row g-4">
                        @foreach($testimonials as $t)
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <p class="mb-3 fst-italic">{{ $t->message ?? '' }}</p>
                                        <div class="d-flex align-items-center gap-2">
                                            @if(!empty($t->avatar))
                                                <img src="{{ $t->avatar }}" class="rounded-circle" width="40" height="40" alt="">
                                            @endif
                                            <div>
                                                <strong class="d-block">{{ $t->name ?? '' }}</strong>
                                                <small class="text-muted">{{ $t->role ?? '' }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endisset

    <footer class="py-4 bg-dark text-white-50 text-center small">
        <div class="container">
            <p class="m-0">&copy; {{ date('Y') }} {{ $appName }}. Todos os direitos reservados.</p>
            <p class="mt-1 mb-0">
                <a href="/privacidade" class="text-white-50">Política de Privacidade</a> ·
                <a href="/termos" class="text-white-50">Termos de Uso</a>
            </p>
        </div>
    </footer>

    {{-- Voltar ao topo SSR --}}
    <button id="back-to-top" class="back-to-top position-fixed" title="Voltar ao topo" aria-label="Voltar ao topo"
            style="bottom:1.5rem;right:1.5rem;z-index:1040;display:none;width:3rem;height:3rem;border-radius:50%;border:none;background:#0d6efd;color:#fff;">
        <i class="bi bi-arrow-up"></i>
    </button>
@endif

{{-- Fallback noscript (engines/screen readers) --}}
<noscript>
    <div class="container py-5 text-center">
        <h1>{{ $appName }}</h1>
        <p>Este site funciona melhor com JavaScript ativado.</p>
        <a href="/admin/login" class="btn btn-primary">Acessar painel</a>
    </div>
</noscript>

{{-- Banner LGPD - estilos inline para funcionar com Tailwind E Bootstrap --}}
<div id="lgpd-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:1050;background:rgba(15,23,42,0.97);color:#fff;padding:0.85rem 1rem;backdrop-filter:blur(8px);border-top:1px solid rgba(255,255,255,0.08);">
    <div style="max-width:1280px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;gap:0.75rem;">
        <div style="flex:1;font-size:0.85rem;min-width:200px;">
            Utilizamos cookies para melhorar sua experiência. Ao continuar, você concorda com nossa
            <a href="/politica-de-privacidade" style="color:#a5b4fc;text-decoration:underline;">Política de Privacidade</a>.
        </div>
        <button id="lgpd-accept" style="background:#4f46e5;color:#fff;border:none;border-radius:0.5rem;padding:0.45rem 1.1rem;font-size:0.8rem;font-weight:600;cursor:pointer;">Aceitar</button>
        <button id="lgpd-decline" style="background:transparent;color:#94a3b8;border:1px solid #475569;border-radius:0.5rem;padding:0.45rem 1.1rem;font-size:0.8rem;font-weight:600;cursor:pointer;">Recusar</button>
    </div>
</div>

<script>
@if($hasReactBuild)
// Preload do SPA (some apos React montar)
window.addEventListener('load', () => {
    setTimeout(() => {
        const p = document.getElementById('preload');
        if (p) { p.style.opacity = '0'; setTimeout(() => p.remove(), 400); }
    }, 300);
});
@endif

// LGPD
(function () {
    const banner = document.getElementById('lgpd-banner');
    if (!banner) return;
    if (localStorage.getItem('lgpd-consent') === null) banner.classList.remove('d-none');
    const accept = document.getElementById('lgpd-accept');
    const decline = document.getElementById('lgpd-decline');
    if (accept) accept.onclick = () => { localStorage.setItem('lgpd-consent', 'accept'); banner.classList.add('d-none'); };
    if (decline) decline.onclick = () => { localStorage.setItem('lgpd-consent', 'decline'); banner.classList.add('d-none'); };
})();

// Service Worker (registra apenas em producao com build pronto)
@if($hasReactBuild)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js?v={{ file_exists(public_path('sw.js')) ? filemtime(public_path('sw.js')) : time() }}')
            .catch(function (error) {
                console.warn('Service Worker nao registrado:', error);
            });
    });
}
@else
// Sem build do SPA: garante que SW antigo nao serve cache desatualizado
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(regs => regs.forEach(r => r.unregister()));
    if ('caches' in window) {
        caches.keys().then(keys => keys.forEach(k => caches.delete(k)));
    }
}

// Voltar ao topo SSR
(function () {
    const btn = document.getElementById('back-to-top');
    if (!btn) return;
    window.addEventListener('scroll', () => {
        btn.style.display = window.scrollY > 400 ? 'inline-flex' : 'none';
    });
    btn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
})();
@endif

// Detecta PWA instalado (controla menu footer)
window.isPwaInstalled = window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;
</script>
</body>
</html>
