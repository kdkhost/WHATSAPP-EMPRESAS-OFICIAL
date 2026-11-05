@php
    try {
        $settings = app(\App\Modules\Settings\Services\SettingsManager::class);
        $appName = $settings->get('app.name', config('app.name'));
        $appLogo = $settings->get('app.logo');
        $appFavicon = $settings->get('app.favicon');
    } catch (\Throwable) {
        $appName = config('app.name', 'CW Solutions');
        $appLogo = null;
        $appFavicon = null;
    }

    $icon = $__env->yieldContent('icon', 'warning');
    $icons = [
        'lock' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6 10h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="2"/><path d="M12 15v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        'search' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m21 21-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/></svg>',
        'server' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="4" width="16" height="6" rx="2" stroke="currentColor" stroke-width="2"/><rect x="4" y="14" width="16" height="6" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 7h.01M8 17h.01M12 7h4M12 17h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        'warning' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 2 20h20L12 3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 9v5M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('code') - @yield('title') | {{ $appName }}</title>
    @if($appFavicon)
        <link rel="icon" href="{{ $appFavicon }}">
        <link rel="shortcut icon" href="{{ $appFavicon }}">
        <link rel="apple-touch-icon" href="{{ $appFavicon }}">
    @endif
    <style>
        *,*::before,*::after{box-sizing:border-box}
        :root{
            color-scheme:light dark;
            --page-bg:#f4f6f9;
            --page-text:#121826;
            --page-muted:#5d6678;
            --card-bg:#ffffff;
            --card-border:#dfe5ef;
            --primary:#6610f2;
            --primary-contrast:#ffffff;
            --shadow:0 24px 70px rgba(18,24,38,.14);
        }
        @media (prefers-color-scheme:dark){
            :root{
                --page-bg:#020617;
                --page-text:#f8fafc;
                --page-muted:#9aa7bd;
                --card-bg:#0b1020;
                --card-border:#1d2942;
                --primary:#6d5dfc;
                --shadow:0 24px 70px rgba(0,0,0,.42);
            }
        }
        body{
            margin:0;
            min-height:100vh;
            display:grid;
            place-items:center;
            padding:24px;
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 22%, transparent), transparent 36%),
                var(--page-bg);
            color:var(--page-text);
            font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        }
        .error-shell{
            width:min(100%,620px);
            border:1px solid var(--card-border);
            border-radius:22px;
            background:color-mix(in srgb, var(--card-bg) 96%, transparent);
            box-shadow:var(--shadow);
            padding:42px;
            text-align:center;
        }
        .brand{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:28px}
        .brand img{max-height:48px;max-width:190px;object-fit:contain}
        .brand-mark{
            width:48px;height:48px;border-radius:14px;
            display:grid;place-items:center;
            background:var(--primary);color:var(--primary-contrast);
            font-weight:800;font-size:20px;
        }
        .brand-name{font-weight:800;font-size:18px}
        .icon{
            width:54px;height:54px;margin:0 auto 18px;
            display:grid;place-items:center;color:var(--primary);
        }
        .icon svg{width:54px;height:54px}
        .code{font-size:88px;line-height:.95;font-weight:900;color:var(--primary);letter-spacing:0}
        h1{font-size:28px;line-height:1.2;margin:12px 0;color:var(--page-text)}
        .message{font-size:16px;line-height:1.65;color:var(--page-muted);margin:0 auto 28px;max-width:490px}
        .actions{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
        .btn{
            min-height:44px;
            display:inline-flex;align-items:center;justify-content:center;
            padding:0 18px;border-radius:12px;
            font-size:14px;font-weight:800;text-decoration:none;
            border:1px solid var(--card-border);
            color:var(--page-text);background:transparent;
            cursor:pointer;
        }
        .btn-primary{background:var(--primary);border-color:var(--primary);color:var(--primary-contrast)}
        .footer{margin-top:28px;font-size:13px;color:var(--page-muted)}
        @media (max-width:520px){
            .error-shell{padding:30px 22px;border-radius:18px}
            .code{font-size:68px}
            h1{font-size:23px}
        }
    </style>
</head>
<body>
    <main class="error-shell" role="main">
        <div class="brand">
            @if($appLogo)
                <img src="{{ $appLogo }}" alt="{{ $appName }}">
            @else
                <span class="brand-mark">{{ strtoupper(mb_substr($appName, 0, 1)) }}</span>
                <span class="brand-name">{{ $appName }}</span>
            @endif
        </div>

        <div class="icon">{!! $icons[$icon] ?? $icons['warning'] !!}</div>
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <div class="message">@yield('message')</div>

        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">Página inicial</a>
            @auth('admin')
                <a class="btn" href="{{ url('/admin') }}">Painel admin</a>
            @endauth
            <button class="btn" type="button" onclick="history.back()">Voltar</button>
        </div>

        <div class="footer">{{ $appName }}</div>
    </main>
</body>
</html>
