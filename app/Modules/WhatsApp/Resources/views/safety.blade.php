
@extends('admin.layouts.app')

@section('title', 'Painel Anti-Ban WhatsApp')

@php
    $instanceCount = $instances->count();
    $protectedCount = $instances->filter(function ($instance) {
        $control = $instance->safetyControl;
        return $control?->sending_paused || $control?->sending_disabled || $control?->warmup_enabled || $control?->campaigns_blocked;
    })->count();

    $avgRisk = (float) ($stats['avg_risk'] ?? 0);
    $avgRiskLabel = number_format($avgRisk, 1, ',', '.');
    $riskTone = $avgRisk >= 81 ? 'danger' : ($avgRisk >= 51 ? 'warning' : 'success');
    $alerts = $stats['alerts'] ?? [];
    $warmupLimits = is_array($settings['warmup_daily_limits'] ?? null)
        ? $settings['warmup_daily_limits']
        : [3 => 30, 7 => 80, 15 => 150];

    $metricCards = [
        ['label' => 'Envios hoje', 'value' => $stats['today_sent'] ?? 0, 'icon' => 'bi-send-check', 'tone' => 'primary', 'help' => 'Volume liberado no dia. Use para acompanhar teto diario e aquecimento.'],
        ['label' => 'Entregues', 'value' => $stats['delivered'] ?? 0, 'icon' => 'bi-check2-all', 'tone' => 'success', 'help' => 'Mensagens confirmadas como entregues. Use para medir efetividade.'],
        ['label' => 'Respostas', 'value' => $stats['replied'] ?? 0, 'icon' => 'bi-chat-dots', 'tone' => 'info', 'help' => 'Mensagens recebidas. Use para medir engajamento da base.'],
        ['label' => 'Ultima hora', 'value' => $stats['last_hour_sent'] ?? 0, 'icon' => 'bi-clock-history', 'tone' => 'info', 'help' => 'Ritmo recente. Use para detectar picos de campanha.'],
        ['label' => 'Por minuto', 'value' => $stats['last_minute_sent'] ?? 0, 'icon' => 'bi-lightning-charge', 'tone' => 'secondary', 'help' => 'Pressao instantanea. Se subir demais, aumente delay ou reduza limite.'],
        ['label' => 'Falhas', 'value' => $stats['failed'] ?? 0, 'icon' => 'bi-x-circle', 'tone' => 'danger', 'help' => 'Mensagens com erro no periodo. Use para investigar instancia, token ou API.'],
        ['label' => 'Bloqueios', 'value' => $stats['blocked'] ?? 0, 'icon' => 'bi-shield-x', 'tone' => 'danger', 'help' => 'Envios barrados por regra. Use para identificar risco ou regra restritiva.'],
        ['label' => 'Score medio', 'value' => $avgRiskLabel, 'icon' => 'bi-activity', 'tone' => $riskTone, 'help' => 'Media de risco operacional. 0-20 baixo, 81+ critico.'],
    ];

    $fieldHelp = [
        'enabled' => ['icon' => 'bi-shield-check', 'when' => 'Use ligado em producao. Desligue apenas para diagnostico controlado.'],
        'timezone' => ['icon' => 'bi-clock', 'when' => 'Fuso usado em horario comercial, cooldowns e score por janela.'],
        'business_hours_enabled' => ['icon' => 'bi-calendar-check', 'when' => 'Bloqueia disparos fora do expediente e permite reagendamento.'],
        'business_hours_start' => ['icon' => 'bi-sunrise', 'when' => 'Hora inicial permitida para envios automaticos.'],
        'business_hours_end' => ['icon' => 'bi-sunset', 'when' => 'Hora final permitida antes de pausar a fila.'],
        'block_groups' => ['icon' => 'bi-people', 'when' => 'Impede envios em grupos. Recomendado para cobranca direta.'],
        'minimum_message_chars' => ['icon' => 'bi-chat-text', 'when' => 'Evita mensagens curtas demais e com cara de automacao.'],
        'stop_keywords' => ['icon' => 'bi-slash-circle', 'when' => 'Palavras que interrompem novos envios para o contato.'],
        'limit_per_minute' => ['icon' => 'bi-lightning-charge', 'when' => 'Controla picos instantaneos. Numeros novos devem usar valor menor.'],
        'limit_per_hour' => ['icon' => 'bi-hourglass-top', 'when' => 'Controla o ritmo por hora para evitar rajadas.'],
        'limit_per_day' => ['icon' => 'bi-calendar-day', 'when' => 'Teto diario por operacao. Essencial para warmup.'],
        'recipient_cooldown_minutes' => ['icon' => 'bi-person-clock', 'when' => 'Tempo minimo entre mensagens para o mesmo destinatario.'],
        'message_cooldown_minutes' => ['icon' => 'bi-chat-square-text', 'when' => 'Tempo minimo para repetir a mesma mensagem.'],
        'campaign_cooldown_minutes' => ['icon' => 'bi-megaphone', 'when' => 'Espacamento entre campanhas no mesmo numero ou publico.'],
        'release_when_limited_seconds' => ['icon' => 'bi-arrow-repeat', 'when' => 'Tempo de espera antes de retentar quando bater limite.'],
        'human_delay_enabled' => ['icon' => 'bi-person-walking', 'when' => 'Simula comportamento humano entre envios automaticos.'],
        'human_delay_min_seconds' => ['icon' => 'bi-stopwatch', 'when' => 'Menor atraso entre mensagens. Evite valores muito baixos.'],
        'human_delay_max_seconds' => ['icon' => 'bi-stopwatch-fill', 'when' => 'Maior atraso entre mensagens para gerar variacao.'],
        'send_delay_min_ms' => ['icon' => 'bi-cpu', 'when' => 'Atraso minimo entre chamadas de API. Suaviza disparos proximos.'],
        'send_delay_max_ms' => ['icon' => 'bi-cpu-fill', 'when' => 'Atraso maximo entre chamadas de API. Reduz previsibilidade.'],
        'warmup_enabled' => ['icon' => 'bi-thermometer-half', 'when' => 'Use em numero novo ou em recuperacao de reputacao.'],
        'warmup_limit_days_1_3' => ['icon' => 'bi-1-circle', 'when' => 'Limite diario para os primeiros 3 dias.'],
        'warmup_limit_days_4_7' => ['icon' => 'bi-4-circle', 'when' => 'Limite diario intermediario. Aumente gradualmente.'],
        'warmup_limit_days_8_15' => ['icon' => 'bi-8-circle', 'when' => 'Limite diario da etapa avancada de aquecimento.'],
        'risk_medium' => ['icon' => 'bi-activity', 'when' => 'Faixa de atencao sem bloqueio agressivo.'],
        'risk_high' => ['icon' => 'bi-exclamation-triangle', 'when' => 'Faixa que pede acao corretiva: mais delay ou menos limite.'],
        'risk_critical' => ['icon' => 'bi-exclamation-octagon', 'when' => 'Faixa critica para endurecer respostas automaticas.'],
        'risk_block_above' => ['icon' => 'bi-shield-x', 'when' => 'Acima deste score o envio pode ser barrado.'],
        'webhook_secret_required' => ['icon' => 'bi-lock', 'when' => 'Exija segredo em producao.'],
        'webhook_max_age_seconds' => ['icon' => 'bi-key', 'when' => 'Janela maxima para aceitar webhooks e bloquear replay.'],
        'api_rate_limit_per_minute' => ['icon' => 'bi-hdd-network', 'when' => 'Limite da API publica por minuto contra flood.'],
        'tenant_allow_custom_limits' => ['icon' => 'bi-sliders2', 'when' => 'Permite que inquilinos configurem cadencia/limites em seu painel por sua conta em risco.'],
        'tenant_max_limit_per_day' => ['icon' => 'bi-shield-shaded', 'when' => 'Teto diario absoluto que um inquilino pode configurar mesmo em modo acelerado.'],
        'tenant_max_limit_per_minute' => ['icon' => 'bi-speedometer2', 'when' => 'Velocidade maxima por minuto permitida para instancias de inquilinos.'],
        'tenant_max_limit_per_hour' => ['icon' => 'bi-hourglass-split', 'when' => 'Teto maximo de disparos por hora permitido para inquilinos.'],
        'send_pix_qrcode_image' => ['icon' => 'bi-qr-code', 'when' => 'Dispara a imagem do QR Code Pix nas cobrancas e templates de Pix alem do codigo Copia e Cola.'],
    ];

    $configSections = [
        [
            'title' => 'Politica operacional',
            'subtitle' => 'Motor global, janela comercial e regras basicas de protecao.',
            'fields' => [
                ['name' => 'enabled', 'label' => 'Protecao ativa', 'type' => 'select', 'value' => $settings['enabled'] ? 1 : 0, 'options' => [1 => 'Sim', 0 => 'Nao']],
                [
                    'name' => 'timezone', 
                    'label' => 'Timezone', 
                    'type' => 'select', 
                    'value' => $settings['timezone'],
                    'options' => [
                        'America/Sao_Paulo' => 'America/Sao_Paulo (Horário de Brasília)',
                        'America/Manaus' => 'America/Manaus (Amazonas)',
                        'America/Campo_Grande' => 'America/Campo_Grande (Mato Grosso do Sul)',
                        'America/Cuiaba' => 'America/Cuiaba (Mato Grosso)',
                        'America/Porto_Velho' => 'America/Porto_Velho (Rondônia)',
                        'America/Boa_Vista' => 'America/Boa_Vista (Roraima)',
                        'America/Rio_Branco' => 'America/Rio_Branco (Acre)',
                        'America/Fortaleza' => 'America/Fortaleza',
                        'America/Recife' => 'America/Recife',
                        'America/Bahia' => 'America/Bahia',
                        'America/Belem' => 'America/Belem',
                        'UTC' => 'UTC (Tempo Universal Coordenado)'
                    ]
                ],
                ['name' => 'business_hours_enabled', 'label' => 'Janela comercial', 'type' => 'select', 'value' => $settings['business_hours_enabled'] ? 1 : 0, 'options' => [1 => 'Ativa', 0 => 'Desligada']],
                ['name' => 'business_hours_start', 'label' => 'Inicio', 'type' => 'time', 'value' => $settings['business_hours_start']],
                ['name' => 'business_hours_end', 'label' => 'Fim', 'type' => 'time', 'value' => $settings['business_hours_end']],
                ['name' => 'block_groups', 'label' => 'Bloquear grupos', 'type' => 'select', 'value' => $settings['block_groups'] ? 1 : 0, 'options' => [1 => 'Sim', 0 => 'Nao']],
                ['name' => 'minimum_message_chars', 'label' => 'Minimo de caracteres', 'type' => 'number', 'value' => $settings['minimum_message_chars']],
                ['name' => 'stop_keywords', 'label' => 'Palavras de parada', 'type' => 'text', 'value' => $settings['stop_keywords']],
            ],
        ],
        [
            'title' => 'Limites e cooldowns',
            'subtitle' => 'Volume maximo por janela e tempo minimo entre contato, mensagem e campanha.',
            'fields' => [
                ['name' => 'limit_per_minute', 'label' => 'Por minuto', 'type' => 'number', 'value' => $settings['limit_per_minute']],
                ['name' => 'limit_per_hour', 'label' => 'Por hora', 'type' => 'number', 'value' => $settings['limit_per_hour']],
                ['name' => 'limit_per_day', 'label' => 'Por dia', 'type' => 'number', 'value' => $settings['limit_per_day']],
                ['name' => 'recipient_cooldown_minutes', 'label' => 'Destinatario', 'type' => 'number', 'value' => $settings['recipient_cooldown_minutes']],
                ['name' => 'message_cooldown_minutes', 'label' => 'Mensagem', 'type' => 'number', 'value' => $settings['message_cooldown_minutes']],
                ['name' => 'campaign_cooldown_minutes', 'label' => 'Campanha', 'type' => 'number', 'value' => $settings['campaign_cooldown_minutes']],
                ['name' => 'release_when_limited_seconds', 'label' => 'Retentar em segundos', 'type' => 'number', 'value' => $settings['release_when_limited_seconds']],
            ],
        ],
        [
            'title' => 'Permissões e Tetos para Tenants',
            'subtitle' => 'Controle o que os inquilinos podem ajustar em suas instâncias por sua conta em risco.',
            'fields' => [
                ['name' => 'tenant_allow_custom_limits', 'label' => 'Permitir ajuste pelo Tenant', 'type' => 'select', 'value' => ($settings['tenant_allow_custom_limits'] ?? true) ? 1 : 0, 'options' => [1 => 'Sim (Permitido)', 0 => 'Não (Bloqueado)']],
                ['name' => 'tenant_max_limit_per_day', 'label' => 'Teto diário máximo para Tenant', 'type' => 'number', 'value' => $settings['tenant_max_limit_per_day'] ?? 500],
                ['name' => 'tenant_max_limit_per_hour', 'label' => 'Teto por hora para Tenant', 'type' => 'number', 'value' => $settings['tenant_max_limit_per_hour'] ?? 90],
                ['name' => 'tenant_max_limit_per_minute', 'label' => 'Teto por minuto para Tenant', 'type' => 'number', 'value' => $settings['tenant_max_limit_per_minute'] ?? 6],
            ],
        ],
        [
            'title' => 'Delay e aquecimento',
            'subtitle' => 'Atrasos humanos, suavizacao de envios e warmup progressivo.',
            'fields' => [
                ['name' => 'human_delay_enabled', 'label' => 'Delay humano', 'type' => 'select', 'value' => $settings['human_delay_enabled'] ? 1 : 0, 'options' => [1 => 'Ativo', 0 => 'Desligado']],
                ['name' => 'human_delay_min_seconds', 'label' => 'Minimo em segundos', 'type' => 'number', 'value' => $settings['human_delay_min_seconds']],
                ['name' => 'human_delay_max_seconds', 'label' => 'Maximo em segundos', 'type' => 'number', 'value' => $settings['human_delay_max_seconds']],
                ['name' => 'send_delay_min_ms', 'label' => 'Delay minimo ms', 'type' => 'number', 'value' => $settings['send_delay_min_ms'] ?? 1500],
                ['name' => 'send_delay_max_ms', 'label' => 'Delay maximo ms', 'type' => 'number', 'value' => $settings['send_delay_max_ms'] ?? 4500],
                ['name' => 'warmup_enabled', 'label' => 'Warmup', 'type' => 'select', 'value' => $settings['warmup_enabled'] ? 1 : 0, 'options' => [1 => 'Ativo', 0 => 'Desligado']],
                ['name' => 'warmup_limit_days_1_3', 'label' => 'Dias 1-3', 'type' => 'number', 'value' => $warmupLimits[3] ?? 30],
                ['name' => 'warmup_limit_days_4_7', 'label' => 'Dias 4-7', 'type' => 'number', 'value' => $warmupLimits[7] ?? 80],
                ['name' => 'warmup_limit_days_8_15', 'label' => 'Dias 8-15', 'type' => 'number', 'value' => $warmupLimits[15] ?? 150],
            ],
        ],
        [
            'title' => 'Score, API e webhooks',
            'subtitle' => 'Faixas de risco, bloqueio automatico e seguranca das integracoes.',
            'fields' => [
                ['name' => 'risk_medium', 'label' => 'Risco medio', 'type' => 'number', 'value' => $settings['risk_medium']],
                ['name' => 'risk_high', 'label' => 'Risco alto', 'type' => 'number', 'value' => $settings['risk_high']],
                ['name' => 'risk_critical', 'label' => 'Risco critico', 'type' => 'number', 'value' => $settings['risk_critical']],
                ['name' => 'risk_block_above', 'label' => 'Bloquear acima', 'type' => 'number', 'value' => $settings['risk_block_above']],
                ['name' => 'webhook_secret_required', 'label' => 'Webhook secreto obrigatorio', 'type' => 'select', 'value' => $settings['webhook_secret_required'] ? 1 : 0, 'options' => [1 => 'Sim', 0 => 'Nao']],
                ['name' => 'webhook_max_age_seconds', 'label' => 'Replay maximo em segundos', 'type' => 'number', 'value' => $settings['webhook_max_age_seconds']],
                ['name' => 'api_rate_limit_per_minute', 'label' => 'API req/min', 'type' => 'number', 'value' => $settings['api_rate_limit_per_minute']],
            ],
        ],
        [
            'title' => 'Mídias e QR Code Pix',
            'subtitle' => 'Controle do disparo de imagem do QR Code nas mensagens de cobrança e Pix.',
            'fields' => [
                ['name' => 'send_pix_qrcode_image', 'label' => 'Enviar imagem do QR Code Pix', 'type' => 'select', 'value' => ($settings['send_pix_qrcode_image'] ?? true) ? 1 : 0, 'options' => [1 => 'Sim (Disparar imagem do QR Code)', 0 => 'Não (Apenas texto Copia e Cola)']],
            ],
        ],
    ];
@endphp

@push('head')
<style>
/* CSS Escopado e Padronizado */
.whatsapp-safety-page {
    max-width: 100%;
    overflow-x: hidden;
}

.safety-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 1200px) {
    .safety-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 768px) {
    .safety-summary-grid { grid-template-columns: 1fr; }
}

.safety-content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(320px, .8fr);
    gap: 1rem;
    align-items: start;
}
@media (max-width: 992px) {
    .safety-content-grid { grid-template-columns: 1fr; }
}

.safety-card {
    border: 1px solid var(--app-border-soft, rgba(148,163,184,.18));
    border-radius: var(--app-radius-premium, 18px);
    background: var(--app-surface-bg, #111827);
    box-shadow: none;
    margin-bottom: 1rem;
}
.safety-card + .safety-card {
    margin-top: 1rem;
}
.safety-card-header {
    padding: 1.25rem 1.25rem 0;
    border-bottom: none;
    background: transparent;
}
.safety-card-body {
    padding: 1.25rem;
}

.safety-form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}
@media (max-width: 1400px) {
    .safety-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 768px) {
    .safety-form-grid { grid-template-columns: 1fr; }
}

.safety-actions {
    display: flex;
    justify-content: flex-end;
    gap: .75rem;
    flex-wrap: wrap;
    margin-top: 1.5rem;
}

/* Metric Cards Customization */
.safety-metric {
    border: 1px solid var(--app-border-soft, rgba(148,163,184,.18));
    border-radius: var(--app-radius-premium, 18px);
    background: var(--app-surface-bg, #111827);
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.safety-metric__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 12px;
    background: color-mix(in srgb, currentColor 12%, transparent);
    margin-bottom: .75rem;
}
.safety-metric__label {
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--bs-secondary-color);
    margin-bottom: .25rem;
}
.safety-metric__value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.1;
}

/* Form Fields */
.safety-field {
    border: 1px solid var(--app-border-soft, rgba(148,163,184,.18));
    border-radius: 12px;
    padding: 1rem;
    background: var(--app-surface-bg, #111827);
    height: 100%;
}
.safety-field__label {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .5rem;
    font-weight: 600;
    font-size: .9rem;
}
.safety-field__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 8px;
    color: var(--bs-primary);
    background: color-mix(in srgb, var(--bs-primary) 12%, transparent);
}
.safety-field .form-text {
    color: var(--bs-secondary-color);
    font-size: .82rem;
    line-height: 1.35;
    margin-top: .5rem;
}

/* Specific elements */
.safety-instance-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 12px;
    color: var(--bs-primary);
    background: color-mix(in srgb, var(--bs-primary) 12%, transparent);
    font-weight: 800;
}
.safety-risk-bar {
    height: .5rem;
    overflow: hidden;
    border-radius: 999px;
    background: var(--bs-tertiary-bg);
}
.safety-risk-bar > span {
    display: block;
    height: 100%;
    width: var(--risk-width);
    background: var(--bs-success);
}
.safety-risk-bar.is-warning > span { background: var(--bs-warning); }
.safety-risk-bar.is-danger > span { background: var(--bs-danger); }
</style>
@endpush

@section('content')
<div class="container-fluid whatsapp-safety-page">
    
    <x-ui.page-header
        title="Painel Anti-Ban WhatsApp"
        subtitle="Central de risco, limites, cooldowns, warmup e segurança operacional dos envios."
        eyebrow="WhatsApp Safety">
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Instâncias</span>
                <span class="dashboard-note__value">{{ $instanceCount }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Com proteção</span>
                <span class="dashboard-note__value">{{ $protectedCount }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    @if(! empty($alerts))
        <div class="row g-3 mb-4">
            @foreach($alerts as $alert)
                @php
                    $alertTone = $alert['severity'] === 'critical' ? 'danger' : ($alert['severity'] === 'warning' ? 'warning' : 'info');
                @endphp
                <div class="col-12 col-lg-4">
                    <div class="alert alert-{{ $alertTone }} mb-0 border-0 shadow-sm">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-{{ $alertTone === 'danger' ? 'exclamation-octagon' : ($alertTone === 'warning' ? 'exclamation-triangle' : 'info-circle') }} mt-1"></i>
                            <span>{{ $alert['message'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="safety-summary-grid safety-metrics-grid">
        @foreach($metricCards as $metric)
            <div class="safety-metric text-{{ $metric['tone'] }}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="safety-metric__icon"><i class="bi {{ $metric['icon'] }}"></i></span>
                    <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" data-bs-title="{{ $metric['help'] }}"></i>
                </div>
                <span class="safety-metric__label">{{ $metric['label'] }}</span>
                <strong class="safety-metric__value">{{ $metric['value'] }}</strong>
            </div>
        @endforeach
    </div>

    <div class="safety-card">
        <div class="safety-card-header">
            <h5 class="mb-0">Instâncias monitoradas</h5>
            <small class="text-muted">Ações rápidas por instância para pausar, retomar, aquecer e reduzir risco.</small>
        </div>
        <div class="safety-card-body">
            <div class="d-flex flex-column gap-3">
            @forelse($instances as $instance)
                @php
                    $control = $instance->safetyControl;
                    $rep = $instance->reputation;
                    $quar = $instance->quarantine;
                    $riskScore = (int) ($rep?->risk_score ?? 0);
                    $safeRiskScore = max(0, min(100, $riskScore));
                    $riskClass = $riskScore >= 81 ? 'is-danger' : ($riskScore >= 51 ? 'is-warning' : 'is-success');
                @endphp
                <div class="border rounded p-3" style="border-color: var(--app-border-soft, rgba(148,163,184,.18)) !important; background: var(--app-surface-bg, #111827);">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-lg-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="safety-instance-avatar">
                                    {{ mb_strtoupper(mb_substr($instance->display_name ?: $instance->instance_name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-bold text-truncate">{{ $instance->display_name ?: $instance->instance_name }}</div>
                                    <div class="text-muted small text-truncate">{{ $instance->tenant?->company_name ?: 'Sem tenant vinculado' }}</div>
                                    <code class="small d-block text-truncate">{{ $instance->instance_name }}</code>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <x-ui.status-badge :status="$instance->status === 'connected' ? 'active' : 'inactive'" :label="$instance->status" />
                                @if($quar)
                                    <x-ui.status-badge status="overdue" label="Quarentena" />
                                @endif
                                @if($control?->sending_disabled)
                                    <x-ui.status-badge status="overdue" label="Envios off" />
                                @elseif($control?->sending_paused)
                                    <x-ui.status-badge status="pending" label="Pausada" />
                                @endif
                                @if($control?->warmup_enabled)
                                    <x-ui.status-badge status="sandbox" label="Warmup" />
                                @endif
                                @if($control?->campaigns_blocked)
                                    <x-ui.status-badge status="inactive" label="Campanhas off" />
                                @endif
                            </div>
                            @if($quar)
                                <div class="small text-danger">{{ $quar->reason }}</div>
                            @else
                                <div class="small text-muted">Sem quarentena ativa.</div>
                            @endif
                        </div>
                        <div class="col-12 col-lg-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Risco</span>
                                <strong>{{ $riskScore }}/100</strong>
                            </div>
                            <div class="safety-risk-bar {{ $riskClass }}" style="--risk-width: {{ $safeRiskScore }}%">
                                <span></span>
                            </div>
                            <div class="small text-muted mt-2">Trust {{ $rep?->trust_score ?? 100 }} | Qualidade {{ $rep?->quality_score ?? 100 }}</div>
                        </div>
                        <div class="col-12 col-lg-2 text-lg-end">
                            <x-ui.action-dropdown label="Ações">
                                <button type="button" class="dropdown-item js-control" data-id="{{ $instance->id }}" data-action="pause"><i class="bi bi-pause-circle me-2"></i>Pausar envios</button>
                                <button type="button" class="dropdown-item js-control" data-id="{{ $instance->id }}" data-action="resume"><i class="bi bi-play-circle me-2"></i>Retomar envios</button>
                                <button type="button" class="dropdown-item js-control" data-id="{{ $instance->id }}" data-action="warmup"><i class="bi bi-thermometer-half me-2"></i>Ativar warmup</button>
                                <button type="button" class="dropdown-item js-control" data-id="{{ $instance->id }}" data-action="reduce_limit"><i class="bi bi-speedometer me-2"></i>Reduzir limite</button>
                                <button type="button" class="dropdown-item js-control" data-id="{{ $instance->id }}" data-action="increase_delay"><i class="bi bi-stopwatch me-2"></i>Aumentar delay</button>
                                <button type="button" class="dropdown-item js-control" data-id="{{ $instance->id }}" data-action="block_campaigns"><i class="bi bi-megaphone me-2"></i>Bloquear campanhas</button>
                                <button type="button" class="dropdown-item text-danger js-control" data-id="{{ $instance->id }}" data-action="disable_sends"><i class="bi bi-shield-x me-2"></i>Desligar envios</button>
                                @if($quar)
                                    <div class="dropdown-divider"></div>
                                    <button type="button" class="dropdown-item text-success js-release-quarantine" data-id="{{ $instance->id }}"><i class="bi bi-unlock me-2"></i>Liberar quarentena</button>
                                @endif
                                <button type="button" class="dropdown-item js-reset-warmup" data-id="{{ $instance->id }}"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset warmup</button>
                            </x-ui.action-dropdown>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state text-center p-4">
                    <i class="bi bi-phone fs-1 text-muted mb-2 d-block"></i>
                    <h6 class="mb-1">Nenhuma instância monitorada</h6>
                    <p class="text-muted small mb-0">Cadastre uma instância do WhatsApp para habilitar os controles anti-ban.</p>
                </div>
            @endforelse
            </div>
        </div>
    </div>

    <div class="safety-content-grid">
        <!-- Coluna Esquerda: Configurações -->
        <div>
            <div class="safety-card">
                <div class="safety-card-header">
                    <h5 class="mb-0">Configuração global anti-ban</h5>
                    <small class="text-muted">Campos organizados por contexto.</small>
                </div>
                <div class="safety-card-body">
                    <form id="safety-config-form">
                        @csrf
                        <div class="accordion" id="safetyConfigAccordion">
                            @foreach($configSections as $sectionIndex => $section)
                                <div class="accordion-item bg-transparent border-secondary border-opacity-25 mb-3 rounded overflow-hidden">
                                    <h2 class="accordion-header" id="safety-heading-{{ $sectionIndex }}">
                                        <button class="accordion-button bg-transparent {{ $sectionIndex > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#safety-section-{{ $sectionIndex }}">
                                            <div>
                                                <strong class="d-block">{{ $section['title'] }}</strong>
                                                <small class="text-muted fw-normal">{{ $section['subtitle'] }}</small>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="safety-section-{{ $sectionIndex }}" class="accordion-collapse collapse {{ $sectionIndex === 0 ? 'show' : '' }}" data-bs-parent="#safetyConfigAccordion">
                                        <div class="accordion-body">
                                            <div class="safety-form-grid">
                                                @foreach($section['fields'] as $field)
                                                    @php
                                                        $help = $fieldHelp[$field['name']] ?? ['icon' => 'bi-info-circle', 'when' => 'Ajuste operacional do anti-ban.'];
                                                    @endphp
                                                    <div class="safety-field">
                                                        <label class="safety-field__label" for="safety-{{ $field['name'] }}">
                                                            <span class="safety-field__icon"><i class="bi {{ $help['icon'] }}"></i></span>
                                                            <span>{{ $field['label'] }}</span>
                                                            <i class="bi bi-question-circle text-muted ms-auto" data-bs-toggle="tooltip" data-bs-title="{{ $help['when'] }}"></i>
                                                        </label>
                                                        @if($field['type'] === 'select')
                                                            <select id="safety-{{ $field['name'] }}" name="{{ $field['name'] }}" class="form-select bg-dark text-light border-secondary">
                                                                @foreach($field['options'] as $value => $label)
                                                                    <option value="{{ $value }}" {{ (string) $field['value'] === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        @else
                                                            <input id="safety-{{ $field['name'] }}" type="{{ $field['type'] }}" name="{{ $field['name'] }}" class="form-control bg-dark text-light border-secondary" value="{{ $field['value'] }}">
                                                        @endif
                                                        <div class="form-text">{{ $help['when'] }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="safety-actions">
                            <button type="button" id="save-safety-config" class="btn btn-primary">
                                <i class="bi bi-shield-check me-1"></i> Salvar configuração
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Coluna Direita: Saúde Operacional e Picos -->
        <div>
            <div class="safety-card">
                <div class="safety-card-header">
                    <h5 class="mb-0">Saúde operacional</h5>
                    <small class="text-muted">Resumo rápido do estado do dia.</small>
                </div>
                <div class="safety-card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-3 border rounded text-center border-secondary border-opacity-25 h-100">
                                <span class="d-block small text-muted text-uppercase fw-bold mb-1">Retries</span>
                                <strong class="fs-4">{{ $stats['retries'] ?? 0 }}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded text-center border-secondary border-opacity-25 h-100">
                                <span class="d-block small text-muted text-uppercase fw-bold mb-1">Cooldowns</span>
                                <strong class="fs-4">{{ $stats['deferred'] ?? 0 }}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded text-center border-secondary border-opacity-25 h-100">
                                <span class="d-block small text-muted text-uppercase fw-bold mb-1">Pausadas</span>
                                <strong class="fs-4">{{ $stats['paused_instances'] ?? 0 }}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded text-center border-secondary border-opacity-25 h-100">
                                <span class="d-block small text-muted text-uppercase fw-bold mb-1">Warmup</span>
                                <strong class="fs-4">{{ $stats['warmup_instances'] ?? 0 }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="safety-card">
                <div class="safety-card-header">
                    <h5 class="mb-0">Picos de risco</h5>
                    <small class="text-muted">Instâncias com maior score registrado hoje.</small>
                </div>
                <div class="safety-card-body">
                    @if($topRisks->isEmpty())
                        <div class="empty-state text-center p-3">
                            <i class="bi bi-graph-up fs-2 text-muted mb-2 d-block"></i>
                            <h6 class="mb-1">Sem eventos críticos</h6>
                            <p class="text-muted small mb-0">Nenhum pico de risco relevante hoje.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-dark mb-0">
                                <thead>
                                    <tr>
                                        <th>Instância</th>
                                        <th>Score</th>
                                        <th>Eventos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topRisks as $risk)
                                        @php
                                            $riskBadge = $risk->peak_risk >= 81 ? 'overdue' : ($risk->peak_risk >= 51 ? 'pending' : 'sandbox');
                                        @endphp
                                        <tr>
                                            <td>#{{ $risk->tenant_instance_id ?: '-' }}</td>
                                            <td><x-ui.status-badge :status="$riskBadge" :label="$risk->peak_risk" /></td>
                                            <td>{{ $risk->events }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
    if (window.bootstrap?.Tooltip) {
        new window.bootstrap.Tooltip(element);
    }
});

async function confirmSafetyAction(message) {
    if (window.Swal) {
        const result = await window.Swal.fire({
            title: 'Confirmar ação',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar',
        });
        return result.isConfirmed;
    }
    return Promise.resolve(false);
}

async function postSafetyJson(url, payload = null) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: payload ? JSON.stringify(payload) : null,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Falha ao processar a solicitação.');
    }
    return data;
}

document.querySelectorAll('.js-control').forEach((button) => {
    button.addEventListener('click', async () => {
        const instanceId = button.dataset.id;
        const action = button.dataset.action;
        try {
            await postSafetyJson(`{{ url('/admin/whatsapp/instances') }}/${instanceId}/controls`, { action });
            window.toast?.success('Controle anti-ban atualizado.');
            setTimeout(() => window.location.reload(), 1500);
        } catch (error) {
            window.toast?.error(error.message || 'Falha ao atualizar o controle anti-ban.');
        }
    });
});

document.querySelectorAll('.js-release-quarantine').forEach((button) => {
    button.addEventListener('click', async () => {
        try {
            await postSafetyJson(`{{ url('/admin/whatsapp/instances') }}/${button.dataset.id}/quarantine/release`);
            window.toast?.success('Quarentena liberada.');
            setTimeout(() => window.location.reload(), 1500);
        } catch (error) {
            window.toast?.error(error.message || 'Falha ao liberar quarentena.');
        }
    });
});

document.querySelectorAll('.js-reset-warmup').forEach((button) => {
    button.addEventListener('click', async () => {
        const confirmed = await confirmSafetyAction('Resetar warmup desta instância? O limite diário voltará ao mínimo.');
        if (! confirmed) return;
        try {
            await postSafetyJson(`{{ url('/admin/whatsapp/instances') }}/${button.dataset.id}/reset-warmup`);
            window.toast?.success('Warmup resetado.');
            setTimeout(() => window.location.reload(), 1500);
        } catch (error) {
            window.toast?.error(error.message || 'Falha ao resetar warmup.');
        }
    });
});

const saveBtn = document.getElementById('save-safety-config');
if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
        const form = document.getElementById('safety-config-form');
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        const originalHtml = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Salvando...';
        saveBtn.disabled = true;

        try {
            await postSafetyJson(`{{ route('admin.whatsapp.safety.config') }}`, payload);
            window.toast?.success('Configuração anti-ban salva com sucesso.');
            setTimeout(() => window.location.reload(), 1500);
        } catch (error) {
            window.toast?.error(error.message || 'Falha ao salvar a configuração anti-ban.');
            saveBtn.innerHTML = originalHtml;
            saveBtn.disabled = false;
        }
    });
}
</script>
@endpush
