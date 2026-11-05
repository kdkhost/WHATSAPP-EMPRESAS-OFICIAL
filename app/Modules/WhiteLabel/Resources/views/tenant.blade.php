@extends('tenancy::tenant.layout')
@section('title', 'White Label')

@php
    $wl = $tenant->whiteLabelSettings();
    $statusClass = match ($status['status'] ?? 'not_configured') {
        'active' => 'success',
        'pending' => 'warning',
        'failed' => 'danger',
        default => 'secondary',
    };
    $brandName = $wl['brand_name'] ?? $tenant->company_name;
    $primary = $wl['primary_color'] ?? '#0d6efd';
    $secondary = $wl['secondary_color'] ?? '#6610f2';
    $sidebar = $wl['sidebar_color'] ?? '#1a1d21';
@endphp

@section('content')
<div class="tenant-premium-hero mb-4">
    <div>
        <span class="tenant-premium-hero__eyebrow"><i class="bi bi-palette me-2"></i>White Label</span>
        <h1 class="tenant-premium-hero__title">Sua marca no painel.</h1>
        <p class="tenant-premium-hero__text">Configure nome, logo, cores, rodape e dominio proprio da sua conta com validacao visual de propagacao DNS.</p>
    </div>
    <div class="tenant-premium-metric" id="domain-status-card">
        <span class="tenant-premium-kicker">Dominio proprio</span>
        <div class="d-flex align-items-start justify-content-between gap-3">
            <div class="min-w-0">
                <h2 class="tenant-premium-title text-truncate" data-domain-label>{{ $tenant->custom_domain ?: 'Nao configurado' }}</h2>
                <p class="tenant-premium-subtitle mb-0" data-status-message>{{ $status['last_error'] ?? 'Informe um dominio para iniciar a validacao.' }}</p>
            </div>
            <span class="badge rounded-pill text-bg-{{ $statusClass }}" data-domain-badge>{{ $status['status_label'] }}</span>
        </div>
        <div class="small text-muted mt-3">
            <i class="bi bi-clock-history me-1"></i>
            <span data-last-check>{{ $status['last_checked_at'] ? 'Atualizado em '.$status['last_checked_at'] : 'Ainda nao validado' }}</span>
        </div>
    </div>
</div>

<form id="tenant-whitelabel-form" class="row g-4">
    @csrf
    <div class="col-xl-8">
        <div class="premium-panel mb-3">
            <div class="premium-panel__header">
                <div>
                    <span class="premium-panel__kicker">Identidade</span>
                    <h3 class="premium-panel__title">Branding da conta</h3>
                    <p class="premium-panel__subtitle">Essas informacoes aparecem no painel do cliente e na tela de login quando o dominio estiver validado.</p>
                </div>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="wl-enabled" @checked((bool)($wl['enabled'] ?? false))>
                    <label class="form-check-label small fw-semibold" for="wl-enabled">Ativo</label>
                </div>
            </div>
            <div class="premium-panel__body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome da marca</label>
                        <input name="brand_name" class="form-control form-control-lg" value="{{ $brandName }}" placeholder="Nome exibido no painel">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Slogan</label>
                        <input name="slogan" class="form-control form-control-lg" value="{{ $wl['slogan'] ?? '' }}" placeholder="Frase curta da sua marca">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Logo</label>
                        <input name="logo_url" class="form-control" value="{{ $wl['logo_url'] ?? '' }}" placeholder="https://seudominio.com/logo.png">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Favicon</label>
                        <input name="favicon_url" class="form-control" value="{{ $wl['favicon_url'] ?? '' }}" placeholder="https://seudominio.com/favicon.ico">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cor primaria</label>
                        <input type="color" name="primary_color" class="form-control form-control-color w-100" value="{{ $primary }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cor secundaria</label>
                        <input type="color" name="secondary_color" class="form-control form-control-color w-100" value="{{ $secondary }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cor da sidebar</label>
                        <input type="color" name="sidebar_color" class="form-control form-control-color w-100" value="{{ $sidebar }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="premium-panel mb-3">
            <div class="premium-panel__header">
                <div>
                    <span class="premium-panel__kicker">Dominio</span>
                    <h3 class="premium-panel__title">Acesso pelo seu dominio</h3>
                    <p class="premium-panel__subtitle">A validacao confere CNAME ou A apontando para o alvo do sistema antes de liberar o uso.</p>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-check-domain">
                    <i class="bi bi-arrow-repeat me-1"></i>Validar DNS
                </button>
            </div>
            <div class="premium-panel__body">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <label class="form-label">Dominio proprio</label>
                        <input name="custom_domain" class="form-control form-control-lg" value="{{ $tenant->custom_domain }}" placeholder="app.suaempresa.com.br">
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label">Alvo DNS</label>
                        <div class="input-group input-group-lg">
                            <input class="form-control font-monospace" value="{{ $status['dns_target'] }}" readonly data-dns-target>
                            <button class="btn btn-outline-secondary" type="button" data-copy-target="{{ $status['dns_target'] }}" title="Copiar">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="dns-guide">
                            <div class="dns-guide__item">
                                <span>CNAME</span>
                                <code data-domain-cname>{{ $tenant->custom_domain ?: 'app.suaempresa.com.br' }}</code>
                                <i class="bi bi-arrow-right"></i>
                                <code data-target-cname>{{ $status['dns_target'] }}</code>
                            </div>
                            <div class="dns-guide__item">
                                <span>Status</span>
                                <strong data-inline-status>{{ $status['status_label'] }}</strong>
                                <small data-inline-detail>{{ $status['verified_at'] ? 'Validado em '.$status['verified_at'] : ($status['last_error'] ?? 'Aguardando configuracao') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="premium-panel">
            <div class="premium-panel__header">
                <div>
                    <span class="premium-panel__kicker">Avancado</span>
                    <h3 class="premium-panel__title">Acabamento visual</h3>
                </div>
            </div>
            <div class="premium-panel__body">
                <div class="mb-3">
                    <label class="form-label">CSS customizado</label>
                    <textarea name="custom_css" class="form-control font-monospace" rows="5" placeholder="/* ajustes visuais da sua marca */">{{ $wl['custom_css'] ?? '' }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Texto do rodape</label>
                    <input name="footer_text" class="form-control" value="{{ $wl['footer_text'] ?? '' }}" placeholder="© {{ date('Y') }} {{ $brandName }}. Todos os direitos reservados.">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="hide_powered_by" value="1" id="wl-powered" @checked((bool)($wl['hide_powered_by'] ?? false))>
                            <label class="form-check-label" for="wl-powered">Ocultar assinatura do sistema</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="custom_login" value="1" id="wl-login" @checked((bool)($wl['custom_login'] ?? true))>
                            <label class="form-check-label" for="wl-login">Usar branding no login</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="premium-panel__footer">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1"></i>Salvar White Label
                </button>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="premium-sidecard sticky-xl-top" style="top:80px;">
            <div class="premium-sidecard__head">
                <div>
                    <span class="premium-sidecard__kicker">Preview</span>
                    <h3 class="premium-panel__title">Painel do cliente</h3>
                </div>
            </div>
            <div class="premium-sidecard__body">
                <div class="wl-preview" id="wl-preview">
                    <div class="wl-preview__top" style="background:{{ $primary }}">
                        <img src="{{ $wl['logo_url'] ?? '' }}" alt="" class="{{ empty($wl['logo_url']) ? 'd-none' : '' }}" data-preview-logo>
                        <strong data-preview-name>{{ $brandName }}</strong>
                        <small data-preview-slogan>{{ $wl['slogan'] ?? 'Painel premium' }}</small>
                    </div>
                    <div class="wl-preview__body">
                        <div class="wl-preview__side" style="background:{{ $sidebar }}">
                            <span></span><span></span><span class="active"></span><span></span>
                        </div>
                        <div class="wl-preview__content">
                            <div></div><div></div><div></div>
                        </div>
                    </div>
                    <div class="wl-preview__footer" data-preview-footer>
                        {{ $wl['footer_text'] ?? ('© '.date('Y').' '.$brandName) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('styles')
<style>
.dns-guide {
    display: grid;
    gap: .75rem;
    border: 1px solid var(--app-border-soft);
    border-radius: 16px;
    padding: 1rem;
    background: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
}
.dns-guide__item {
    display: grid;
    grid-template-columns: 70px minmax(0,1fr) auto minmax(0,1fr);
    gap: .75rem;
    align-items: center;
}
.dns-guide__item span { font-size: .75rem; font-weight: 800; text-transform: uppercase; color: var(--bs-secondary-color); }
.dns-guide__item code { display: block; overflow: hidden; text-overflow: ellipsis; padding: .45rem .6rem; border-radius: 10px; background: var(--bs-body-bg); border: 1px solid var(--app-border-soft); }
.dns-guide__item small { color: var(--bs-secondary-color); }
.wl-preview { border: 1px solid var(--app-border-soft); border-radius: 18px; overflow: hidden; background: var(--bs-body-bg); }
.wl-preview__top { color: #fff; min-height: 104px; padding: 1rem; display: flex; flex-direction: column; justify-content: center; gap: .25rem; }
.wl-preview__top img { max-width: 160px; max-height: 44px; object-fit: contain; margin-bottom: .35rem; }
.wl-preview__top strong { font-size: 1.1rem; line-height: 1.1; }
.wl-preview__top small { opacity: .82; }
.wl-preview__body { min-height: 210px; display: grid; grid-template-columns: 74px 1fr; }
.wl-preview__side { padding: 1rem .7rem; display: flex; flex-direction: column; gap: .7rem; }
.wl-preview__side span { height: 10px; border-radius: 999px; background: rgba(255,255,255,.16); }
.wl-preview__side span.active { background: rgba(255,255,255,.45); }
.wl-preview__content { padding: 1rem; display: grid; gap: .75rem; align-content: start; background: color-mix(in srgb, var(--bs-body-bg) 88%, var(--bs-primary) 12%); }
.wl-preview__content div { min-height: 44px; border-radius: 14px; background: var(--bs-body-bg); border: 1px solid var(--app-border-soft); box-shadow: var(--app-shadow-premium); }
.wl-preview__content div:first-child { min-height: 72px; }
.wl-preview__footer { padding: .8rem 1rem; text-align: center; color: var(--bs-secondary-color); font-size: .78rem; border-top: 1px solid var(--app-border-soft); }
@media (max-width: 767.98px) {
    .dns-guide__item { grid-template-columns: 1fr; }
    .dns-guide__item i { display: none; }
}
</style>
@endpush

@push('scripts')
<script>
(() => {
    const form = document.getElementById('tenant-whitelabel-form');
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const endpoints = {
        save: @json(route('tenant.whitelabel.save')),
        check: @json(route('tenant.whitelabel.check')),
        status: @json(route('tenant.whitelabel.status')),
    };
    const statusColors = {
        active: 'text-bg-success',
        pending: 'text-bg-warning',
        provisioning_ssl: 'text-bg-info',
        failed: 'text-bg-danger',
        not_configured: 'text-bg-secondary'
    };

    function boolField(name) {
        const el = form.querySelector(`[name="${name}"]`);
        return el && el.checked ? '1' : '0';
    }

    function updatePreview() {
        const name = form.brand_name.value || @json($tenant->company_name);
        const slogan = form.slogan.value || 'Painel premium';
        const logo = form.logo_url.value;
        const footer = form.footer_text.value || `© ${new Date().getFullYear()} ${name}`;
        document.querySelector('[data-preview-name]').textContent = name;
        document.querySelector('[data-preview-slogan]').textContent = slogan;
        document.querySelector('[data-preview-footer]').textContent = footer;
        document.querySelector('.wl-preview__top').style.background = form.primary_color.value;
        document.querySelector('.wl-preview__side').style.background = form.sidebar_color.value;
        const logoEl = document.querySelector('[data-preview-logo]');
        if (logo) {
            logoEl.src = logo;
            logoEl.classList.remove('d-none');
        } else {
            logoEl.classList.add('d-none');
        }
        document.querySelector('[data-domain-cname]').textContent = form.custom_domain.value || 'app.suaempresa.com.br';
    }

    function applyStatus(status) {
        const badge = document.querySelector('[data-domain-badge]');
        badge.textContent = status.status_label || 'Nao configurado';
        badge.className = `badge rounded-pill ${statusColors[status.status] || statusColors.not_configured}`;
        document.querySelector('[data-domain-label]').textContent = status.domain || 'Nao configurado';
        document.querySelector('[data-status-message]').textContent = status.message || status.last_error || (status.status === 'active' ? 'Dominio validado.' : 'Aguardando configuracao.');
        document.querySelector('[data-last-check]').textContent = status.last_checked_at ? `Atualizado em ${status.last_checked_at}` : 'Ainda nao validado';
        document.querySelector('[data-inline-status]').textContent = status.status_label || 'Nao configurado';
        document.querySelector('[data-inline-detail]').textContent = status.verified_at ? `Validado em ${status.verified_at}` : (status.last_error || 'Aguardando configuracao');
        document.querySelector('[data-dns-target]').value = status.dns_target || '';
        document.querySelector('[data-target-cname]').textContent = status.dns_target || '';
        document.querySelector('[data-copy-target]').dataset.copyTarget = status.dns_target || '';
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) },
            ...options
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.ok === false) {
            throw new Error(data.message || data.error || Object.values(data.errors || {})?.[0]?.[0] || 'Falha na requisicao.');
        }
        return data;
    }

    form.querySelectorAll('input, textarea').forEach(el => el.addEventListener('input', updatePreview));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const fd = new FormData(form);
        ['enabled', 'hide_powered_by', 'custom_login'].forEach(name => fd.set(name, boolField(name)));
        try {
            const data = await requestJson(endpoints.save, { method: 'POST', body: fd });
            applyStatus(data.status || {});
            window.toast.success('White Label salvo.');
        } catch (error) {
            window.toast.error(error.message);
        }
    });

    document.getElementById('btn-check-domain').addEventListener('click', async () => {
        try {
            const data = await requestJson(endpoints.check, { method: 'POST' });
            applyStatus(data.status || {});
            if (data.status?.status === 'active') {
                window.toast.success('Dominio validado com sucesso.');
            } else if (data.status?.status === 'provisioning_ssl') {
                window.toast.info('Gerando SSL no Cloudflare...');
            } else {
                window.toast.info('DNS ainda em propagacao.');
            }
        } catch (error) {
            window.toast.error(error.message);
        }
    });

    document.querySelector('[data-copy-target]').addEventListener('click', (event) => {
        const target = event.currentTarget.dataset.copyTarget;
        if (!target) return;
        navigator.clipboard?.writeText(target).then(() => window.toast.success('Alvo DNS copiado.'));
    });

    setInterval(async () => {
        const badge = document.querySelector('[data-domain-badge]');
        if (!badge) return;
        const txt = badge.textContent.toLowerCase();
        if (!txt.includes('dns') && !txt.includes('ssl')) return;
        try {
            const data = await requestJson(endpoints.check, { method: 'POST' });
            applyStatus(data.status || {});
            if (data.status?.status === 'active') {
                window.toast.success('Dominio validado automaticamente.');
            }
        } catch (error) {
            console.debug(error.message);
        }
    }, 60000);

    updatePreview();
})();
</script>
@endpush
