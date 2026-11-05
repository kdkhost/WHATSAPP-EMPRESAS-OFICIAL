<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
    try {
        $domainTenant = app(\App\Modules\WhiteLabel\Services\TenantWhiteLabelService::class)->tenantForHost(request()->getHost());
        $domainWhiteLabel = $domainTenant?->whiteLabelSettings() ?? [];
        $domainWhiteLabelEnabled = $domainTenant?->whiteLabelEnabled() && (bool)($domainWhiteLabel['custom_login'] ?? true);
    } catch (\Throwable) {
        $domainTenant = null;
        $domainWhiteLabel = [];
        $domainWhiteLabelEnabled = false;
    }

    try {
        $appName = ($domainWhiteLabelEnabled && !empty($domainWhiteLabel['brand_name']))
            ? $domainWhiteLabel['brand_name']
            : ($settingsManager->get('app.name', config('app.name')) ?? config('app.name'));
    } catch (\Throwable) {
        $appName = config('app.name');
    }

    try {
        $appLogo = ($domainWhiteLabelEnabled && !empty($domainWhiteLabel['logo_url']))
            ? $domainWhiteLabel['logo_url']
            : $settingsManager->get('app.logo');
    } catch (\Throwable) {
        $appLogo = null;
    }

    try {
        $appFavicon = ($domainWhiteLabelEnabled && !empty($domainWhiteLabel['favicon_url']))
            ? $domainWhiteLabel['favicon_url']
            : $settingsManager->get('app.favicon');
    } catch (\Throwable) {
        $appFavicon = null;
    }

    $primaryColor = ($domainWhiteLabelEnabled && !empty($domainWhiteLabel['primary_color'])) ? $domainWhiteLabel['primary_color'] : '#0d6efd';
    $secondaryColor = ($domainWhiteLabelEnabled && !empty($domainWhiteLabel['secondary_color'])) ? $domainWhiteLabel['secondary_color'] : '#6610f2';
    $loginSlogan = ($domainWhiteLabelEnabled && !empty($domainWhiteLabel['slogan'])) ? $domainWhiteLabel['slogan'] : 'Reduza inadimplencia com cobrancas automaticas via WhatsApp.';
@endphp
<title>@yield('title') - {{ $appName }}</title>
@if($appFavicon)
<link rel="icon" href="{{ $appFavicon }}">
<link rel="shortcut icon" href="{{ $appFavicon }}">
<link rel="apple-touch-icon" href="{{ $appFavicon }}">
@endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.split { min-height: 100vh; }
.split .left { background: linear-gradient(135deg, {{ $primaryColor }}, {{ $secondaryColor }}); color: #fff; }
@media (max-width: 991px) { .split .left { display: none; } }
</style>
</head>
<body class="bg-body-tertiary">
<div class="container-fluid split">
    <div class="row min-vh-100">
        {{-- Lado esquerdo apenas em telas grandes --}}
        <div class="col-lg-6 left d-flex flex-column justify-content-center p-5">
            <h1 class="display-5 fw-bold d-flex align-items-center gap-3">
                @if($appLogo)
                    <img src="{{ $appLogo }}" alt="{{ $appName }}" style="max-height:52px;max-width:220px;object-fit:contain;">
                @else
                    <i class="bi bi-whatsapp"></i>
                @endif
                <span>{{ $appName }}</span>
            </h1>
            <p class="lead">{{ $loginSlogan }}</p>
            <ul class="list-unstyled fs-5">
                <li><i class="bi bi-check2-circle me-2"></i>Templates editaveis com preview</li>
                <li><i class="bi bi-check2-circle me-2"></i>Multiplos gateways de pagamento</li>
                <li><i class="bi bi-check2-circle me-2"></i>PWA com notificacoes push</li>
                <li><i class="bi bi-check2-circle me-2"></i>Modular - instale addons</li>
            </ul>
        </div>
        <div class="col-lg-6 d-flex align-items-center justify-content-center p-4">
            <div class="card shadow-sm" style="max-width: @yield('wide', false) ? '720px' : '480px'; width: 100%;">
                <div class="card-body p-4">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/admin.js') }}"></script>
</body>
</html>
