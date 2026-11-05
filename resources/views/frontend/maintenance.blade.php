@php
    $appName = $settingsManager->get('app.name', config('app.name'));
    $appFavicon = $settingsManager->get('app.favicon');
    $bgImage = $background_image ?? '';
    $bgOpacity = ($background_opacity ?? 50) / 100;
    $overlayColor = $overlay_color ?? '#000000';
    $txtColor = $text_color ?? '#ffffff';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title ?? 'Em manutencao' }} - {{ $appName }}</title>
@if($appFavicon)
<link rel="icon" href="{{ $appFavicon }}">
<link rel="shortcut icon" href="{{ $appFavicon }}">
<link rel="apple-touch-icon" href="{{ $appFavicon }}">
@endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; overflow: hidden; }
.bg-layer { position: fixed; inset: 0; z-index: 0; background-size: cover; background-position: center; background-repeat: no-repeat; }
.overlay-layer { position: fixed; inset: 0; z-index: 1; }
.content-layer { position: relative; z-index: 2; text-align: center; padding: 2rem; max-width: 650px; width: 100%; }
.glass-box { background: rgba(255,255,255,0.08); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.12); border-radius: 1.5rem; padding: 3rem 2.5rem; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
.countdown { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 2.5rem; font-weight: 700; letter-spacing: 0.05em; }
.countdown-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.15em; opacity: 0.7; }
.pulse-icon { animation: pulse 2s ease-in-out infinite; }
@keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.05); opacity: 0.8; } }
</style>
</head>
<body>

{{-- Background Image Layer --}}
@if($bgImage)
<div class="bg-layer" style="background-image: url('{{ $bgImage }}');"></div>
@else
<div class="bg-layer" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);"></div>
@endif

{{-- Overlay Layer --}}
<div class="overlay-layer" style="background: {{ $overlayColor }}; opacity: {{ $bgOpacity }};"></div>

{{-- Content --}}
<div class="content-layer">
    <div class="glass-box">
        @if(!empty($logo))
        <img src="{{ $logo }}" alt="Logo" style="max-height:60px;margin-bottom:1.5rem;" class="d-block mx-auto">
        @endif

        <div class="pulse-icon mb-3">
            <i class="bi bi-tools" style="font-size:3.5rem;color:{{ $txtColor }};"></i>
        </div>

        <h1 class="fw-bold mb-3" style="font-size:2rem;color:{{ $txtColor }};">{{ $title ?? 'Em manutenção' }}</h1>

        <p class="mb-4" style="font-size:1.1rem;color:{{ $txtColor }};opacity:0.85;line-height:1.6;">
            {{ $message ?? 'Estamos realizando melhorias no sistema. Voltaremos em breve!' }}
        </p>

        @if(!empty($until_at))
        <div class="mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,0.15);">
            <p class="countdown-label mb-2" style="color:{{ $txtColor }};">Previsão de retorno</p>
            <div id="countdown" class="countdown" data-until="{{ $until_at }}" style="color:{{ $txtColor }};">
                --:--:--
            </div>
        </div>
        @endif

        <div class="mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,0.1);">
            <small style="color:{{ $txtColor }};opacity:0.5;">{{ $appName }}</small>
        </div>
    </div>
</div>

<script>
(function () {
    var el = document.getElementById('countdown');
    if (!el) return;
    var until = new Date(el.dataset.until).getTime();
    function tick() {
        var diff = until - Date.now();
        if (diff <= 0) { location.reload(); return; }
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        el.textContent = (d > 0 ? d + 'd ' : '') +
            String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            String(s).padStart(2, '0');
    }
    setInterval(tick, 1000);
    tick();
})();
</script>
</body>
</html>
