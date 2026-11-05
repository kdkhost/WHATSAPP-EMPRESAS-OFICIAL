@php
    use Illuminate\Support\Facades\Auth;

    $user = Auth::user()?->refresh();
    $appName = $settingsManager->get('app.name', config('app.name'));
    $appLogo = $settingsManager->get('app.logo');
    $appFavicon = $settingsManager->get('app.favicon');
    $themeCookie = request()->cookie('admin_theme');
    $themeDark = $themeCookie === 'dark' || ($themeCookie !== 'light' && (bool) ($user->theme_dark ?? false));
@endphp
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="{{ $themeDark ? 'dark' : 'light' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1a1d21" media="(prefers-color-scheme: dark)">
<meta name="color-scheme" content="light dark">
<title>Verificacao 2FA - {{ $appName }}</title>
@if($appFavicon)
<link rel="icon" href="{{ $appFavicon }}">
<link rel="shortcut icon" href="{{ $appFavicon }}">
<link rel="apple-touch-icon" href="{{ $appFavicon }}">
@endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/css/adminlte.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
<style>
:root {
    --bs-primary: {{ $settingsManager->get('theme.primary', '#0d6efd') }};
    --bs-secondary: {{ $settingsManager->get('theme.secondary', '#6610f2') }};
    --app-radius-premium: 18px;
    --app-border-soft: rgba(15,23,42,.08);
    --app-shadow-premium: 0 22px 60px rgba(15,23,42,.12);
}
[data-bs-theme="dark"] {
    --app-border-soft: rgba(255,255,255,.09);
    --app-shadow-premium: 0 22px 60px rgba(0,0,0,.35);
}
body {
    min-height: 100vh;
    margin: 0;
    display: grid;
    place-items: center;
    background:
        linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 12%, transparent), transparent 46%),
        linear-gradient(180deg, var(--bs-body-bg), color-mix(in srgb, var(--bs-body-bg) 88%, var(--bs-primary) 12%));
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
}
.verify-shell {
    width: min(920px, calc(100vw - 28px));
    display: grid;
    grid-template-columns: minmax(0, 1fr) 420px;
    border: 1px solid var(--app-border-soft);
    border-radius: 24px;
    background: var(--bs-body-bg);
    box-shadow: var(--app-shadow-premium);
    overflow: hidden;
}
.verify-brand {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background:
        linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 14%, transparent), transparent 55%),
        var(--bs-tertiary-bg);
}
.brand-logo {
    max-height: 46px;
    max-width: 220px;
    object-fit: contain;
}
.brand-mark {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: linear-gradient(135deg, var(--bs-primary), var(--bs-secondary));
    box-shadow: 0 16px 28px color-mix(in srgb, var(--bs-primary) 24%, transparent);
}
.verify-panel {
    padding: 2rem;
}
.verify-kicker {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .4rem .65rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--bs-primary) 10%, transparent);
    color: var(--bs-primary);
    font-size: .74rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.code-input {
    height: 64px;
    font-size: 1.8rem;
    letter-spacing: .45rem;
    text-align: center;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    border-radius: 16px;
}
.verify-meta {
    border-top: 1px solid var(--app-border-soft);
    padding-top: 1rem;
    color: var(--bs-secondary-color);
}
@media (max-width: 880px) {
    .verify-shell { grid-template-columns: 1fr; }
    .verify-brand { display: none; }
}
@media (max-width: 520px) {
    .verify-panel { padding: 1.25rem; }
    .code-input { font-size: 1.35rem; letter-spacing: .32rem; }
}
</style>
</head>
<body>
<main class="verify-shell">
    <section class="verify-brand">
        <div>
            @if($appLogo)
                <img src="{{ $appLogo }}" alt="{{ $appName }}" class="brand-logo mb-4">
            @else
                <div class="brand-mark mb-4"><i class="bi bi-whatsapp fs-3"></i></div>
            @endif
            <span class="verify-kicker"><i class="bi bi-shield-check"></i> Acesso protegido</span>
            <h1 class="h2 mt-3 mb-3">Confirme sua identidade para continuar.</h1>
            <p class="text-muted mb-0">O painel permanece bloqueado ate a validacao do codigo do aplicativo autenticador ou de um codigo de recuperacao.</p>
        </div>
        <div class="verify-meta small">
            <div class="fw-semibold text-body">{{ $user->name ?? 'Cliente' }}</div>
            <div>{{ $user->email ?? '' }}</div>
        </div>
    </section>

    <section class="verify-panel">
        <div class="text-center mb-4">
            <div class="brand-mark mx-auto mb-3"><i class="bi bi-shield-lock fs-3"></i></div>
            <span class="verify-kicker">Verificacao em duas etapas</span>
            <h2 class="h4 mt-3 mb-2">Digite o codigo 2FA</h2>
            <p class="text-muted small mb-0">Use o codigo de 6 digitos do autenticador ou um codigo de recuperacao.</p>
        </div>

        <form id="tenant-2fa-verify-form" class="mx-auto" style="max-width:340px;">
            @csrf
            <input type="text"
                   name="code"
                   class="form-control code-input mb-3"
                   maxlength="15"
                   inputmode="numeric"
                   autocomplete="one-time-code"
                   autofocus
                   placeholder="000000">
            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-check2-circle me-1"></i> Verificar
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">
            @csrf
            <button type="submit" class="btn btn-link btn-sm text-muted">Sair da conta</button>
        </form>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js"></script>
<script>
function notify(message, type = 'info') {
    const colors = {
        success: 'linear-gradient(to right, #198754, #157347)',
        error: 'linear-gradient(to right, #dc3545, #b02a37)',
        info: 'linear-gradient(to right, #0dcaf0, #0aa2c0)',
    };
    Toastify({ text: message, duration: 3500, gravity: 'top', position: 'right', close: true, style: { background: colors[type] || colors.info } }).showToast();
}

document.getElementById('tenant-2fa-verify-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const code = form.code.value.trim();
    const button = form.querySelector('button[type="submit"]');
    const original = button.innerHTML;

    if (code.length < 6) {
        notify('Digite o codigo completo.', 'error');
        return;
    }

    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verificando...';

    try {
        const response = await fetch('{{ route("tenant.2fa.verify.check") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: new FormData(form),
        });
        const data = await response.json();
        if (response.ok && data.ok) {
            notify('2FA verificado.', 'success');
            setTimeout(() => window.location = data.redirect || '/painel', 350);
            return;
        }
        notify(data.error || data.message || 'Codigo invalido.', 'error');
        form.code.value = '';
        form.code.focus();
    } catch (error) {
        notify('Erro de rede ao validar o codigo.', 'error');
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
});
</script>
</body>
</html>
