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
    $defaultWebhookEvents = [
        'APPLICATION_STARTUP',
        'QRCODE_UPDATED',
        'CONNECTION_UPDATE',
        'MESSAGES_UPSERT',
        'MESSAGES_UPDATE',
        'MESSAGES_DELETE',
        'SEND_MESSAGE',
        'CONTACTS_UPSERT',
        'CONTACTS_UPDATE',
        'PRESENCE_UPDATE',
        'CHATS_UPDATE',
        'GROUPS_UPSERT',
        'GROUP_UPDATE',
        'GROUP_PARTICIPANTS_UPDATE',
        'CALL',
        'NEW_JWT_TOKEN',
    ];
    $recommendedWebhookEvents = [
        'QRCODE_UPDATED',
        'CONNECTION_UPDATE',
        'MESSAGES_UPSERT',
        'MESSAGES_UPDATE',
        'SEND_MESSAGE',
        'CALL',
    ];
    $webhookMeta = [
        'webhook_enabled' => ['label' => 'Ativar webhook', 'description' => 'Liga o envio de eventos desta instância para o seu sistema.'],
        'webhook_by_events' => ['label' => 'Separar por eventos', 'description' => 'Envia apenas os eventos marcados abaixo para reduzir ruído e tráfego desnecessário.'],
        'webhook_base64' => ['label' => 'Enviar mídia em base64', 'description' => 'Inclui arquivos no payload em base64. Normalmente deve ficar desligado para evitar consumo alto.'],
    ];
    $eventMeta = [
        'APPLICATION_STARTUP' => ['label' => 'Inicialização da aplicação', 'description' => 'Evento técnico disparado quando a aplicação inicia. Normalmente não é necessário no painel.'],
        'QRCODE_UPDATED' => ['label' => 'QR Code atualizado', 'description' => 'Atualiza o QR Code de conexão no painel.'],
        'CONNECTION_UPDATE' => ['label' => 'Status da conexão', 'description' => 'Informa quando a instância conecta, desconecta ou muda de estado.'],
        'MESSAGES_UPSERT' => ['label' => 'Nova mensagem recebida', 'description' => 'Recebe novas mensagens e alimenta chat, atendimento e automações.'],
        'MESSAGES_UPDATE' => ['label' => 'Atualização de mensagem', 'description' => 'Atualiza status de mensagens, como entregue, lida ou alterada.'],
        'MESSAGES_DELETE' => ['label' => 'Mensagem apagada', 'description' => 'Informa quando uma mensagem foi apagada. Desativado por padrão.'],
        'SEND_MESSAGE' => ['label' => 'Mensagem enviada', 'description' => 'Confirma mensagens enviadas pela instância.'],
        'CONTACTS_UPSERT' => ['label' => 'Novo contato', 'description' => 'Recebe contatos criados ou sincronizados. Pode gerar volume alto.'],
        'CONTACTS_UPDATE' => ['label' => 'Contato atualizado', 'description' => 'Recebe alterações de contatos. Pode gerar volume alto.'],
        'PRESENCE_UPDATE' => ['label' => 'Presença online/digitando', 'description' => 'Recebe eventos de digitando e online. Pode gerar muito ruído.'],
        'CHATS_UPDATE' => ['label' => 'Atualização de conversas', 'description' => 'Recebe alterações em chats. Pode gerar volume alto.'],
        'GROUPS_UPSERT' => ['label' => 'Novo grupo', 'description' => 'Use apenas se o sistema trabalhar com grupos.'],
        'GROUP_UPDATE' => ['label' => 'Grupo atualizado', 'description' => 'Recebe mudanças de nome, descrição ou configurações de grupos.'],
        'GROUP_PARTICIPANTS_UPDATE' => ['label' => 'Participantes do grupo', 'description' => 'Recebe entrada, saída e alteração de participantes em grupos.'],
        'CALL' => ['label' => 'Chamadas recebidas', 'description' => 'Permite detectar chamadas e rejeitar automaticamente.'],
        'NEW_JWT_TOKEN' => ['label' => 'Novo token JWT', 'description' => 'Evento técnico de token. Normalmente não é usado no painel.'],
    ];
    $behaviorMeta = [
        'reject_call' => ['label' => 'Rejeitar chamadas', 'description' => 'Rejeita chamadas recebidas automaticamente e evita interferência na automação.'],
        'groups_ignore' => ['label' => 'Ignorar grupos', 'description' => 'Evita que o sistema responda mensagens em grupos.'],
        'always_online' => ['label' => 'Sempre online', 'description' => 'Mantém a instância como online. Fica desligado por padrão para não parecer artificial.'],
        'read_messages' => ['label' => 'Marcar mensagens como lidas', 'description' => 'Marca mensagens recebidas como lidas. Pode atrapalhar atendimento humano.'],
        'ignore_status' => ['label' => 'Ignore Status', 'description' => 'Ignora atualizacoes de status (stories) no WhatsApp.'],
        'sync_full_history' => ['label' => 'Sincronizar histórico completo', 'description' => 'Sincroniza histórico antigo. Pode causar lentidão e alto consumo.'],
        'msg_call' => ['label' => 'Mensagem ao rejeitar chamada', 'description' => 'Mensagem enviada quando uma chamada é recusada automaticamente.'],
    ];
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
    $instanceConfig = $instances->mapWithKeys(function ($inst) use ($defaultWebhookEvents) {
        $settings = data_get($inst->meta ?? [], 'evolution_settings', []);
        return [$inst->id => [
            'id' => $inst->id,
            'api_base_url' => $inst->api_base_url,
            'has_api_key' => filled($inst->api_key),
            'webhook_enabled' => (bool) $inst->webhook_enabled,
            'webhook_by_events' => $inst->webhook_by_events ?? true,
            'webhook_base64' => (bool) $inst->webhook_base64,
            'webhook_events' => $inst->webhook_events ?: $defaultWebhookEvents,
            'webhook_url' => $inst->secure_webhook_url,
            'event_producers' => data_get($inst->meta ?? [], 'evolution_event_producers', [
                'rabbitmq' => 'default',
                'websocket' => 'default',
                'nats' => 'default',
            ]),
            'settings' => [
                'reject_call' => (bool) data_get($settings, 'rejectCall', data_get($settings, 'reject_call', false)),
                'msg_call' => (string) data_get($settings, 'msgCall', data_get($settings, 'msg_call', '')),
                'groups_ignore' => (bool) data_get($settings, 'groupsIgnore', data_get($settings, 'groups_ignore', false)),
                'always_online' => (bool) data_get($settings, 'alwaysOnline', data_get($settings, 'always_online', false)),
                'read_messages' => (bool) data_get($settings, 'readMessages', data_get($settings, 'read_messages', false)),
                'ignore_status' => (bool) data_get($settings, 'ignoreStatus', ! (bool) data_get($settings, 'readStatus', data_get($settings, 'read_status', false))),
                'read_status' => (bool) data_get($settings, 'readStatus', data_get($settings, 'read_status', false)),
                'sync_full_history' => (bool) data_get($settings, 'syncFullHistory', data_get($settings, 'sync_full_history', false)),
            ],
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
                    @php
                        $status = $inst->status ?? 'creating';
                        $profileName = $inst->whatsapp_profile_name;
                        $displayName = $inst->display_name ?: $profileName;
                        $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
                        $number = $inst->profile_number ?: $inst->phone;
                        $maskedNumber = $number ? Format::phone(preg_replace('/^55/', '', $number)) : '-';
                        $settings = data_get($inst->meta ?? [], 'evolution_settings', []);
                    @endphp
                    <article class="instance-card" data-instance-card="{{ $inst->id }}">
                        <div class="instance-card__top">
                            <div class="instance-avatar" data-avatar-wrap>
                                @if($inst->profile_avatar_url)
                                    <img src="{{ $inst->profile_avatar_url }}" alt="{{ $displayName }}" referrerpolicy="no-referrer" onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('d-none');" data-profile-avatar>
                                    <span data-profile-initial class="d-none">{{ $initial }}</span>
                                @else
                                    <span data-profile-initial>{{ $initial }}</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h3 class="instance-card__title mb-0" data-profile-name>{{ $displayName }}</h3>
                                    @if($inst->is_default)
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Padrão</span>
                                    @endif
                                @if($inst->is_extra_billable)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">Extra {{ Format::money($inst->recurring_amount ?? 0) }}/mes</span>
                                @endif
                                <span class="badge bg-{{ $inst->profile_is_business ? 'info' : 'secondary' }}-subtle text-{{ $inst->profile_is_business ? 'info' : 'secondary' }} border border-{{ $inst->profile_is_business ? 'info' : 'secondary' }}-subtle" data-profile-type>
                                    {{ $inst->profile_is_business ? 'Business' : 'Pessoal' }}
                                </span>
                                </div>
                                <div class="instance-card__code"><code>{{ $inst->instance_name }}</code></div>
                                <p class="instance-card__slogan" data-profile-status>{{ $inst->profile_status ?: 'Slogan/status ainda não sincronizado.' }}</p>
                            </div>
                            <span class="badge text-bg-{{ $statusColors[$status] ?? 'secondary' }}" data-status-badge>
                                {{ $statusLabels[$status] ?? ucfirst((string) $status) }}
                            </span>
                        </div>

                        <div class="instance-card__meta">
                            <div>
                                <span>Número</span>
                                <strong data-profile-number>{{ $maskedNumber }}</strong>
                            </div>
                        <div>
                            <span>JID</span>
                            <strong class="text-truncate" data-profile-jid>{{ $inst->profile_jid ?: '-' }}</strong>
                        </div>
                        <div>
                            <span>LID</span>
                            <strong class="text-truncate" data-profile-lid>{{ $inst->profile_lid ?: '-' }}</strong>
                        </div>
                        <div>
                            <span>Dispositivos</span>
                            <strong data-profile-devices>{{ is_array($inst->profile_devices) ? count($inst->profile_devices) : 0 }}</strong>
                        </div>
                        <div>
                            <span>Token</span>
                            <strong>{{ filled($inst->api_key) ? 'Configurado' : 'Pendente' }}</strong>
                            </div>
                            <div>
                                <span>Última sincronização</span>
                                <strong data-profile-sync>{{ $inst->last_profile_sync_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                            </div>
                        </div>

                        @if($inst->last_error)
                            <div class="alert alert-warning py-2 px-3 small mb-3" data-last-error>
                                <i class="bi bi-exclamation-triangle me-1"></i>{{ $inst->last_error }}
                            </div>
                        @else
                            <div class="d-none" data-last-error></div>
                        @endif

                        <div class="instance-card__actions">
                            <button class="btn btn-outline-success btn-sm btn-qr" data-id="{{ $inst->id }}">
                                <i class="bi bi-qr-code me-1"></i> QR Code
                            </button>
                            <button class="btn btn-outline-primary btn-sm btn-sync" data-id="{{ $inst->id }}">
                                <i class="bi bi-arrow-repeat me-1"></i> Sincronizar
                            </button>
                            <button class="btn btn-outline-danger btn-sm btn-disconnect {{ $inst->status !== 'connected' ? 'd-none' : '' }}" data-id="{{ $inst->id }}">
                                <i class="bi bi-wifi-off me-1"></i> Desconectar
                            </button>
                            <button class="btn btn-outline-secondary btn-sm btn-config" data-id="{{ $inst->id }}">
                                <i class="bi bi-sliders me-1"></i> Configurar API
                            </button>
                            @unless($inst->is_default)
                                <button class="btn btn-outline-dark btn-sm btn-rename" data-id="{{ $inst->id }}" data-current="{{ $inst->display_name }}">
                                    <i class="bi bi-pencil me-1"></i> Renomear
                                </button>
                            @endunless
                            <button class="btn btn-outline-danger btn-sm btn-delete-instance" data-id="{{ $inst->id }}" data-name="{{ $displayName }}">
                                <i class="bi bi-trash me-1"></i> Excluir
                            </button>
                        </div>
                    </article>
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
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-qr-code me-2"></i>QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qr-loading" class="py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Gerando QR Code...</p>
                </div>
                <div id="qr-content" class="d-none">
                    <img id="qr-image" src="" alt="QR Code" class="img-fluid border rounded" style="max-width:250px;">
                    <p class="mt-2 small text-muted">Escaneie com o WhatsApp e depois clique em sincronizar.</p>
                </div>
                <div id="qr-error" class="d-none">
                    <i class="bi bi-exclamation-triangle text-warning display-4"></i>
                    <p class="mt-2 text-muted">Nao foi possivel gerar o QR Code.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalInstanceConfig" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title"><i class="bi bi-sliders me-2"></i>Configuração WhatsApp Meta</h5>
                    <small class="text-muted">Ajustes aplicados somente nesta instância.</small>
                    <div class="config-sync-indicator mt-2 d-none" id="config-sync-indicator">
                        <i class="bi bi-cloud-check me-1"></i>
                        <span id="config-sync-text">Sincronizado com WhatsApp Oficial agora</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frm-instance-config">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="config-section h-100">
                                <span class="tenant-premium-kicker">Conexão</span>
                                <h6>Credenciais da API</h6>
                                <div class="mb-3">
                                    <label class="form-label">URL da API</label>
                                    <input type="url" class="form-control" name="api_base_url" placeholder="Usar URL global do sistema">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Token da instância</label>
                                    <input type="password" class="form-control" name="api_key" autocomplete="new-password" placeholder="Token atual será mantido">
                                    <div class="form-text" id="token-status">Informe apenas quando quiser cadastrar ou trocar o token.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="config-section h-100">
                                <span class="tenant-premium-kicker">Webhook seguro</span>
                                <h6>Recebimento de eventos</h6>
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" name="webhook_url_display" readonly>
                                    <button class="btn btn-outline-secondary" type="button" id="btn-copy-webhook"><i class="bi bi-clipboard"></i></button>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="webhook_enabled" id="webhook_enabled">
                                            <label class="form-check-label d-inline-flex align-items-center gap-2" for="webhook_enabled">
                                                <span>{{ $webhookMeta['webhook_enabled']['label'] }}</span>
                                                <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $webhookMeta['webhook_enabled']['description'] }}" aria-label="Ajuda sobre {{ strtolower($webhookMeta['webhook_enabled']['label']) }}">
                                                    <i class="bi bi-question-circle"></i>
                                                </button>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="webhook_by_events" id="webhook_by_events">
                                            <label class="form-check-label d-inline-flex align-items-center gap-2" for="webhook_by_events">
                                                <span>{{ $webhookMeta['webhook_by_events']['label'] }}</span>
                                                <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $webhookMeta['webhook_by_events']['description'] }}" aria-label="Ajuda sobre {{ strtolower($webhookMeta['webhook_by_events']['label']) }}">
                                                    <i class="bi bi-question-circle"></i>
                                                </button>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="webhook_base64" id="webhook_base64">
                                            <label class="form-check-label d-inline-flex align-items-center gap-2" for="webhook_base64">
                                                <span>{{ $webhookMeta['webhook_base64']['label'] }}</span>
                                                <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $webhookMeta['webhook_base64']['description'] }}" aria-label="Ajuda sobre {{ strtolower($webhookMeta['webhook_base64']['label']) }}">
                                                    <i class="bi bi-question-circle"></i>
                                                </button>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-2 mt-3">
                                    <div class="col-md-4">
                                        <label class="form-label">RabbitMQ</label>
                                        <select class="form-select" name="event_producer_rabbitmq">
                                            <option value="default">Padrao do sistema</option>
                                            <option value="enabled">Ativar</option>
                                            <option value="disabled">Desativar</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">WebSocket</label>
                                        <select class="form-select" name="event_producer_websocket">
                                            <option value="default">Padrao do sistema</option>
                                            <option value="enabled">Ativar</option>
                                            <option value="disabled">Desativar</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">NATS</label>
                                        <select class="form-select" name="event_producer_nats">
                                            <option value="default">Padrao do sistema</option>
                                            <option value="enabled">Ativar</option>
                                            <option value="disabled">Desativar</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="config-section mt-3">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <span class="tenant-premium-kicker">Eventos</span>
                                <h6 class="mb-1">Eventos do Webhook</h6>
                                <p class="small text-muted mb-0">Passe o mouse no ícone de ajuda para entender cada evento sem poluir a visualização.</p>
                            </div>
                            <button type="button" class="btn btn-outline-success btn-sm" id="btn-apply-recommended-config">
                                <i class="bi bi-magic me-1"></i> Aplicar configuração recomendada
                            </button>
                        </div>
                        <div class="event-grid">
                            @foreach($defaultWebhookEvents as $event)
                                <label class="event-pill event-pill--detail">
                                    <input type="checkbox" name="webhook_events[]" value="{{ $event }}">
                                    <span class="event-pill__body">
                                        <span class="event-pill__meta">
                                            <strong>{{ $eventMeta[$event]['label'] ?? $event }}</strong>
                                            <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $eventMeta[$event]['description'] ?? 'Evento técnico do Webhook.' }}" aria-label="Ajuda sobre {{ strtolower($eventMeta[$event]['label'] ?? $event) }}">
                                                <i class="bi bi-question-circle"></i>
                                            </button>
                                        </span>
                                        <code>{{ $event }}</code>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="config-section mt-3">
                        <span class="tenant-premium-kicker">Comportamento</span>
                        <h6>Configurações da instância</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="form-check form-switch config-switch-card">
                                    <input class="form-check-input" type="checkbox" name="reject_call" id="reject_call">
                                    <label class="form-check-label" for="reject_call">
                                        <span class="config-switch-card__header">
                                            <strong>{{ $behaviorMeta['reject_call']['label'] }}</strong>
                                            <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $behaviorMeta['reject_call']['description'] }}" aria-label="Ajuda sobre {{ strtolower($behaviorMeta['reject_call']['label']) }}">
                                                <i class="bi bi-question-circle"></i>
                                            </button>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch config-switch-card">
                                    <input class="form-check-input" type="checkbox" name="groups_ignore" id="groups_ignore">
                                    <label class="form-check-label" for="groups_ignore">
                                        <span class="config-switch-card__header">
                                            <strong>{{ $behaviorMeta['groups_ignore']['label'] }}</strong>
                                            <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $behaviorMeta['groups_ignore']['description'] }}" aria-label="Ajuda sobre {{ strtolower($behaviorMeta['groups_ignore']['label']) }}">
                                                <i class="bi bi-question-circle"></i>
                                            </button>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch config-switch-card">
                                    <input class="form-check-input" type="checkbox" name="always_online" id="always_online">
                                    <label class="form-check-label" for="always_online">
                                        <span class="config-switch-card__header">
                                            <strong>{{ $behaviorMeta['always_online']['label'] }}</strong>
                                            <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $behaviorMeta['always_online']['description'] }}" aria-label="Ajuda sobre {{ strtolower($behaviorMeta['always_online']['label']) }}">
                                                <i class="bi bi-question-circle"></i>
                                            </button>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch config-switch-card">
                                    <input class="form-check-input" type="checkbox" name="read_messages" id="read_messages">
                                    <label class="form-check-label" for="read_messages">
                                        <span class="config-switch-card__header">
                                            <strong>{{ $behaviorMeta['read_messages']['label'] }}</strong>
                                            <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $behaviorMeta['read_messages']['description'] }}" aria-label="Ajuda sobre {{ strtolower($behaviorMeta['read_messages']['label']) }}">
                                                <i class="bi bi-question-circle"></i>
                                            </button>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch config-switch-card">
                                    <input class="form-check-input" type="checkbox" name="ignore_status" id="ignore_status">
                                    <label class="form-check-label" for="ignore_status">
                                        <span class="config-switch-card__header">
                                            <strong>{{ $behaviorMeta['ignore_status']['label'] }}</strong>
                                            <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $behaviorMeta['ignore_status']['description'] }}" aria-label="Ajuda sobre {{ strtolower($behaviorMeta['ignore_status']['label']) }}">
                                                <i class="bi bi-question-circle"></i>
                                            </button>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch config-switch-card">
                                    <input class="form-check-input" type="checkbox" name="sync_full_history" id="sync_full_history">
                                    <label class="form-check-label" for="sync_full_history">
                                        <span class="config-switch-card__header">
                                            <strong>{{ $behaviorMeta['sync_full_history']['label'] }}</strong>
                                            <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $behaviorMeta['sync_full_history']['description'] }}" aria-label="Ajuda sobre {{ strtolower($behaviorMeta['sync_full_history']['label']) }}">
                                                <i class="bi bi-question-circle"></i>
                                            </button>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label d-flex align-items-center gap-2">
                                    <strong>{{ $behaviorMeta['msg_call']['label'] }}</strong>
                                    <button type="button" class="config-help-button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $behaviorMeta['msg_call']['description'] }}" aria-label="Ajuda sobre {{ strtolower($behaviorMeta['msg_call']['label']) }}">
                                        <i class="bi bi-question-circle"></i>
                                    </button>
                                </label>
                                <input type="text" class="form-control" name="msg_call" maxlength="500" placeholder="Ex.: Atendimento somente por mensagem.">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="btn-save-config">
                    <i class="bi bi-cloud-check me-1"></i> Salvar e aplicar
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
const defaultWebhookEvents = @json($defaultWebhookEvents);
const recommendedWebhookEvents = @json($recommendedWebhookEvents);
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
            if (d.qr) {
                showQrCode(d.qr);
            } else if (d.instance?.id) {
                await openQrModalForInstance(d.instance.id);
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
    btn.addEventListener('click', async () => {
        if (isInstanceConnected(btn)) {
            await Swal.fire({
                icon: 'info',
                title: 'Instancia ja em uso',
                text: 'Esta instancia ja esta conectada ao WhatsApp. Desconecte-a primeiro se quiser trocar de numero.',
                confirmButtonText: 'OK',
                background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
            });
            return;
        }

        await openQrModalForInstance(btn.dataset.id);
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
    form.api_base_url.value = cfg.api_base_url || '';
    form.api_key.value = '';
    document.getElementById('token-status').textContent = cfg.has_api_key ? 'Token já configurado. Informe apenas se quiser trocar.' : 'Token ainda pendente para esta instância.';
    form.webhook_url_display.value = cfg.webhook_url || '';
    form.webhook_enabled.checked = !!cfg.webhook_enabled;
    form.webhook_by_events.checked = cfg.webhook_by_events !== false;
    form.webhook_base64.checked = !!cfg.webhook_base64;
    form.event_producer_rabbitmq.value = cfg.event_producers?.rabbitmq || 'default';
    form.event_producer_websocket.value = cfg.event_producers?.websocket || 'default';
    form.event_producer_nats.value = cfg.event_producers?.nats || 'default';

    const events = cfg.webhook_events?.length ? cfg.webhook_events : defaultWebhookEvents;
    form.querySelectorAll('input[name="webhook_events[]"]').forEach(input => {
        input.checked = events.includes(input.value);
    });

    const settings = cfg.settings || {};
    ['reject_call', 'groups_ignore', 'always_online', 'read_messages', 'ignore_status', 'sync_full_history'].forEach(name => {
        form[name].checked = !!settings[name];
    });
    form.msg_call.value = settings.msg_call || '';
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

document.getElementById('btn-apply-recommended-config')?.addEventListener('click', () => {
    const form = document.getElementById('frm-instance-config');
    if (!form) return;

    form.webhook_enabled.checked = true;
    form.webhook_by_events.checked = true;
    form.webhook_base64.checked = false;
    form.event_producer_rabbitmq.value = 'default';
    form.event_producer_websocket.value = 'default';
    form.event_producer_nats.value = 'default';

    form.querySelectorAll('input[name="webhook_events[]"]').forEach(input => {
        input.checked = recommendedWebhookEvents.includes(input.value);
    });

    form.reject_call.checked = true;
    form.groups_ignore.checked = true;
    form.always_online.checked = false;
    form.read_messages.checked = false;
    form.ignore_status.checked = true;
    form.sync_full_history.checked = false;
    form.msg_call.value = 'Atendimento somente por mensagem.';

    notify('success', 'Configuração recomendada aplicada na tela. Clique em Salvar e aplicar para persistir.');
});

document.getElementById('btn-save-config')?.addEventListener('click', async () => {
    const form = document.getElementById('frm-instance-config');
    const id = form.dataset.id;
    const btn = document.getElementById('btn-save-config');
    const events = [...form.querySelectorAll('input[name="webhook_events[]"]:checked')].map(input => input.value);
    const payload = {
        api_base_url: form.api_base_url.value,
        api_key: form.api_key.value,
        webhook_enabled: form.webhook_enabled.checked,
        webhook_by_events: form.webhook_by_events.checked,
        webhook_base64: form.webhook_base64.checked,
        webhook_events: events,
        event_producer_rabbitmq: form.event_producer_rabbitmq.value,
        event_producer_websocket: form.event_producer_websocket.value,
        event_producer_nats: form.event_producer_nats.value,
        reject_call: form.reject_call.checked,
        msg_call: form.msg_call.value,
        groups_ignore: form.groups_ignore.checked,
        always_online: form.always_online.checked,
        read_messages: form.read_messages.checked,
        ignore_status: form.ignore_status.checked,
        read_status: !form.ignore_status.checked,
        sync_full_history: form.sync_full_history.checked,
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
                event_producers: {
                    rabbitmq: payload.event_producer_rabbitmq,
                    websocket: payload.event_producer_websocket,
                    nats: payload.event_producer_nats,
                },
                webhook_url: d.webhook_url,
                has_api_key: instanceConfig[id]?.has_api_key || !!payload.api_key,
                last_synced_at: savedAt
            };
            form.webhook_url_display.value = d.webhook_url || form.webhook_url_display.value;
            updateConfigSyncIndicator(id, savedAt);
            notify(d.applied ? 'success' : 'error', d.message || 'Configuração salva.');
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
    if (instance.profile_avatar_url) {
        avatarWrap.innerHTML = `<img src="${escapeAttr(instance.profile_avatar_url)}" alt="${escapeAttr(name)}" referrerpolicy="no-referrer" onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('d-none');" data-profile-avatar><span data-profile-initial class="d-none">${escapeHtml((name || '-').charAt(0).toUpperCase())}</span>`;
    } else {
        avatarWrap.innerHTML = `<span data-profile-initial>${escapeHtml((name || '-').charAt(0).toUpperCase())}</span>`;
    }

    card.querySelector('[data-profile-name]').textContent = name || '-';
    card.querySelector('[data-profile-status]').textContent = instance.profile_status || 'Slogan/status ainda não sincronizado.';
    card.querySelector('[data-profile-number]').textContent = formatPhone(instance.profile_number || instance.phone || '');
    card.querySelector('[data-profile-jid]').textContent = instance.profile_jid || '-';
    card.querySelector('[data-profile-lid]').textContent = instance.profile_lid || '-';
    card.querySelector('[data-profile-devices]').textContent = Array.isArray(instance.profile_devices) ? instance.profile_devices.length : 0;
    card.querySelector('[data-profile-sync]').textContent = instance.last_profile_sync_at || '-';

    const typeBadge = card.querySelector('[data-profile-type]');
    if (typeBadge) {
        const tone = instance.profile_is_business ? 'info' : 'secondary';
        typeBadge.className = `badge bg-${tone}-subtle text-${tone} border border-${tone}-subtle`;
        typeBadge.textContent = instance.profile_is_business ? 'Business' : 'Pessoal';
    }

    const badge = card.querySelector('[data-status-badge]');
    const color = statusColors[instance.status] || 'secondary';
    badge.className = `badge text-bg-${color}`;
    badge.textContent = statusLabels[instance.status] || instance.status || 'Desconhecido';

    const discBtn = card.querySelector('.btn-disconnect');
    if (discBtn) {
        discBtn.classList.toggle('d-none', instance.status !== 'connected');
    }

    const errorBox = card.querySelector('[data-last-error]');
    if (instance.last_error) {
        errorBox.className = 'alert alert-warning py-2 px-3 small mb-3';
        errorBox.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>${escapeHtml(instance.last_error)}`;
    } else {
        errorBox.className = 'd-none';
        errorBox.innerHTML = '';
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
            <p>Crie a primeira instância para gerar QR Code, conectar o WhatsApp e sincronizar a identidade do perfil.</p>
            <button class="btn btn-primary btn-sm empty-state__action" data-bs-toggle="modal" data-bs-target="#modalCreateInstance">
                <i class="bi bi-plus-lg me-1"></i> Criar instância
            </button>
        </div>
    `);
}

function showQrCode(qr) {
    const modal = new bootstrap.Modal(document.getElementById('modalQR'));
    document.getElementById('qr-loading').classList.add('d-none');
    document.getElementById('qr-error').classList.add('d-none');
    document.getElementById('qr-content').classList.remove('d-none');
    document.getElementById('qr-image').src = normalizeQrSrc(qr);
    modal.show();
}

async function openQrModalForInstance(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalQR'));
    document.getElementById('qr-image').removeAttribute('src');
    document.getElementById('qr-loading').classList.remove('d-none');
    document.getElementById('qr-content').classList.add('d-none');
    document.getElementById('qr-error').classList.add('d-none');
    modal.show();

    try {
        const r = await fetch(`${instancesBaseUrl}/${id}/qr`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const d = await r.json();
        document.getElementById('qr-loading').classList.add('d-none');
        if (d.ok && d.qr) {
            document.getElementById('qr-image').src = normalizeQrSrc(d.qr);
            document.getElementById('qr-content').classList.remove('d-none');
        } else {
            modal.hide();
            if (d.connected) {
                notify('info', d.message || 'Esta instancia ja esta conectada.');
                return;
            }
            notify('error', d.error || d.message || 'Nao foi possivel gerar o QR Code.');
        }
    } catch (e) {
        modal.hide();
        document.getElementById('qr-loading').classList.add('d-none');
        notify('error', 'Erro de conexao ao gerar o QR Code.');
    }
}

function normalizeQrSrc(qr) {
    const value = String(qr || '').trim();
    if (!value || value.startsWith('data:image')) return value;
    return `data:image/png;base64,${value.replace(/\s+/g, '')}`;
}

function bindDynamicInstanceActions(root) {
    root.querySelectorAll('.btn-qr').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (isInstanceConnected(btn)) {
                await Swal.fire({
                    icon: 'info',
                    title: 'Instancia ja em uso',
                    text: 'Esta instancia ja esta conectada ao WhatsApp. Desconecte-a primeiro se quiser trocar de numero.',
                    confirmButtonText: 'OK',
                    background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                    color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
                });
                return;
            }

            await openQrModalForInstance(btn.dataset.id);
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
