@extends('admin.layouts.app')
@section('title', 'White Label')
@php
    $tenantEnabled = $settingsManager->get('whitelabel.tenant_enabled', true);
@endphp
@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-palette me-2"></i>White Label</h3>
            <p class="text-muted mb-0 small">Personalize o sistema com a marca do cliente: logo, cores, domínio e rodapé.</p>
        </div>
        <span class="badge bg-info fs-6"><i class="bi bi-building me-1"></i>Por Tenant</span>
    </div>

    <form id="frm-whitelabel" class="row g-4">
        @csrf
        <div class="col-lg-8">
            {{-- Branding --}}
            <div class="card">
                <div class="card-header"><strong><i class="bi bi-brush me-1"></i> Branding</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome do Sistema</label>
                            <input name="brand_name" class="form-control" value="{{ $settingsManager->get('whitelabel.brand_name', config('app.name')) }}"
                                   placeholder="Nome exibido para o cliente">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Slogan</label>
                            <input name="slogan" class="form-control" value="{{ $settingsManager->get('whitelabel.slogan', '') }}"
                                   placeholder="Frase curta descritiva">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Logo (URL)</label>
                            <input name="logo_url" class="form-control" value="{{ $settingsManager->get('whitelabel.logo_url', '') }}"
                                   placeholder="https://...logo.png">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Favicon (URL)</label>
                            <input name="favicon_url" class="form-control" value="{{ $settingsManager->get('whitelabel.favicon_url', '') }}"
                                   placeholder="https://...favicon.ico">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cor Primaria</label>
                            <input type="color" name="primary_color" class="form-control form-control-color"
                                   value="{{ $settingsManager->get('whitelabel.primary_color', '#0d6efd') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cor Secundaria</label>
                            <input type="color" name="secondary_color" class="form-control form-control-color"
                                   value="{{ $settingsManager->get('whitelabel.secondary_color', '#6610f2') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cor do Sidebar</label>
                            <input type="color" name="sidebar_color" class="form-control form-control-color"
                                   value="{{ $settingsManager->get('whitelabel.sidebar_color', '#1a1d21') }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dominio customizado --}}
            <div class="card mt-3">
                <div class="card-header"><strong><i class="bi bi-globe me-1"></i> Dominio Customizado</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Dominio Proprio</label>
                            <input name="custom_domain" class="form-control" value="{{ $settingsManager->get('whitelabel.custom_domain', '') }}"
                                   placeholder="app.seucliente.com.br">
                            <small class="text-muted">O cliente acessa o sistema pelo dominio dele. Requer configuracao DNS.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SSL</label>
                            <select name="ssl_enabled" class="form-select">
                                <option value="1" @selected($settingsManager->get('whitelabel.ssl_enabled', true))>Ativo (Let's Encrypt)</option>
                                <option value="0" @selected(!$settingsManager->get('whitelabel.ssl_enabled', true))>Desativado</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Alvo DNS para tenants</label>
                            <input name="dns_target" class="form-control" value="{{ $settingsManager->get('whitelabel.dns_target', $dnsTarget ?? request()->getHost()) }}"
                                   placeholder="{{ request()->getHost() }}">
                            <small class="text-muted">CNAME/A esperado na validacao visual do painel do usuario.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">White Label no painel do usuario</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="tenant_enabled" value="1" id="wl-tenant-enabled"
                                       @checked($tenantEnabled)>
                                <label class="form-check-label" for="wl-tenant-enabled">Permitir configuracao pelo tenant</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info small mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Configuracao DNS:</strong> O cliente deve apontar um registro CNAME do dominio dele para
                                <code>{{ $dnsTarget ?? request()->getHost() }}</code>. Apos propagacao, o painel do usuario valida o dominio visualmente.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cloudflare for SaaS --}}
            <div class="card mt-3">
                <div class="card-header"><strong><i class="bi bi-cloud-check me-1"></i> Integração Cloudflare for SaaS</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Cloudflare API Token</label>
                            <input type="password" name="cloudflare_api_token" class="form-control" value="{{ $settingsManager->get('whitelabel.cloudflare_api_token', '') }}"
                                   placeholder="********************************">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Zone ID (ID da Zona)</label>
                            <input name="cloudflare_zone_id" class="form-control" value="{{ $settingsManager->get('whitelabel.cloudflare_zone_id', '') }}"
                                   placeholder="Ex: 023e105f4ecef8ad9ca31a8372d0c353">
                        </div>
                        <div class="col-12 mt-3">
                            <details class="small text-muted bg-light p-3 rounded border">
                                <summary class="fw-semibold cursor-pointer mb-2" style="cursor:pointer;list-style:none;">
                                    <i class="bi bi-question-circle me-1"></i> Como obter e configurar o Cloudflare? (Clique para ver)
                                </summary>
                                <ol class="ps-3 mb-0 mt-2">
                                    <li class="mb-2">Acesse sua conta do Cloudflare, vá na aba do seu domínio principal (o mesmo usado pelo sistema).</li>
                                    <li class="mb-2">Na página "Visão Geral", role para baixo. Do lado direito você encontrará a seção <strong>ID da zona</strong> (Zone ID). Copie e cole acima.</li>
                                    <li class="mb-2">No menu lateral do Cloudflare, vá em <strong>SSL/TLS > Nomes de host personalizados (Custom Hostnames)</strong> e clique em <strong>Habilitar Cloudflare for SaaS</strong> e cadastre um método de pagamento (você tem 100 domínios gratuitos, só cobra após isso).</li>
                                    <li class="mb-2">Ainda no Cloudflare, clique no ícone de "Perfil" (canto superior direito) > <strong>Meu Perfil</strong> > <strong>Tokens de API</strong>.</li>
                                    <li class="mb-2">Clique em <strong>Criar Token</strong> > Vá no final da página em "Criar Token Personalizado" > Clique em "Começar".</li>
                                    <li class="mb-2">Nome do token: "SSL Painel SaaS". Em "Permissões", selecione <strong>Zona</strong> > <strong>Custom Hostnames</strong> > <strong>Editar</strong>.</li>
                                    <li class="mb-1">Em "Recursos de Zona", selecione <strong>Incluir</strong> > <strong>Zona Específica</strong> > Selecione seu domínio. Clique em "Continuar para resumo" e "Criar Token". Cole o Token gerado acima!</li>
                                </ol>
                            </details>
                        </div>
                    </div>
                </div>
            </div>

            {{-- CSS/JS customizado --}}
            <div class="card mt-3">
                <div class="card-header"><strong><i class="bi bi-code-slash me-1"></i> Personalizacao Avancada</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">CSS Customizado</label>
                        <textarea name="custom_css" class="form-control font-monospace" rows="5"
                                  placeholder="/* CSS adicional do tenant */&#10;.navbar { background: #333 !important; }">{{ $settingsManager->get('whitelabel.custom_css', '') }}</textarea>
                        <small class="text-muted">CSS injetado apos o tema padrao. Use para ajustes visuais especificos.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Texto do Rodape</label>
                        <input name="footer_text" class="form-control" value="{{ $settingsManager->get('whitelabel.footer_text', '') }}"
                               placeholder="© 2025 Empresa do Cliente. Todos os direitos reservados.">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="hide_powered_by" value="1" id="wl-powered"
                                       @checked($settingsManager->get('whitelabel.hide_powered_by', false))>
                                <label class="form-check-label" for="wl-powered">Ocultar "Powered by"</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="custom_login" value="1" id="wl-login"
                                       @checked($settingsManager->get('whitelabel.custom_login', false))>
                                <label class="form-check-label" for="wl-login">Tela de login personalizada</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2-circle me-1"></i> Salvar Configuracoes
                    </button>
                </div>
            </div>
        </div>

        {{-- Preview --}}
        <div class="col-lg-4">
            <div class="card sticky-top" style="top:80px;">
                <div class="card-header"><strong><i class="bi bi-eye me-1"></i> Preview</strong></div>
                <div class="card-body p-0">
                    <div id="wl-preview" class="rounded overflow-hidden" style="min-height:300px;">
                        <div class="p-2 text-white text-center" id="preview-header" style="background:#0d6efd;">
                            <img id="preview-logo" src="" class="d-none mb-1" style="max-height:30px;">
                            <div id="preview-name" class="fw-bold small">{{ config('app.name') }}</div>
                        </div>
                        <div class="d-flex" style="min-height:250px;">
                            <div id="preview-sidebar" class="p-2" style="width:60px;background:#1a1d21;">
                                <div class="bg-white bg-opacity-10 rounded mb-2" style="height:8px;"></div>
                                <div class="bg-white bg-opacity-10 rounded mb-2" style="height:8px;"></div>
                                <div class="bg-white bg-opacity-25 rounded mb-2" style="height:8px;"></div>
                                <div class="bg-white bg-opacity-10 rounded mb-2" style="height:8px;"></div>
                            </div>
                            <div class="flex-grow-1 p-2 bg-light">
                                <div class="bg-white rounded p-2 shadow-sm mb-2" style="height:40px;"></div>
                                <div class="bg-white rounded p-2 shadow-sm" style="height:80px;"></div>
                            </div>
                        </div>
                        <div id="preview-footer" class="p-1 text-center bg-light border-top">
                            <small class="text-muted" id="preview-footer-text">© {{ date('Y') }} {{ config('app.name') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><strong><i class="bi bi-info-circle me-1"></i> Como funciona</strong></div>
                <div class="card-body small">
                    <ol class="ps-3 mb-0">
                        <li class="mb-2">Configure o branding (logo, cores, nome)</li>
                        <li class="mb-2">Opcionalmente, configure um dominio proprio</li>
                        <li class="mb-2">O sistema detecta o tenant pelo dominio e aplica o branding</li>
                        <li class="mb-2">O cliente ve o sistema como se fosse 100% dele</li>
                    </ol>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><strong><i class="bi bi-globe2 me-1"></i> Dominios de tenants</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tenant</th>
                                    <th>Dominio</th>
                                    <th>Status</th>
                                    <th>Ultima validacao</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse(($tenantDomains ?? collect()) as $domainTenant)
                                @php
                                    $badge = match ($domainTenant->custom_domain_status) {
                                        'active' => 'success',
                                        'pending' => 'warning',
                                        'failed' => 'danger',
                                        default => 'secondary',
                                    };
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $domainTenant->company_name }}</td>
                                    <td><code>{{ $domainTenant->custom_domain }}</code></td>
                                    <td><span class="badge text-bg-{{ $badge }}">{{ $domainTenant->custom_domain_status ?: 'not_configured' }}</span></td>
                                    <td class="small text-muted">{{ $domainTenant->custom_domain_last_checked_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4 small">Nenhum dominio de tenant configurado.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

// Preview em tempo real
document.querySelectorAll('#frm-whitelabel input, #frm-whitelabel textarea, #frm-whitelabel select').forEach(el => {
    el.addEventListener('input', updatePreview);
});

function updatePreview() {
    const form = document.getElementById('frm-whitelabel');
    const primary = form.querySelector('[name=primary_color]').value;
    const sidebar = form.querySelector('[name=sidebar_color]').value;
    const name = form.querySelector('[name=brand_name]').value || '{{ config("app.name") }}';
    const logo = form.querySelector('[name=logo_url]').value;
    const footer = form.querySelector('[name=footer_text]').value || `© ${new Date().getFullYear()} ${name}`;

    document.getElementById('preview-header').style.background = primary;
    document.getElementById('preview-sidebar').style.background = sidebar;
    document.getElementById('preview-name').textContent = name;
    document.getElementById('preview-footer-text').textContent = footer;

    const logoEl = document.getElementById('preview-logo');
    if (logo) { logoEl.src = logo; logoEl.classList.remove('d-none'); }
    else { logoEl.classList.add('d-none'); }
}

// Salvar
document.getElementById('frm-whitelabel').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    ['tenant_enabled', 'ssl_enabled', 'hide_powered_by', 'custom_login'].forEach(name => {
        const el = e.target.querySelector(`[name="${name}"]`);
        fd.set(name, el && el.checked ? '1' : '0');
    });
    const r = await fetch('{{ url("/admin/white-label") }}', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: fd,
    });
    if (r.ok) window.toast.success('Configuracoes White Label salvas!');
    else {
        const err = await r.json().catch(() => ({}));
        window.toast.error(err.message || 'Erro ao salvar.');
    }
});

updatePreview();
</script>
@endpush
