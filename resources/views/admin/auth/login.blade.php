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
@endphp
<title>Login - {{ $appName }}</title>
@if($appFavicon)
<link rel="icon" href="{{ $appFavicon }}">
<link rel="shortcut icon" href="{{ $appFavicon }}">
<link rel="apple-touch-icon" href="{{ $appFavicon }}">
@endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.split { min-height: 100vh; }
.split .left { background: linear-gradient(135deg, #0d6efd, #6610f2); color: #fff; }
@media (max-width: 991px) { .split .left { display: none; } }
</style>
</head>
<body class="bg-body-tertiary">
<div class="container-fluid split">
    <div class="row min-vh-100">
        {{-- Coluna 1 (apenas em telas grandes) --}}
        <div class="col-lg-6 left d-flex flex-column justify-content-center p-5">
            <h1 class="display-5 fw-bold d-flex align-items-center gap-3">
                @if($appLogo)
                    <img src="{{ $appLogo }}" alt="{{ $appName }}" style="max-height:52px;max-width:220px;object-fit:contain;">
                @else
                    <i class="bi bi-whatsapp"></i>
                @endif
                <span>{{ $appName }}</span>
            </h1>
            <p class="lead">Sistema modular de cobranca automatica via WhatsApp.</p>
            <ul class="list-unstyled fs-5">
                <li><i class="bi bi-check2-circle me-2"></i>Templates editaveis com preview</li>
                <li><i class="bi bi-check2-circle me-2"></i>Cron interno sem dependencia de trafego</li>
                <li><i class="bi bi-check2-circle me-2"></i>WAF proprio com painel grafico</li>
                <li><i class="bi bi-check2-circle me-2"></i>PWA gerenciavel pelo admin</li>
            </ul>
        </div>
        {{-- Coluna 2 - formulario --}}
        <div class="col-lg-6 d-flex align-items-center justify-content-center p-4">
            <div class="card shadow-sm" style="max-width: 420px; width: 100%;">
                <div class="card-body p-4">
                    <h2 class="h4 mb-3 text-center">Entrar no painel</h2>
                    @if(session('error'))
                    <div class="alert alert-warning small">{{ session('error') }}</div>
                    @endif
                    @if($errors->any())
                    <div class="alert alert-danger small">{{ $errors->first() }}</div>
                    @endif
                    <form method="POST" action="{{ route('admin.login.attempt') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" class="form-control" required autofocus value="{{ old('email') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember">
                            <label class="form-check-label" for="remember">Manter-me conectado</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Entrar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
