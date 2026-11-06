@extends('tenancy::tenant.layout')
@section('title', 'Instâncias')

@php
    use App\Support\Format;

    $connectedCount = $instances->where('status', 'connected')->count();
    $includedLimit = $tenant->includedInstanceLimit();
    $canCreateIncluded = $tenant->canCreateIncludedInstance();
    $extraPrice = (float) ($tenant->plan->extra_instance_price ?? 0);
    $billableExtraCount = $instances->where('is_extra_billable', true)->count();
    $safetyControl = \App\Models\WhatsAppSafetyControl::where('tenant_id', $tenant->id)->whereNull('tenant_instance_id')->first()
        ?: \App\Models\WhatsAppSafetyControl::where('tenant_id', $tenant->id)->first();
    $safetyMeta = is_array($safetyControl?->meta) ? $safetyControl->meta : [];
    $safetyMode = $safetyMeta['risk_mode'] ?? ($safetyControl && $safetyControl->limit_per_day > 300 ? 'accelerated' : 'safe');
    $safetyDailyLimit = (int) ($safetyControl?->limit_per_day ?: config('whatsapp_anti_ban.limits.per_day', 250));
    $safetyDelayMin = (int) ($safetyControl?->human_delay_min_seconds ?: config('whatsapp_anti_ban.human_delay.min_seconds', 12));
    $safetyDelayMax = (int) ($safetyControl?->human_delay_max_seconds ?: config('whatsapp_anti_ban.human_delay.max_seconds', 32));
    $adminMaxDaily = (int) config('whatsapp_anti_ban.tenant_settings.max_limit_per_day', 500);
    $statusLabels = [
        'creating' => 'Criando',
        'active' => 'Ativa',
        'connected' => 'Conectada',
        'disconnected' => 'Desconectada',
        'suspended' => 'Suspensa',
        'banned' => 'Banida',
    ];
    $statusColors = [
        'creating' => 'info',
        'active' => 'primary',
        'connected' => 'success',
        'disconnected' => 'warning',
        'suspended' => 'secondary',
        'banned' => 'danger',
    ];
    $instanceConfig = $instances->mapWithKeys(function ($inst) {
        return [$inst->id => [
            'id' => $inst->id,
            'display_name' => $inst->display_name,
            'phone' => $inst->phone,
            'meta_phone_number_id' => $inst->meta_phone_number_id,
            'meta_waba_id' => $inst->meta_waba_id,
            'has_meta_token' => filled($inst->meta_access_token),
            'quality_rating' => data_get($inst->meta, 'quality_rating', 'GREEN'),
            'status' => $inst->status,
            'webhook_url' => $inst->secure_webhook_url,
            'last_synced_at' => $inst->last_profile_sync_at?->toIso8601String(),
        ]];
    });
@endphp

@section('content')
<div class="tenant-premium-hero mb-4">
    <div>
        <span class="tenant-premium-hero__eyebrow"><i class="bi bi-whatsapp me-2"></i>WhatsApp Meta por instância</span>
        <h1 class="tenant-premium-hero__title">Instâncias WhatsApp</h1>
        <p class="tenant-premium-hero__text">Conecte o WhatsApp, sincronize avatar, nome, slogan e mantenha webhook e comportamento da API separados para cada instância do tenant.</p>
    </div>
    <div class="tenant-premium-metric">
        <span class="tenant-premium-kicker">Uso do plano</span>
        <div class="d-flex align-items-start justify-content-between gap-3">
            <div>
                <h2 class="tenant-premium-title" data-instance-usage>{{ $instances->count() }} / {{ $includedLimit }}</h2>
                <p class="tenant-premium-subtitle mb-0" data-instance-connected-count>
                    {{ $connectedCount }} conectada(s)
                    @if($billableExtraCount)
                        - {{ $billableExtraCount }} extra(s)
                    @endif
                </p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-warning btn-sm" id="btn-open-safety-modal" onclick="openSafetyModal()">
                    <i class="bi bi-shield-check me-1"></i> Proteção Anti-Ban
                </button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateInstance">
                    <i class="bi bi-plus-lg me-1"></i> Nova instância
                </button>
            </div>
        </div>
        @unless($canCreateIncluded)
            <div class="small text-warning-emphasis mt-2">
                Limite incluso atingido. Proxima instancia: {{ Format::money($extraPrice) }}/mes + pro-rata.
            </div>
        @endunless
    </div>
</div>

<div class="card mb-4 border-0 shadow-sm" style="border-radius: var(--app-radius-premium, 16px); background: color-mix(in srgb, var(--bs-body-bg) 94%, var(--bs-primary) 6%);">
    <div class="card-body py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: {{ $safetyMode === 'accelerated' ? 'rgba(220, 53, 69, 0.15)' : ($safetyMode === 'moderate' ? 'rgba(13, 110, 253, 0.15)' : 'rgba(25, 135, 84, 0.15)') }}; color: {{ $safetyMode === 'accelerated' ? '#dc3545' : ($safetyMode === 'moderate' ? '#0d6efd' : '#198754') }};">
                <i class="bi {{ $safetyMode === 'accelerated' ? 'bi-exclamation-triangle-fill fs-5' : 'bi-shield-check fs-5' }}" id="safety-banner-icon"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <strong class="text-body" id="safety-banner-title">
                        {{ $safetyMode === 'accelerated' ? 'Modo Acelerado (Por Sua Conta e Risco)' : ($safetyMode === 'moderate' ? 'Modo Moderado de Disparo' : 'Modo Ultra Seguro (Recomendado Meta)') }}
                    </strong>
                    <span class="badge {{ $safetyMode === 'accelerated' ? 'bg-danger' : ($safetyMode === 'moderate' ? 'bg-primary' : 'bg-success') }}" id="safety-banner-badge">
                        {{ $safetyMode === 'accelerated' ? 'Alto Risco' : ($safetyMode === 'moderate' ? 'Equilibrado' : 'Proteção Ativa') }}
                    </span>
                </div>
                <div class="small text-muted" id="safety-banner-details">
                    Limite: <strong>{{ $safetyDailyLimit }} msgs/dia</strong> &bull; Intervalo humano: <strong>{{ $safetyDelayMin }}-{{ $safetyDelayMax }}s</strong> &bull; Janela comercial: <strong>{{ ($safetyMeta['respect_business_hours'] ?? true) ? '08:00 às 20:00' : '24 horas' }}</strong>
                </div>
            </div>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="openSafetyModal()">
            <i class="bi bi-sliders me-1"></i> Configurar Cadência
        </button>
    </div>
</div>

<div class="premium-panel">
    <div class="premium-panel__header">
        <div>
            <span class="premium-panel__kicker">Conta WhatsApp</span>
            <h3 class="premium-panel__title">Identidade sincronizada</h3>
            <p class="premium-panel__subtitle">Os dados abaixo refletem o perfil conectado no WhatsApp Oficial.</p>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" data-instance-count-badge>{{ $instances->count() }} instância(s)</span>
    </div>
    <div class="premium-panel__body">
        @if($instances->count())
            <div class="instance-grid">
                @foreach($instances as $inst)
                    @include('tenancy::tenant.partials.instance-card', ['inst' => $inst, 'statusLabels' => $statusLabels, 'statusColors' => $statusColors])
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <i class="bi bi-phone"></i>
                <h3>Nenhuma instância cadastrada</h3>
                <p>Crie a primeira instância para gerar QR Code, conectar o WhatsApp e sincronizar a identidade do perfil.</p>
                <button class="btn btn-primary btn-sm empty-state__action" data-bs-toggle="modal" data-bs-target="#modalCreateInstance">
                    <i class="bi bi-plus-lg me-1"></i> Criar instância
                </button>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="modalCreateInstance" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nova instância</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frm-create-instance">
                    <div class="mb-3">
                        <label class="form-label">Nome de exibição</label>
                        <input type="text" name="name" class="form-control" required placeholder="Ex.: Atendimento Loja Centro" maxlength="60">
                        <div class="form-text">O identificador técnico será sanitizado automaticamente.</div>
                    </div>
                    @unless($canCreateIncluded)
                        <div class="alert alert-warning mb-0">
                            <strong>Instância extra recorrente.</strong>
                            O limite do plano foi atingido. Ao confirmar, o sistema cria a instância do WhatsApp e gera a cobrança pró-rata agrupada na fatura pendente.
                        </div>
                    @endunless
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-create-instance">
                    <i class="bi bi-check2 me-1"></i> Criar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Proteção Anti-Ban & Cadência de Disparos -->
<div class="modal fade" id="modalSafetySettings" tabindex="-1" aria-labelledby="modalSafetySettingsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--app-radius-premium, 18px); overflow: hidden;">
            <div class="modal-header bg-dark text-white border-0 py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle p-2 bg-success bg-opacity-25 text-success d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                        <i class="bi bi-shield-check fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalSafetySettingsLabel">Proteção Anti-Ban & Cadência de Disparos</h5>
                        <small class="text-white-50">Diretrizes da Meta / WhatsApp para preservação e integridade do seu número</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-0 shadow-sm mb-4 d-flex align-items-start gap-3" style="border-radius: 12px;">
                    <i class="bi bi-info-circle fs-4 mt-1 text-info"></i>
                    <div class="small">
                        <strong>Por que controlar o ritmo de disparos?</strong><br>
                        Os servidores da Meta utilizam inteligência artificial para detectar disparos massivos, envios em rajada simultânea e denúncias fora de horário. O sistema aplica inteligência anti-ban com <em>delay humano randômico</em>, <em>cooldown entre contatos</em> e <em>janela de atendimento comercial</em> para proteger a sua conta contra penalidades.
                    </div>
                </div>

                <form id="frm-safety-settings">
                    <label class="form-label fw-bold mb-2">Escolha o Perfil de Envio para o seu WhatsApp:</label>

                    <!-- Opção 1: Modo Ultra Seguro -->
                    <div class="card mb-3 border safety-mode-card cursor-pointer" id="card-mode-safe" style="border-radius: 14px; transition: all .2s ease;">
                        <div class="card-body p-3">
                            <div class="form-check d-flex align-items-start gap-3">
                                <input class="form-check-input mt-1" type="radio" name="safety_mode" id="mode-safe" value="safe" {{ $safetyMode === 'safe' ? 'checked' : '' }} onchange="onSafetyModeChange('safe')">
                                <label class="form-check-label flex-grow-1 cursor-pointer" for="mode-safe">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                        <span class="fw-bold text-success fs-6"><i class="bi bi-shield-fill-check me-1"></i> Modo Ultra Seguro (Recomendado Meta)</span>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Máxima Proteção Anti-Ban</span>
                                    </div>
                                    <p class="small text-muted mb-2">Ideal para cobranças de rotina e atendimento. Simula comportamento estritamente humano com espaçamento orgânico entre mensagens.</p>
                                    <div class="d-flex flex-wrap gap-3 small text-body-secondary">
                                        <span><i class="bi bi-calendar-check text-success me-1"></i> Limite: <strong>Até 200 msgs/dia</strong></span>
                                        <span><i class="bi bi-stopwatch text-success me-1"></i> Delay humano: <strong>15 a 35 segundos</strong></span>
                                        <span><i class="bi bi-clock text-success me-1"></i> Horário: <strong>08:00 às 20:00</strong></span>
                                        <span><i class="bi bi-thermometer-half text-success me-1"></i> <strong>Warmup progressivo</strong></span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Opção 2: Modo Moderado -->
                    <div class="card mb-3 border safety-mode-card cursor-pointer" id="card-mode-moderate" style="border-radius: 14px; transition: all .2s ease;">
                        <div class="card-body p-3">
                            <div class="form-check d-flex align-items-start gap-3">
                                <input class="form-check-input mt-1" type="radio" name="safety_mode" id="mode-moderate" value="moderate" {{ $safetyMode === 'moderate' ? 'checked' : '' }} onchange="onSafetyModeChange('moderate')">
                                <label class="form-check-label flex-grow-1 cursor-pointer" for="mode-moderate">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                        <span class="fw-bold text-primary fs-6"><i class="bi bi-speedometer2 me-1"></i> Modo Moderado</span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Equilibrado</span>
                                    </div>
                                    <p class="small text-muted mb-2">Para operações com chips maturados (mais de 30 dias de uso ativo). Ritmo ágil preservando pausas protetivas.</p>
                                    <div class="d-flex flex-wrap gap-3 small text-body-secondary">
                                        <span><i class="bi bi-calendar-check text-primary me-1"></i> Limite: <strong>Até 300 msgs/dia</strong></span>
                                        <span><i class="bi bi-stopwatch text-primary me-1"></i> Delay humano: <strong>10 a 22 segundos</strong></span>
                                        <span><i class="bi bi-clock text-primary me-1"></i> Horário: <strong>08:00 às 20:00</strong></span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Opção 3: Modo Acelerado (Por sua conta e risco) -->
                    <div class="card mb-3 border safety-mode-card cursor-pointer" id="card-mode-accelerated" style="border-radius: 14px; transition: all .2s ease;">
                        <div class="card-body p-3">
                            <div class="form-check d-flex align-items-start gap-3">
                                <input class="form-check-input mt-1" type="radio" name="safety_mode" id="mode-accelerated" value="accelerated" {{ $safetyMode === 'accelerated' ? 'checked' : '' }} onchange="onSafetyModeChange('accelerated')">
                                <label class="form-check-label flex-grow-1 cursor-pointer" for="mode-accelerated">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                        <span class="fw-bold text-danger fs-6"><i class="bi bi-exclamation-triangle-fill me-1"></i> Modo Acelerado (Alto Risco de Banimento)</span>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Por Sua Conta e Risco</span>
                                    </div>
                                    <p class="small text-muted mb-0">Permite velocidade superior e maior volume de disparos, reduzindo intervalos protetivos. <strong>Atenção:</strong> Risco significativo de bloqueio ou banimento do número pela Meta.</p>
                                </label>
                            </div>

                            <!-- Campos Extras do Modo Acelerado -->
                            <div id="accelerated-settings-panel" class="mt-3 pt-3 border-top {{ $safetyMode === 'accelerated' ? '' : 'd-none' }}">
                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small fw-bold">Limite diário desejado (Máx: {{ $adminMaxDaily }})</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control" id="custom-daily-limit" name="custom_daily_limit" value="{{ min($adminMaxDaily, max(50, $safetyDailyLimit)) }}" min="20" max="{{ $adminMaxDaily }}">
                                            <span class="input-group-text">msgs/dia</span>
                                        </div>
                                        <div class="form-text small">Teto máximo autorizado pelo administrador: {{ $adminMaxDaily }} envios/dia.</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small fw-bold">Janela Comercial (08h às 20h)</label>
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input" type="checkbox" id="custom-respect-hours" name="custom_respect_hours" value="1" {{ ($safetyMeta['respect_business_hours'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="custom-respect-hours">Pausar envios fora do horário comercial</label>
                                        </div>
                                        <div class="form-text small">Desativar permite envios 24h, aumentando o risco de denúncias por clientes.</div>
                                    </div>
                                </div>

                                <div class="alert alert-danger border-0 shadow-sm p-3 mb-3" style="border-radius: 10px;">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="bi bi-exclamation-octagon-fill text-danger fs-4 mt-1"></i>
                                        <div>
                                            <strong class="text-danger">TERMO DE CIÊNCIA E RESPONSABILIDADE EXCLUSIVA:</strong>
                                            <p class="small mb-0 mt-1">
                                                Ao ativar o modo acelerado, você reconhece que a cadência de envios pode contrariar os termos e diretrizes oficiais da Meta. Você declara ter plena ciência de que isso pode acarretar em bloqueio temporário ou <strong>banimento definitivo e irreversível</strong> do seu número telefônico de WhatsApp. O sistema e a plataforma não se responsabilizam sob nenhuma hipótese por números banidos ou desativados pela Meta.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="risk-acknowledged-check" name="risk_acknowledged" value="1" {{ !empty($safetyMeta['risk_accepted_at']) ? 'checked' : '' }}>
                                    <label class="form-check-label small fw-bold text-danger" for="risk-acknowledged-check">
                                        Li, compreendi e assumo integralmente os riscos de banimento da minha linha telefônica por minha conta e risco.
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-0 py-3 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-save-safety-settings" onclick="saveSafetySettings()">
                    <i class="bi bi-shield-check me-1"></i> Salvar Configuração
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalQR" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-patch-check-fill text-success me-2"></i>Conexão Oficial Meta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-shield-check text-success display-4 mb-3"></i>
                <h5 class="fw-bold">Sem necessidade de QR Code</h5>
                <p class="text-muted small">A WhatsApp Cloud API Oficial da Meta conecta de forma nativa e segura através do Phone Number ID e Token de Acesso Permanente, sem risco de desconexão ou necessidade de emulação de celular.</p>
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalInstanceConfig" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title"><i class="bi bi-patch-check-fill text-success me-2"></i>Credenciais Meta WhatsApp Cloud API</h5>
                    <small class="text-muted">Ajustes da API Oficial da Meta (Graph API v21.0) para esta instância.</small>
                    <div class="config-sync-indicator mt-2 d-none" id="config-sync-indicator">
                        <i class="bi bi-cloud-check me-1"></i>
                        <span id="config-sync-text">Sincronizado com WhatsApp Oficial</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frm-instance-config">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="alert alert-info py-2 px-3 small mb-2 d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill fs-5"></i>
                                <div>
                                    Esta instância opera diretamente na <strong>WhatsApp Cloud API v21.0 da Meta</strong>. Caso deixe os campos em branco, serão utilizadas as credenciais globais do sistema.
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nome de exibição</label>
                            <input type="text" class="form-control" name="display_name" placeholder="Ex: Suporte Financeiro" maxlength="80">
                            <div class="form-text">Identificador interno da linha no painel.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Número de Telefone</label>
                            <input type="text" class="form-control" name="phone" placeholder="Ex: 5511999999999" maxlength="30">
                            <div class="form-text">Número oficial do WhatsApp com DDI e DDD.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Phone Number ID da Meta</label>
                            <input type="text" class="form-control font-monospace" name="meta_phone_number_id" placeholder="Ex: 109283746592817" maxlength="64">
                            <div class="form-text">Obtido no Meta for Developers > WhatsApp > API Setup.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">WABA ID (Conta Comercial)</label>
                            <input type="text" class="form-control font-monospace" name="meta_waba_id" placeholder="Ex: 192837465019283" maxlength="64">
                            <div class="form-text">WhatsApp Business Account ID.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Token de Acesso Permanente (System User Token)</label>
                            <input type="password" class="form-control font-monospace" name="meta_access_token" autocomplete="new-password" placeholder="EAAG... (deixe em branco para manter o token atual)">
                            <div class="form-text" id="token-status">
                                <i class="bi bi-shield-lock me-1"></i> Armazenado com criptografia AES-256 no banco de dados.
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded p-3 bg-body-tertiary">
                                <h6 class="fw-bold mb-2"><i class="bi bi-arrow-repeat me-1"></i> Webhook Oficial da Meta</h6>
                                <p class="small text-muted mb-2">Cadastre este webhook no Meta Developer Portal para receber status de entrega e mensagens:</p>
                                
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Callback URL (URL de Retorno)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control font-monospace" name="webhook_url_display" readonly>
                                        <button class="btn btn-outline-secondary" type="button" id="btn-copy-webhook">
                                            <i class="bi bi-clipboard me-1"></i> Copiar
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="form-label small fw-semibold">Verify Token (Token de Verificação)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control font-monospace" name="webhook_verify_token_display" value="{{ config('whatsapp.meta_webhook_verify_token', 'kdkhost_meta_webhook_secret_2026') }}" readonly>
                                        <button class="btn btn-outline-secondary" type="button" id="btn-copy-verify-token">
                                            <i class="bi bi-clipboard me-1"></i> Copiar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="btn-save-config">
                    <i class="bi bi-cloud-check me-1"></i> Salvar e Testar Conexão
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.instance-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; }
.instance-card {
    border: 1px solid var(--app-border-soft);
    border-radius: 18px;
    background: color-mix(in srgb, var(--bs-body-bg) 94%, var(--bs-primary) 6%);
    padding: 1rem;
    box-shadow: var(--app-shadow-premium);
}
.instance-card__top { display: flex; gap: .85rem; align-items: flex-start; margin-bottom: 1rem; }
.instance-avatar {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    overflow: hidden;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    background: color-mix(in srgb, var(--bs-success) 18%, var(--bs-body-bg));
    color: var(--bs-success);
    font-weight: 800;
    font-size: 1.4rem;
    border: 1px solid color-mix(in srgb, var(--bs-success) 35%, var(--app-border-soft));
}
.instance-avatar img { width: 100%; height: 100%; object-fit: cover; }
.instance-card__title { font-size: 1.08rem; font-weight: 800; letter-spacing: 0; }
.instance-card__code { margin-top: .15rem; font-size: .78rem; color: var(--bs-secondary-color); }
.instance-card__slogan { margin: .5rem 0 0; color: var(--bs-secondary-color); line-height: 1.45; white-space: normal; }
.instance-card__meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem;
    margin-bottom: 1rem;
}
.instance-card__meta > div {
    min-width: 0;
    padding: .75rem;
    border: 1px solid var(--app-border-soft);
    border-radius: 14px;
    background: var(--bs-body-bg);
}
.instance-card__meta span { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); margin-bottom: .25rem; }
.instance-card__meta strong { display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.instance-card__actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.config-section {
    border: 1px solid var(--app-border-soft);
    border-radius: 16px;
    padding: 1rem;
    background: var(--bs-body-bg);
}
.config-section h6 { font-weight: 800; margin-bottom: .85rem; }
.event-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .55rem; }
.event-pill {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .6rem .7rem;
    border: 1px solid var(--app-border-soft);
    border-radius: 12px;
    background: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    cursor: pointer;
}
.event-pill span { font-size: .78rem; font-weight: 700; }
.event-pill--detail { align-items: flex-start; }
.event-pill__body { display: block; min-width: 0; }
.event-pill__meta { display: inline-flex; align-items: center; gap: .45rem; min-width: 0; }
.event-pill__body code { display: inline-block; margin-top: .35rem; font-size: .72rem; }
.config-switch-card {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .85rem;
    border: 1px solid var(--app-border-soft);
    border-radius: 14px;
    background: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    min-height: 100%;
}
.config-switch-card.form-switch { padding-left: .85rem; }
.config-switch-card .form-check-input {
    float: none;
    margin-left: 0;
    margin-top: .18rem;
    flex: 0 0 auto;
}
.config-switch-card .form-check-label {
    padding-left: 0;
    display: block;
    min-width: 0;
    flex: 1 1 auto;
}
.config-switch-card__header { display: inline-flex; align-items: center; gap: .45rem; min-width: 0; }
.config-help-button {
    width: 1.25rem;
    height: 1.25rem;
    border: 0;
    padding: 0;
    border-radius: 999px;
    display: inline-grid;
    place-items: center;
    color: var(--bs-info);
    background: color-mix(in srgb, var(--bs-info) 12%, transparent);
    flex: 0 0 auto;
}
.config-help-button:hover,
.config-help-button:focus-visible {
    color: var(--bs-white);
    background: var(--bs-info);
    outline: none;
}
.config-help-button i { font-size: .8rem; line-height: 1; }
.config-sync-indicator {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .78rem;
    color: var(--bs-success-text-emphasis);
    border: 1px solid color-mix(in srgb, var(--bs-success) 45%, transparent);
    background: color-mix(in srgb, var(--bs-success) 18%, transparent);
    border-radius: 999px;
    padding: .2rem .55rem;
}
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .75rem;
    text-align: center;
    padding: 3rem 1rem;
    color: var(--bs-secondary-color);
}
.empty-state > i { font-size: 3rem; color: var(--bs-primary); margin-bottom: .15rem; }
.empty-state h3 { color: var(--bs-body-color); margin: 0; }
.empty-state p { max-width: 540px; margin: 0; line-height: 1.55; }
.empty-state__action { width: auto; min-width: 0; padding-inline: .85rem; }
[data-bs-theme="dark"] .instance-card { background: rgba(17,24,39,.88); }
[data-bs-theme="dark"] .config-section,
[data-bs-theme="dark"] .instance-card__meta > div { background: rgba(255,255,255,.03); }
@media (max-width: 575.98px) {
    .instance-grid { grid-template-columns: 1fr; }
    .instance-card__top { flex-wrap: wrap; }
    .instance-card__meta { grid-template-columns: 1fr; }
    .instance-card__actions .btn { width: 100%; }
}
</style>
@endpush

@push('scripts')
<script>
const instanceConfig = @json($instanceConfig);
const statusLabels = @json($statusLabels);
const statusColors = @json($statusColors);
const instancesBaseUrl = '{{ url('/painel/instancias') }}';
const syncDateTimeFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'medium' });

function formatSyncTimestamp(value) {
    if (!value) return '';
    const dt = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(dt.getTime())) return '';
    return syncDateTimeFormat.format(dt);
}

function updateConfigSyncIndicator(id, timestamp = null) {
    const indicator = document.getElementById('config-sync-indicator');
    const text = document.getElementById('config-sync-text');
    if (!indicator || !text) return;

    const cfg = instanceConfig[id] || {};
    const rawTimestamp = timestamp || cfg.last_synced_at || '';
    const formattedTimestamp = formatSyncTimestamp(rawTimestamp);
    if (!formattedTimestamp) {
        indicator.classList.add('d-none');
        text.textContent = 'Sincronizado com WhatsApp Oficial agora';
        return;
    }

    indicator.classList.remove('d-none');
    text.textContent = `Sincronizado com WhatsApp Oficial em ${formattedTimestamp}`;
}

function notify(type, message) {
    if (window.toast?.[type]) {
        window.toast[type](message);
        return;
    }
    Toastify({ text: message, duration: 3500, gravity: 'top', position: 'right' }).showToast();
}

function setLoading(button, loading) {
    if (!button) return;
    button.disabled = loading;
    button.dataset.originalHtml = button.dataset.originalHtml || button.innerHTML;
    button.innerHTML = loading ? '<span class="spinner-border spinner-border-sm me-1"></span>Aguarde' : button.dataset.originalHtml;
}

async function submitCreateInstance(purchaseExtra = false) {
    const form = document.getElementById('frm-create-instance');
    const btn = document.getElementById('btn-create-instance');
    const fd = new FormData(form);
    if (purchaseExtra) fd.set('purchase_extra', '1');

    setLoading(btn, true);

    try {
        const r = await fetch('{{ route("tenant.instances.create") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd
        });
        const d = await r.json();
        if (d.ok) {
            const message = d.requires_purchase
                ? 'Instância extra criada e cobrança pró-rata gerada.'
                : 'Instância criada com sucesso.';
            notify('success', message);
            bootstrap.Modal.getInstance(document.getElementById('modalCreateInstance'))?.hide();
            form.reset();
            insertInstanceCard(d);
            updateInstanceCounts(d.counts);
            if (d.instance?.id) {
                openConfigModal(d.instance.id);
            }
        } else if (d.requires_purchase && d.quote && !purchaseExtra) {
            const quote = d.quote;
            const amount = formatMoney(quote.prorata_amount || 0);
            const recurring = formatMoney(quote.recurring_amount || 0);
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Comprar instância extra?',
                html: `<div class="text-start">
                    <p>Seu plano ja atingiu o limite de instancias inclusas.</p>
                    <p class="mb-1"><strong>Recorrente:</strong> ${recurring}/mes</p>
                    <p class="mb-0"><strong>Pro-rata agora:</strong> ${amount}</p>
                </div>`,
                showCancelButton: true,
                confirmButtonText: 'Confirmar compra',
                cancelButtonText: 'Cancelar',
                background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
            });

            if (result.isConfirmed) {
                await submitCreateInstance(true);
            }
        } else {
            notify('error', d.error || 'Erro ao criar instância.');
        }
    } catch (e) {
        notify('error', 'Erro de conexão.');
    } finally {
        setLoading(btn, false);
    }
}

document.getElementById('btn-create-instance')?.addEventListener('click', () => submitCreateInstance(false));

document.querySelectorAll('.btn-rename').forEach(btn => {
    btn.addEventListener('click', async () => {
        const result = await Swal.fire({
            title: 'Renomear instância',
            input: 'text',
            inputValue: btn.dataset.current || '',
            inputAttributes: { maxlength: 60 },
            showCancelButton: true,
            confirmButtonText: 'Salvar',
            cancelButtonText: 'Cancelar',
            background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
            color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
            inputValidator: value => !value ? 'Informe um nome.' : undefined,
        });
        if (!result.isConfirmed) return;

        try {
            const r = await fetch(`${instancesBaseUrl}/${btn.dataset.id}`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: result.value })
            });
            const d = await r.json();
            if (d.ok) {
                notify('success', 'Instância renomeada.');
                replaceInstanceCard(d);
            } else {
                notify('error', d.error || 'Erro ao renomear.');
            }
        } catch (e) {
            notify('error', 'Erro de conexão.');
        }
    });
});

document.querySelectorAll('.btn-delete-instance').forEach(btn => {
    btn.addEventListener('click', async () => {
        const connected = isInstanceConnected(btn);
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Excluir instância?',
            text: connected
                ? `A instância ${btn.dataset.name || ''} esta conectada. Desconecte-a primeiro antes de excluir.`
                : `A instância ${btn.dataset.name || ''} sera removida do sistema.`,
            showCancelButton: !connected,
            confirmButtonText: connected ? 'OK' : 'Excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
            color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
        });
        if (connected || !result.isConfirmed) return;
        if (!result.isConfirmed) return;

        setLoading(btn, true);
        try {
            const r = await fetch(`${instancesBaseUrl}/${btn.dataset.id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const d = await r.json();
            if (d.ok) {
                notify('success', 'Instância excluída.');
                document.querySelector(`[data-instance-card="${btn.dataset.id}"]`)?.remove();
                updateInstanceCounts(d.counts);
                ensureInstancesEmptyState();
            } else {
                notify('error', d.error || 'Erro ao excluir instância.');
            }
        } catch (e) {
            notify('error', 'Erro de conexão.');
        } finally {
            setLoading(btn, false);
        }
    });
});

function isInstanceConnected(btn) {
    const card = btn.closest('[data-instance-card]');
    if (!card) return false;
    const badge = card.querySelector('[data-status-badge]');
    return badge && badge.textContent.trim() === 'Conectada';
}

document.querySelectorAll('.btn-qr').forEach(btn => {
    btn.addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQR')).show();
    });
});

document.querySelectorAll('.btn-sync').forEach(btn => {
    btn.addEventListener('click', async () => {
        setLoading(btn, true);
        try {
            const r = await fetch(`${instancesBaseUrl}/${btn.dataset.id}/sincronizar`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const d = await r.json();
            if (d.ok) {
                applyInstancePayload(d.instance);
                notify('success', d.connected ? 'Perfil sincronizado e conectado.' : 'Perfil sincronizado. A instância ainda não está conectada.');
            } else {
                applyInstancePayload(d.instance);
                notify('error', d.error || 'Erro ao sincronizar.');
            }
        } catch (e) {
            notify('error', 'Erro de conexão.');
        } finally {
            setLoading(btn, false);
        }
    });
});

document.querySelectorAll('.btn-disconnect').forEach(btn => {
    btn.addEventListener('click', async () => {
        const result = await Swal.fire({
            title: 'Desconectar instancia?',
            text: 'A instancia sera desconectada do WhatsApp. Voce precisara escanear o QR Code novamente para reconectar.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, desconectar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
            color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
        });
        if (!result.isConfirmed) return;

        setLoading(btn, true);
        try {
            const r = await fetch(`${instancesBaseUrl}/${btn.dataset.id}/desconectar`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const d = await r.json();
            if (d.ok) {
                notify('success', 'Instancia desconectada.');
                btn.classList.add('d-none');
                const card = btn.closest('[data-instance-card]');
                if (card) {
                    const badge = card.querySelector('[data-status-badge]');
                    if (badge) { badge.className = 'badge text-bg-secondary'; badge.textContent = 'Desconectada'; }
                }
            } else {
                notify('error', d.error || 'Erro ao desconectar.');
            }
        } catch (e) {
            notify('error', 'Erro de conexao.');
        } finally {
            setLoading(btn, false);
        }
    });
});

document.querySelectorAll('.btn-config').forEach(btn => {
    btn.addEventListener('click', () => openConfigModal(btn.dataset.id));
});

function initConfigTooltips(root = document) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
        return;
    }

    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => {
        if (!element.dataset.helpBound) {
            element.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
            });
            element.dataset.helpBound = '1';
        }

        const current = bootstrap.Tooltip.getInstance(element);
        if (current) {
            current.dispose();
        }

        new bootstrap.Tooltip(element, {
            container: 'body',
            trigger: 'hover focus',
        });
    });
}

function clearStuckModalBackdrop() {
    if (typeof document === 'undefined') {
        return;
    }

    const hasVisibleModal = !!document.querySelector('.modal.show');
    if (hasVisibleModal) {
        return;
    }

    document.querySelectorAll('.modal-backdrop').forEach((element) => element.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
}

function openConfigModal(id) {
    const cfg = instanceConfig[id] || {};
    const form = document.getElementById('frm-instance-config');
    form.dataset.id = id;
    form.display_name.value = cfg.display_name || '';
    form.phone.value = cfg.phone || '';
    form.meta_phone_number_id.value = cfg.meta_phone_number_id || '';
    form.meta_waba_id.value = cfg.meta_waba_id || '';
    form.meta_access_token.value = '';

    const tokenStatus = document.getElementById('token-status');
    if (tokenStatus) {
        tokenStatus.innerHTML = cfg.has_meta_token
            ? '<i class="bi bi-shield-check text-success me-1"></i> Token permanente já configurado. Preencha apenas se desejar substituir.'
            : '<i class="bi bi-info-circle text-muted me-1"></i> Token individual pendente (utilizando credencial global do sistema).';
    }

    if (form.webhook_url_display) {
        form.webhook_url_display.value = cfg.webhook_url || `${instancesBaseUrl}/${id}/webhook`;
    }

    updateConfigSyncIndicator(id);

    const modalElement = document.getElementById('modalInstanceConfig');
    clearStuckModalBackdrop();
    initConfigTooltips(modalElement);
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
}

initConfigTooltips();

document.getElementById('btn-copy-webhook')?.addEventListener('click', async () => {
    const input = document.querySelector('[name="webhook_url_display"]');
    try {
        await navigator.clipboard.writeText(input.value);
        notify('success', 'URL do webhook copiada.');
    } catch (e) {
        input.select();
        document.execCommand('copy');
        notify('success', 'URL do webhook copiada.');
    }
});

document.getElementById('btn-copy-verify-token')?.addEventListener('click', async () => {
    const input = document.querySelector('[name="webhook_verify_token_display"]');
    try {
        await navigator.clipboard.writeText(input.value);
        notify('success', 'Token de verificação copiado.');
    } catch (e) {
        input.select();
        document.execCommand('copy');
        notify('success', 'Token de verificação copiado.');
    }
});

document.getElementById('btn-save-config')?.addEventListener('click', async () => {
    const form = document.getElementById('frm-instance-config');
    const id = form.dataset.id;
    const btn = document.getElementById('btn-save-config');
    const payload = {
        display_name: form.display_name.value,
        phone: form.phone.value,
        meta_phone_number_id: form.meta_phone_number_id.value,
        meta_waba_id: form.meta_waba_id.value,
        meta_access_token: form.meta_access_token.value,
    };

    setLoading(btn, true);
    try {
        const r = await fetch(`${instancesBaseUrl}/${id}/config`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const d = await r.json();
        if (d.ok) {
            const savedAt = d.synced_at || d.applied_at || new Date().toISOString();
            instanceConfig[id] = {
                ...instanceConfig[id],
                ...payload,
                has_meta_token: instanceConfig[id]?.has_meta_token || !!payload.meta_access_token,
                last_synced_at: savedAt
            };
            if (d.instance) {
                applyInstancePayload(d.instance);
            }
            updateConfigSyncIndicator(id, savedAt);
            notify('success', d.message || 'Configuração da Meta salva com sucesso.');
            bootstrap.Modal.getInstance(document.getElementById('modalInstanceConfig'))?.hide();
            clearStuckModalBackdrop();
        } else {
            notify('error', d.error || 'Erro ao salvar configuração.');
        }
    } catch (e) {
        notify('error', 'Erro de conexão.');
    } finally {
        setLoading(btn, false);
    }
});

document.addEventListener('hidden.bs.modal', () => {
    setTimeout(clearStuckModalBackdrop, 0);
});

document.addEventListener('shown.bs.modal', () => {
    clearStuckModalBackdrop();
});

function applyInstancePayload(instance) {
    if (!instance?.id) return;
    const card = document.querySelector(`[data-instance-card="${instance.id}"]`);
    if (!card) return;

    const name = instance.display_name || instance.profile_business_name || instance.profile_full_name || instance.profile_name || instance.profile_push_name || instance.instance_name;
    const avatarWrap = card.querySelector('[data-avatar-wrap]');
    if (avatarWrap) {
        if (instance.profile_avatar_url) {
            avatarWrap.innerHTML = `<img src="${escapeAttr(instance.profile_avatar_url)}" alt="${escapeAttr(name)}" referrerpolicy="no-referrer" onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('d-none');" data-profile-avatar><span data-profile-initial class="d-none">${escapeHtml((name || '-').charAt(0).toUpperCase())}</span>`;
        } else {
            avatarWrap.innerHTML = `<span data-profile-initial>${escapeHtml((name || '-').charAt(0).toUpperCase())}</span>`;
        }
    }

    const nameEl = card.querySelector('[data-profile-name]');
    if (nameEl) nameEl.textContent = name || '-';

    const statusEl = card.querySelector('[data-profile-status]');
    if (statusEl) statusEl.textContent = instance.profile_status || 'WhatsApp Cloud API Oficial da Meta';

    const numEl = card.querySelector('[data-profile-number]');
    if (numEl) numEl.textContent = formatPhone(instance.profile_number || instance.phone || '');

    const phoneIdEl = card.querySelector('[data-profile-phone-id]');
    if (phoneIdEl) phoneIdEl.textContent = instance.meta_phone_number_id || 'Padrão Global';

    const wabaIdEl = card.querySelector('[data-profile-waba-id]');
    if (wabaIdEl) wabaIdEl.textContent = instance.meta_waba_id || 'Padrão Global';

    const qualityEl = card.querySelector('[data-profile-quality]');
    if (qualityEl) qualityEl.innerHTML = `<i class="bi bi-shield-check me-1"></i>${escapeHtml(instance.quality_rating || 'GREEN')}`;

    const syncEl = card.querySelector('[data-profile-sync]');
    if (syncEl) syncEl.textContent = instance.last_profile_sync_at || '-';

    const badge = card.querySelector('[data-status-badge]');
    if (badge) {
        const color = statusColors[instance.status] || 'secondary';
        badge.className = `badge text-bg-${color}`;
        badge.textContent = statusLabels[instance.status] || instance.status || 'Desconhecido';
    }

    const discBtn = card.querySelector('.btn-disconnect');
    if (discBtn) {
        discBtn.classList.toggle('d-none', instance.status !== 'connected');
    }

    const errorBox = card.querySelector('[data-last-error]');
    if (errorBox) {
        if (instance.last_error) {
            errorBox.className = 'alert alert-warning py-2 px-3 small mb-3';
            errorBox.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>${escapeHtml(instance.last_error)}`;
        } else {
            errorBox.className = 'd-none';
            errorBox.innerHTML = '';
        }
    }
}

function insertInstanceCard(data) {
    if (!data?.html) return;
    const body = document.querySelector('.premium-panel__body');
    if (!body) return;

    body.querySelector('.empty-state')?.remove();
    let grid = body.querySelector('.instance-grid');
    if (!grid) {
        grid = document.createElement('div');
        grid.className = 'instance-grid';
        body.appendChild(grid);
    }

    const tmp = document.createElement('div');
    tmp.innerHTML = data.html.trim();
    const card = tmp.firstElementChild;
    if (!card) return;

    grid.prepend(card);
    if (data.config?.id) {
        instanceConfig[data.config.id] = data.config;
    }
    bindDynamicInstanceActions(card);
}

function replaceInstanceCard(data) {
    if (!data?.html || !data?.instance?.id) {
        applyInstancePayload(data?.instance);
        return;
    }

    const current = document.querySelector(`[data-instance-card="${data.instance.id}"]`);
    if (!current) {
        insertInstanceCard(data);
        return;
    }

    const tmp = document.createElement('div');
    tmp.innerHTML = data.html.trim();
    const next = tmp.firstElementChild;
    if (!next) return;

    current.replaceWith(next);
    if (data.config?.id) {
        instanceConfig[data.config.id] = data.config;
    }
    bindDynamicInstanceActions(next);
}

function updateInstanceCounts(counts) {
    if (!counts) return;
    const total = Number(counts.total || 0);
    const connected = Number(counts.connected || 0);
    const limit = counts.included_limit ?? '{{ $includedLimit }}';
    const extra = Number(counts.billable_extra || 0);

    document.querySelector('[data-instance-usage]')?.replaceChildren(document.createTextNode(`${total} / ${limit}`));
    document.querySelector('[data-instance-count-badge]')?.replaceChildren(document.createTextNode(`${total} instância(s)`));

    const connectedEl = document.querySelector('[data-instance-connected-count]');
    if (connectedEl) {
        connectedEl.textContent = `${connected} conectada(s)${extra ? ` - ${extra} extra(s)` : ''}`;
    }
}

function ensureInstancesEmptyState() {
    const body = document.querySelector('.premium-panel__body');
    const grid = body?.querySelector('.instance-grid');
    if (!body || (grid && grid.querySelector('[data-instance-card]'))) return;
    grid?.remove();
    if (body.querySelector('.empty-state')) return;
    body.insertAdjacentHTML('beforeend', `
        <div class="empty-state">
            <i class="bi bi-phone"></i>
            <h3>Nenhuma instância cadastrada</h3>
            <p>Crie a primeira instância para conectar à WhatsApp Cloud API Oficial da Meta e configurar suas credenciais.</p>
            <button class="btn btn-primary btn-sm empty-state__action" data-bs-toggle="modal" data-bs-target="#modalCreateInstance">
                <i class="bi bi-plus-lg me-1"></i> Criar instância
            </button>
        </div>
    `);
}

function showQrCode(qr) {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQR')).show();
}

async function openQrModalForInstance(id) {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQR')).show();
}

function bindDynamicInstanceActions(root) {
    root.querySelectorAll('.btn-qr').forEach(btn => {
        btn.addEventListener('click', () => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQR')).show();
        });
    });

    root.querySelectorAll('.btn-sync').forEach(btn => {
        btn.addEventListener('click', async () => {
            setLoading(btn, true);
            try {
                const r = await fetch(`${instancesBaseUrl}/${btn.dataset.id}/sincronizar`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.ok) {
                    applyInstancePayload(d.instance);
                    notify('success', d.connected ? 'Perfil sincronizado e conectado.' : 'Perfil sincronizado. A instância ainda não está conectada.');
                } else {
                    applyInstancePayload(d.instance);
                    notify('error', d.error || 'Erro ao sincronizar.');
                }
            } catch (e) {
                notify('error', 'Erro de conexão.');
            } finally {
                setLoading(btn, false);
            }
        });
    });

    root.querySelectorAll('.btn-config').forEach(btn => {
        btn.addEventListener('click', () => openConfigModal(btn.dataset.id));
    });

    root.querySelectorAll('.btn-disconnect').forEach(btn => {
        btn.addEventListener('click', async () => {
            const result = await Swal.fire({
                title: 'Desconectar instancia?',
                text: 'A instancia sera desconectada do WhatsApp. Voce precisara escanear o QR Code novamente para reconectar.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, desconectar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
            });
            if (!result.isConfirmed) return;

            setLoading(btn, true);
            try {
                const r = await fetch(`${instancesBaseUrl}/${btn.dataset.id}/desconectar`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.ok) {
                    notify('success', 'Instancia desconectada.');
                    btn.classList.add('d-none');
                    const card = btn.closest('[data-instance-card]');
                    if (card) {
                        const badge = card.querySelector('[data-status-badge]');
                        if (badge) { badge.className = 'badge text-bg-secondary'; badge.textContent = 'Desconectada'; }
                    }
                } else {
                    notify('error', d.error || 'Erro ao desconectar.');
                }
            } catch (e) {
                notify('error', 'Erro de conexao.');
            } finally {
                setLoading(btn, false);
            }
        });
    });

    root.querySelectorAll('.btn-rename').forEach(btn => {
        btn.addEventListener('click', async () => {
            const result = await Swal.fire({
                title: 'Renomear instância',
                input: 'text',
                inputValue: btn.dataset.current || '',
                inputAttributes: { maxlength: 60 },
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar',
                background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
                inputValidator: value => !value ? 'Informe um nome.' : undefined,
            });
            if (!result.isConfirmed) return;

            try {
                const r = await fetch(`${instancesBaseUrl}/${btn.dataset.id}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: result.value })
                });
                const d = await r.json();
                if (d.ok) {
                    notify('success', 'Instância renomeada.');
                    replaceInstanceCard(d);
                } else {
                    notify('error', d.error || 'Erro ao renomear.');
                }
            } catch (e) {
                notify('error', 'Erro de conexão.');
            }
        });
    });

    root.querySelectorAll('.btn-delete-instance').forEach(btn => {
        btn.addEventListener('click', async () => {
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Excluir instância?',
                text: `A instância ${btn.dataset.name || ''} sera removida do sistema.`,
                showCancelButton: true,
                confirmButtonText: 'Excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
            });
            if (!result.isConfirmed) return;

            setLoading(btn, true);
            try {
                const r = await fetch(`${instancesBaseUrl}/${btn.dataset.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.ok) {
                    notify('success', 'Instância excluída.');
                    document.querySelector(`[data-instance-card="${btn.dataset.id}"]`)?.remove();
                    updateInstanceCounts(d.counts);
                    ensureInstancesEmptyState();
                } else {
                    notify('error', d.error || 'Erro ao excluir instância.');
                }
            } catch (e) {
                notify('error', 'Erro de conexão.');
            } finally {
                setLoading(btn, false);
            }
        });
    });
}

function formatPhone(value) {
    const digits = String(value || '').replace(/\D/g, '').replace(/^55/, '');
    if (digits.length === 11) return `(${digits.slice(0,2)}) ${digits.slice(2,7)}-${digits.slice(7)}`;
    if (digits.length === 10) return `(${digits.slice(0,2)}) ${digits.slice(2,6)}-${digits.slice(6)}`;
    return value || '-';
}

function formatMoney(value) {
    const number = Number(value || 0);
    return number.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
    return escapeHtml(value).replace(/`/g, '&#096;');
}

function openSafetyModal() {
    const modalEl = document.getElementById('modalSafetySettings');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    fetch('{{ route("tenant.safety.limits.get") }}', {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const mode = data.mode || 'safe';
            const radio = document.getElementById(`mode-${mode}`);
            if (radio) {
                radio.checked = true;
                onSafetyModeChange(mode);
            }
            const inputLimit = document.getElementById('custom-daily-limit');
            if (inputLimit && data.limit_per_day) {
                inputLimit.value = data.limit_per_day;
            }
            const checkHours = document.getElementById('custom-respect-hours');
            if (checkHours && typeof data.respect_business_hours !== 'undefined') {
                checkHours.checked = Boolean(data.respect_business_hours);
            }
            const checkRisk = document.getElementById('risk-acknowledged-check');
            if (checkRisk) {
                checkRisk.checked = Boolean(data.risk_accepted);
            }
        }
    })
    .catch(() => {});
}

function onSafetyModeChange(mode) {
    const panel = document.getElementById('accelerated-settings-panel');
    if (panel) {
        if (mode === 'accelerated') {
            panel.classList.remove('d-none');
        } else {
            panel.classList.add('d-none');
        }
    }

    document.querySelectorAll('.safety-mode-card').forEach(card => {
        card.style.borderColor = 'var(--bs-border-color)';
        card.style.boxShadow = 'none';
    });

    const activeCard = document.getElementById(`card-mode-${mode}`);
    if (activeCard) {
        const color = mode === 'accelerated' ? '#dc3545' : (mode === 'moderate' ? '#0d6efd' : '#198754');
        activeCard.style.borderColor = color;
        activeCard.style.boxShadow = `0 0 0 1px ${color}`;
    }
}

async function saveSafetySettings() {
    const selectedModeInput = document.querySelector('input[name="safety_mode"]:checked');
    if (!selectedModeInput) {
        Swal.fire('Atenção', 'Selecione um perfil de envio.', 'warning');
        return;
    }

    const mode = selectedModeInput.value;
    const customDailyLimit = document.getElementById('custom-daily-limit')?.value || null;
    const respectHours = document.getElementById('custom-respect-hours')?.checked ?? true;
    const riskAcknowledged = document.getElementById('risk-acknowledged-check')?.checked ?? false;

    if (mode === 'accelerated') {
        if (!riskAcknowledged) {
            Swal.fire({
                title: 'Termo Obrigatório',
                text: 'Para ativar o Modo Acelerado, você deve marcar a caixa de ciência declarando assumir os riscos de banimento por sua conta e risco.',
                icon: 'warning',
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        const confirmResult = await Swal.fire({
            title: '⚠️ Confirmar Modo Acelerado?',
            html: '<div class="text-start small"><p><strong>ATENÇÃO:</strong> Disparos em ritmo acelerado violam as políticas e heurísticas da Meta. Há risco real de <strong>bloqueio imediato ou banimento irreversível</strong> do seu WhatsApp.</p><p class="mb-0 text-danger fw-bold">Deseja realmente ativar este modo por sua conta e risco?</p></div>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, assumo o risco e confirmo',
            cancelButtonText: 'Cancelar'
        });

        if (!confirmResult.isConfirmed) return;
    } else {
        const confirmResult = await Swal.fire({
            title: 'Aplicar Configurações?',
            text: 'As novas diretrizes de cadência e anti-ban serão aplicadas imediatamente a todas as suas instâncias.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, aplicar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirmResult.isConfirmed) return;
    }

    const saveBtn = document.getElementById('btn-save-safety-settings');
    const originalHtml = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Salvando...';
    }

    try {
        const response = await fetch('{{ route("tenant.safety.limits") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                mode: mode,
                limit_per_day: mode === 'accelerated' ? parseInt(customDailyLimit, 10) : null,
                respect_business_hours: respectHours,
                warmup_enabled: true,
                risk_acknowledged: riskAcknowledged
            })
        });

        const data = await response.json();

        if (response.ok && data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalSafetySettings'))?.hide();

            const titleEl = document.getElementById('safety-banner-title');
            const badgeEl = document.getElementById('safety-banner-badge');
            const detailsEl = document.getElementById('safety-banner-details');
            const iconEl = document.getElementById('safety-banner-icon');

            if (titleEl) titleEl.textContent = data.mode_label || mode;
            if (badgeEl) {
                badgeEl.className = mode === 'accelerated' ? 'badge bg-danger' : (mode === 'moderate' ? 'badge bg-primary' : 'badge bg-success');
                badgeEl.textContent = mode === 'accelerated' ? 'Alto Risco' : (mode === 'moderate' ? 'Equilibrado' : 'Proteção Ativa');
            }
            if (iconEl) {
                iconEl.className = mode === 'accelerated' ? 'bi bi-exclamation-triangle-fill fs-5' : 'bi bi-shield-check fs-5';
            }
            if (detailsEl) {
                const hoursLabel = respectHours ? '08:00 às 20:00' : '24 horas';
                detailsEl.innerHTML = `Limite: <strong>${data.limit_per_day} msgs/dia</strong> &bull; Intervalo humano: <strong>${data.human_delay_min_seconds}-${data.human_delay_max_seconds}s</strong> &bull; Janela comercial: <strong>${hoursLabel}</strong>`;
            }

            if (typeof window.toast !== 'undefined') {
                window.toast.success(data.message || 'Configurações de cadência atualizadas.');
            } else {
                Swal.fire({
                    icon: 'success',
                    title: data.message || 'Configurações atualizadas com sucesso.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        } else {
            Swal.fire('Erro', data.message || 'Não foi possível salvar as configurações.', 'error');
        }
    } catch (err) {
        Swal.fire('Erro', 'Ocorreu um erro de conexão ao salvar as configurações.', 'error');
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalHtml;
        }
    }
}
</script>
@endpush
