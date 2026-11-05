@extends('admin.layouts.app')
@section('title', 'Google Analytics & Business')

@section('content')
<div class="container-fluid">
    @php
        $normalizeTrackingId = function (?string $value, string $prefix): ?string {
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }
            $normalized = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $value) ?? $value);
            $prefix = strtoupper($prefix);
            return str_starts_with($normalized, $prefix) ? $normalized : $prefix.ltrim($normalized, '-');
        };
        $previewGtmId = $normalizeTrackingId($google['gtm_id'], 'GTM-');
        $previewGaId = $normalizeTrackingId($google['ga_id'], 'G-');
        $previewGadsId = $normalizeTrackingId($google['gads_id'], 'AW-');
    @endphp

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-google me-2"></i>Google Analytics & Business</h3>
            <p class="text-muted mb-0 small">Rastreamento, conversões, Tag Manager e perfil do Google Meu Negócio.</p>
        </div>
        <div class="d-flex gap-2">
            @php
                $activeCount = collect([$google['ga_id'], $google['gads_id'], $google['gtm_id']])->filter()->count();
            @endphp
            <span class="badge bg-{{ $activeCount === 3 ? 'success' : ($activeCount > 0 ? 'warning' : 'danger') }} fs-6">
                <i class="bi bi-broadcast me-1"></i>{{ $activeCount }}/3 ativos
            </span>
        </div>
    </div>

    <form id="frm-google">
        @csrf
        <div class="row g-4">

            {{-- COLUNA ESQUERDA --}}
            <div class="col-lg-7">

                {{-- Card: Rastreamento --}}
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-broadcast me-1"></i> Rastreamento e Conversões</span>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-save-google">
                            <i class="bi bi-check2-circle me-1"></i>Salvar
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            {{-- GA4 --}}
                            <div class="col-12">
                                <div class="d-flex align-items-start gap-3 p-3 border rounded {{ $google['ga_id'] ? 'border-success bg-success bg-opacity-10' : 'bg-body-tertiary' }}">
                                    <div class="stat-icon {{ $google['ga_id'] ? 'bg-success' : 'bg-secondary' }} text-white flex-shrink-0" style="width:40px;height:40px;font-size:0.9rem;">
                                        <i class="bi bi-bar-chart-line"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label mb-1 fw-semibold">Google Analytics 4 (GA4)</label>
                                        <div class="input-group">
                                            <input type="text" name="ga_id" id="ga-id" class="form-control"
                                                   value="{{ $google['ga_id'] }}" placeholder="G-XXXXXXXXXX">
                                            <span class="input-group-text {{ $google['ga_id'] ? 'text-success' : 'text-muted' }}" id="ga-status">
                                                <i class="bi bi-{{ $google['ga_id'] ? 'check-circle-fill' : 'circle' }}"></i>
                                            </span>
                                        </div>
                                        <small class="text-muted">Rastreia visitantes, páginas vistas, eventos e conversões.</small>
                                    </div>
                                </div>
                            </div>

                            {{-- Google Ads --}}
                            <div class="col-12">
                                <div class="d-flex align-items-start gap-3 p-3 border rounded {{ $google['gads_id'] ? 'border-success bg-success bg-opacity-10' : 'bg-body-tertiary' }}">
                                    <div class="stat-icon {{ $google['gads_id'] ? 'bg-warning' : 'bg-secondary' }} text-white flex-shrink-0" style="width:40px;height:40px;font-size:0.9rem;">
                                        <i class="bi bi-megaphone"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label mb-1 fw-semibold">Google Ads (Conversões)</label>
                                        <div class="input-group">
                                            <input type="text" name="gads_id" id="gads-id" class="form-control"
                                                   value="{{ $google['gads_id'] }}" placeholder="AW-XXXXXXXXXX">
                                            <span class="input-group-text {{ $google['gads_id'] ? 'text-success' : 'text-muted' }}">
                                                <i class="bi bi-{{ $google['gads_id'] ? 'check-circle-fill' : 'circle' }}"></i>
                                            </span>
                                        </div>
                                        <small class="text-muted">Rastreia conversões de campanhas pagas do Google Ads.</small>
                                    </div>
                                </div>
                            </div>

                            {{-- GTM --}}
                            <div class="col-12">
                                <div class="d-flex align-items-start gap-3 p-3 border rounded {{ $google['gtm_id'] ? 'border-success bg-success bg-opacity-10' : 'bg-body-tertiary' }}">
                                    <div class="stat-icon {{ $google['gtm_id'] ? 'bg-info' : 'bg-secondary' }} text-white flex-shrink-0" style="width:40px;height:40px;font-size:0.9rem;">
                                        <i class="bi bi-tags"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label mb-1 fw-semibold">Google Tag Manager (GTM)</label>
                                        <div class="input-group">
                                            <input type="text" name="gtm_id" id="gtm-id" class="form-control"
                                                   value="{{ $google['gtm_id'] }}" placeholder="GTM-XXXXXXX">
                                            <span class="input-group-text {{ $google['gtm_id'] ? 'text-success' : 'text-muted' }}">
                                                <i class="bi bi-{{ $google['gtm_id'] ? 'check-circle-fill' : 'circle' }}"></i>
                                            </span>
                                        </div>
                                        <small class="text-muted">Gerencia todas as tags (GA4, Ads, Pixel) em um só lugar.</small>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Card: Google Meu Negócio --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-shop me-1"></i> Google Meu Negócio</span>
                        <span class="badge bg-info-subtle text-info">Perfil Local</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome da Empresa</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                                    <input type="text" name="business_name" class="form-control"
                                           value="{{ $google['business_name'] }}" placeholder="Minha Empresa LTDA">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="text" name="business_phone" class="form-control" data-mask="phone"
                                           value="{{ $google['business_phone'] }}" placeholder="(11) 99999-9999">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Endereço Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" name="business_address" class="form-control"
                                           value="{{ $google['business_address'] }}" placeholder="Rua Exemplo, 123 - Centro, Cidade - UF">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Horário de Funcionamento</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                    <input type="text" name="business_hours" class="form-control"
                                           value="{{ $google['business_hours'] }}" placeholder="Seg-Sex 8h-18h, Sáb 8h-12h">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Depoimentos Google</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="reviews_enabled" id="reviews-enabled" value="1"
                                           @checked($google['reviews_enabled']) style="width:2.5rem;height:1.25rem;">
                                    <label class="form-check-label small" for="reviews-enabled">Exibir no site</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i>Salvar Tudo
                        </button>
                    </div>
                </div>
            </div>

            {{-- COLUNA DIREITA --}}
            <div class="col-lg-5">

                {{-- Preview do Código --}}
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-code-slash me-1"></i> Código Gerado</span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">Tags inseridas automaticamente no <code>&lt;head&gt;</code> do site:</p>
                        <pre class="bg-body-tertiary rounded p-3 small mb-0" id="code-preview" style="font-size:0.7rem;max-height:200px;overflow-y:auto;"><code id="code-output">{{ $previewGtmId ? "<!-- GTM -->\n<script>...{$previewGtmId}...</script>" : '' }}{{ $previewGaId ? "\n<!-- GA4 -->\n<script>gtag('config','{$previewGaId}');</script>" : '' }}{{ $previewGadsId ? "\n<!-- Ads -->\n<script>gtag('config','{$previewGadsId}');</script>" : '' }}{{ !$previewGaId && !$previewGtmId && !$previewGadsId ? '<!-- Nenhum tracking configurado -->' : '' }}</code></pre>
                    </div>
                </div>

                {{-- Como obter --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-question-circle me-1"></i> Como Obter os IDs</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-3 py-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="stat-icon bg-primary-subtle text-primary flex-shrink-0" style="width:32px;height:32px;font-size:0.75rem;">
                                        <i class="bi bi-bar-chart-line"></i>
                                    </div>
                                    <div>
                                        <strong class="small">GA4:</strong>
                                        <span class="small text-muted">Analytics → Admin → Fluxos de dados → ID de medição</span>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item px-3 py-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="stat-icon bg-warning-subtle text-warning flex-shrink-0" style="width:32px;height:32px;font-size:0.75rem;">
                                        <i class="bi bi-megaphone"></i>
                                    </div>
                                    <div>
                                        <strong class="small">Ads:</strong>
                                        <span class="small text-muted">Google Ads → Ferramentas → Conversões → ID</span>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item px-3 py-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="stat-icon bg-info-subtle text-info flex-shrink-0" style="width:32px;height:32px;font-size:0.75rem;">
                                        <i class="bi bi-tags"></i>
                                    </div>
                                    <div>
                                        <strong class="small">GTM:</strong>
                                        <span class="small text-muted">Tag Manager → Container → ID do container</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Dica --}}
                <div class="card mt-3 border-warning">
                    <div class="card-body d-flex gap-2 align-items-start">
                        <i class="bi bi-exclamation-triangle text-warning fs-5 flex-shrink-0"></i>
                        <div class="small">
                            <strong>Importante:</strong>
                            <span class="text-muted">Se usar GTM, configure GA4 e Ads <strong>dentro</strong> do Tag Manager. Não preencha os 3 campos simultaneamente para evitar duplicação de dados.</span>
                        </div>
                    </div>
                </div>

                {{-- Links externos --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-box-arrow-up-right me-1"></i> Acessar</span>
                    </div>
                    <div class="card-body d-flex flex-column gap-2">
                        <a href="https://analytics.google.com" target="_blank" class="btn btn-outline-primary btn-sm w-100 text-start">
                            <i class="bi bi-bar-chart-line me-2"></i>Google Analytics
                        </a>
                        <a href="https://ads.google.com" target="_blank" class="btn btn-outline-warning btn-sm w-100 text-start">
                            <i class="bi bi-megaphone me-2"></i>Google Ads
                        </a>
                        <a href="https://tagmanager.google.com" target="_blank" class="btn btn-outline-info btn-sm w-100 text-start">
                            <i class="bi bi-tags me-2"></i>Tag Manager
                        </a>
                        <a href="https://business.google.com" target="_blank" class="btn btn-outline-success btn-sm w-100 text-start">
                            <i class="bi bi-shop me-2"></i>Google Meu Negócio
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const form = document.getElementById('frm-google');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-google');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        try {
            const fd = new FormData(form);
            fd.set('reviews_enabled', document.getElementById('reviews-enabled').checked ? '1' : '0');

            const r = await fetch('{{ route("admin.settings.google.update") }}', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (r.ok && data.ok) window.toast.success('Configurações Google salvas!');
            else window.toast.error(data.errors ? Object.values(data.errors).flat().join(', ') : 'Erro ao salvar.');
        } catch (err) { window.toast.error('Erro de conexão.'); }
        finally { btn.disabled = false; btn.innerHTML = orig; }
    });
})();
</script>
@endpush
