@extends('tenancy::tenant.layout')
@section('title', 'Configuração de Cobrança Automática')

@section('content')
<div class="container-fluid px-0">

    {{-- ========== HEADER ========== --}}
    <x-ui.page-header
        title="Configuração de Cobrança Automática"
        subtitle="Parametrize horários de disparo, régua preventiva, cobrança pós-vencimento e regras de pagamento."
        eyebrow="Financeiro & Automação"
        class="mb-4"
    >
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <a href="{{ route('tenant.billing.history') }}" class="btn btn-outline-secondary btn-sm" title="Histórico de mensagens de cobrança">
                    <i class="bi bi-clock-history me-1"></i> Histórico de Envios
                </a>
                <a href="{{ route('tenant.invoices') }}" class="btn btn-outline-secondary btn-sm" title="Ver faturas geradas">
                    <i class="bi bi-file-earmark-text me-1"></i> Faturas & Cobranças
                </a>
                <button type="button" class="btn btn-primary btn-sm btn-save-billing-trigger" title="Salvar todas as alterações">
                    <i class="bi bi-check2-circle me-1"></i> Salvar Configuração
                </button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== KPIS / INDICADORES ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-ui.stats-card
                label="Horário de Disparo"
                :value="$config->cron_time ?? '08:00'"
                icon="bi-alarm"
                color="primary"
                meta="Execução diária programada"
                class="h-100"
            />
        </div>
        <div class="col-6 col-lg-3">
            <x-ui.stats-card
                label="Geração de Fatura"
                :value="($config->invoice_creation_days ?? 5) . ' dias antes'"
                icon="bi-calendar-plus"
                color="info"
                meta="Emissão prévia da cobrança"
                class="h-100"
            />
        </div>
        <div class="col-6 col-lg-3">
            @php
                $chargeTypeLabel = match($config->charge_type ?? 'both') {
                    'single' => 'À Vista',
                    'installment' => 'Parcelado',
                    default => 'À Vista & Parcelado',
                };
            @endphp
            <x-ui.stats-card
                label="Tipo de Cobrança"
                :value="$chargeTypeLabel"
                icon="bi-credit-card-2-front"
                color="success"
                meta="Opções ofertadas ao cliente"
                class="h-100"
            />
        </div>
        <div class="col-6 col-lg-3">
            <x-ui.stats-card
                label="Feriados & Fim de Semana"
                :value="($config->skip_weekends_holidays ?? true) ? 'Pula para Dia Útil' : 'Vencimento Direto'"
                icon="bi-shield-check"
                color="warning"
                meta="Ajuste bancário automático"
                class="h-100"
            />
        </div>
    </div>

    {{-- ========== FORMULÁRIO PRINCIPAL ========== --}}
    <form id="frm-billing-config">
        <div class="row g-4">
            {{-- Coluna Esquerda: Parâmetros e Régua --}}
            <div class="col-lg-8">
                
                {{-- CARD 1: Parâmetros Gerais e Régua de Cobrança --}}
                <x-ui.premium-card
                    title="Régua de Disparo & Lembretes Automáticos"
                    subtitle="Defina o momento de emissão e os intervalos de alertas preventivos e cobranças em atraso."
                    class="mb-4"
                >
                    <div class="row g-3">
                        {{-- Horário de Envio --}}
                        <div class="col-md-4">
                            <label class="form-label fw-bold d-flex align-items-center justify-content-between">
                                <span><i class="bi bi-clock me-1 text-primary"></i> Horário de Envio</span>
                                <span class="badge bg-primary-subtle text-primary" style="font-size:0.7rem;">Diário</span>
                            </label>
                            <input type="time" name="cron_time" class="form-control form-control-lg fs-6"
                                   value="{{ $config->cron_time ?? '08:00' }}" required>
                            <div class="form-text">Horário em que as mensagens serão disparadas diariamente.</div>
                        </div>

                        {{-- Criar fatura antes --}}
                        <div class="col-md-4">
                            <label class="form-label fw-bold d-flex align-items-center justify-content-between">
                                <span><i class="bi bi-calendar-check me-1 text-info"></i> Gerar Fatura</span>
                                <span class="badge bg-info-subtle text-info" style="font-size:0.7rem;">Antecipação</span>
                            </label>
                            <div class="input-group">
                                <input type="number" name="invoice_creation_days" class="form-control form-control-lg fs-6"
                                       value="{{ $config->invoice_creation_days ?? 5 }}" min="1" max="30" required>
                                <span class="input-group-text">dias antes</span>
                            </div>
                            <div class="form-text">Quantos dias antes do vencimento gerar a fatura.</div>
                        </div>

                        {{-- Tipo de Cobrança --}}
                        <div class="col-md-4">
                            <label class="form-label fw-bold d-flex align-items-center justify-content-between">
                                <span><i class="bi bi-wallet2 me-1 text-success"></i> Tipo de Cobrança</span>
                                <span class="badge bg-success-subtle text-success" style="font-size:0.7rem;">Modalidade</span>
                            </label>
                            <select name="charge_type" class="form-select form-select-lg fs-6" required>
                                <option value="both" {{ ($config->charge_type ?? '') === 'both' ? 'selected' : '' }}>À vista & Parcelado</option>
                                <option value="single" {{ ($config->charge_type ?? '') === 'single' ? 'selected' : '' }}>Somente À vista</option>
                                <option value="installment" {{ ($config->charge_type ?? '') === 'installment' ? 'selected' : '' }}>Somente Parcelado</option>
                            </select>
                            <div class="form-text">Como o cliente poderá pagar o valor cobrado.</div>
                        </div>

                        <div class="col-12"><hr class="my-3 opacity-25"></div>

                        {{-- Régua Preventiva: Antes do Vencimento --}}
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <label class="form-label fw-bold mb-0">
                                        <i class="bi bi-bell me-1 text-primary"></i> Lembretes Preventivos (Antes do Vencimento)
                                    </label>
                                    <div class="text-secondary small">Selecione os dias que antecedem o vencimento para alertar o cliente:</div>
                                </div>
                                <span class="badge bg-primary-subtle text-primary d-none d-sm-inline-block">Recomendado: 3d e 1d</span>
                            </div>

                            @php
                                $daysBefore = $config->days_before_due ?? [3, 1];
                                if (!is_array($daysBefore)) $daysBefore = [];
                            @endphp
                            <div class="d-flex flex-wrap gap-2 pt-1" id="days-before-container">
                                @foreach([1, 2, 3, 5, 7, 10, 15] as $d)
                                    @php $checked = in_array($d, $daysBefore); @endphp
                                    <label class="billing-day-chip billing-day-chip-before {{ $checked ? 'active-before' : '' }}" for="before-{{ $d }}">
                                        <input type="checkbox" name="days_before_due[]" value="{{ $d }}"
                                               id="before-{{ $d }}" {{ $checked ? 'checked' : '' }}>
                                        <i class="bi {{ $checked ? 'bi-check-circle-fill' : 'bi-circle' }} chip-icon"></i>
                                        <span>{{ $d }} dia{{ $d > 1 ? 's' : '' }} antes</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12"><hr class="my-3 opacity-25"></div>

                        {{-- Régua de Cobrança: Pós-Vencimento --}}
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <label class="form-label fw-bold mb-0">
                                        <i class="bi bi-exclamation-triangle me-1 text-warning"></i> Reenvios em Atraso (Após o Vencimento)
                                    </label>
                                    <div class="text-secondary small">Selecione os dias após o vencimento para cobrar a fatura pendente:</div>
                                </div>
                                <span class="badge bg-warning-subtle text-warning-emphasis d-none d-sm-inline-block">Recomendado: 1d, 3d e 7d</span>
                            </div>

                            @php
                                $daysAfter = $config->days_after_due ?? [1, 3, 7];
                                if (!is_array($daysAfter)) $daysAfter = [];
                            @endphp
                            <div class="d-flex flex-wrap gap-2 pt-1" id="days-after-container">
                                @foreach([1, 2, 3, 5, 7, 10, 15, 30] as $d)
                                    @php $checked = in_array($d, $daysAfter); @endphp
                                    <label class="billing-day-chip billing-day-chip-after {{ $checked ? 'active-after' : '' }}" for="after-{{ $d }}">
                                        <input type="checkbox" name="days_after_due[]" value="{{ $d }}"
                                               id="after-{{ $d }}" {{ $checked ? 'checked' : '' }}>
                                        <i class="bi {{ $checked ? 'bi-check-circle-fill' : 'bi-circle' }} chip-icon"></i>
                                        <span>{{ $d }} dia{{ $d > 1 ? 's' : '' }} após</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-ui.premium-card>

                {{-- CARD 2: Parcelamento & Repasse de Taxas --}}
                <x-ui.premium-card
                    title="Condições de Pagamento & Taxas de Gateway"
                    subtitle="Configure o parcelamento e decida sobre o repasse das taxas de transação."
                    class="mb-4"
                >
                    <div class="row g-4">
                        {{-- Parcelamento --}}
                        <div class="col-md-6 border-end-md">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold fs-6"><i class="bi bi-pie-chart me-1 text-primary"></i> Parcelamento no Cartão</span>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="installment_enabled" value="1"
                                           id="installment-enabled" {{ ($config->installment_enabled ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>
                            <p class="text-secondary small mb-3">Permita que seus clientes dividam cobranças em parcelas no cartão de crédito.</p>

                            <div id="installment-options" class="{{ ($config->installment_enabled ?? false) ? '' : 'opacity-50' }}">
                                <label class="form-label small fw-semibold" for="max_installments">Número Máximo de Parcelas</label>
                                <div class="input-group">
                                    <input type="number" name="max_installments" id="max_installments" class="form-control"
                                           min="2" max="24" value="{{ $config->max_installments ?? 12 }}"
                                           {{ ($config->installment_enabled ?? false) ? '' : 'disabled' }}>
                                    <span class="input-group-text">vezes</span>
                                </div>
                                <div class="form-text">Limite permitido para seleção do pagador (de 2x a 24x).</div>
                            </div>
                        </div>

                        {{-- Repasse de Taxas --}}
                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold fs-6"><i class="bi bi-percent me-1 text-success"></i> Repasse de Taxas</span>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="fee_passthrough" value="1"
                                           id="fee-passthrough" {{ ($config->fee_passthrough ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>
                            <p class="text-secondary small mb-3">Transfere as tarifas do gateway de pagamento para o cliente final.</p>

                            <div id="fee-options" class="{{ ($config->fee_passthrough ?? false) ? '' : 'opacity-50' }}">
                                <label class="form-label small fw-semibold" for="fee_percentage">Percentual de Taxa a Repassar (%)</label>
                                <div class="input-group">
                                    <input type="text" name="fee_percentage" id="fee_percentage" class="form-control"
                                           inputmode="decimal" data-mask="decimal"
                                           value="{{ number_format((float) ($config->fee_percentage ?? 0), 2, ',', '.') }}"
                                           {{ ($config->fee_passthrough ?? false) ? '' : 'disabled' }}>
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">Ex: 2,99%. O valor será acrescido no total da fatura.</div>
                            </div>
                        </div>
                    </div>
                </x-ui.premium-card>

                {{-- CARD 3: Vencimento & Feriados --}}
                <x-ui.premium-card
                    title="Regras de Vencimento & Feriados Nacionais"
                    subtitle="Conformidade bancária automática para evitar cobranças indevidas de juros em dias não úteis."
                >
                    <div class="p-3 rounded border bg-body-tertiary d-flex align-items-start gap-3">
                        <div class="fs-2 text-warning lh-1 pt-1">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between">
                                <label class="fw-bold mb-1 cursor-pointer" for="skip-holidays">
                                    Pular finais de semana e feriados bancários
                                </label>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="skip_weekends_holidays" value="1"
                                           id="skip-holidays" {{ ($config->skip_weekends_holidays ?? true) ? 'checked' : '' }}>
                                </div>
                            </div>
                            <p class="text-secondary small mb-0">
                                Ao manter esta opção ativada, caso a data de vencimento calculada caia em um sábado, domingo ou feriado nacional, o sistema adia o vencimento automaticamente para o primeiro dia útil subsequente, garantindo cumprimento das normas da Febraban e proteção jurídica.
                            </p>
                        </div>
                    </div>
                </x-ui.premium-card>

            </div>

            {{-- Coluna Direita: Guia de Boas Práticas e Ações Rápidas --}}
            <div class="col-lg-4">

                {{-- Card de Ação Rápida: Salvar --}}
                <div class="card border-primary-subtle shadow-sm mb-4">
                    <div class="card-body p-4 text-center">
                        <div class="avatar-lg bg-primary-subtle text-primary rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                            <i class="bi bi-shield-check fs-2"></i>
                        </div>
                        <h5 class="fw-bold mb-1">Pronto para Atualizar?</h5>
                        <p class="text-secondary small mb-3">Suas configurações serão aplicadas no próximo ciclo diário de processamento do robô de cobrança.</p>
                        <button type="button" class="btn btn-primary w-100 py-2 fw-semibold btn-save-billing-trigger">
                            <i class="bi bi-check2-circle me-1"></i> Salvar Configuração
                        </button>
                    </div>
                </div>

                {{-- Card de Boas Práticas --}}
                <x-ui.premium-card
                    title="Boas Práticas de Cobrança"
                    subtitle="Dicas operacionais para alta taxa de conversão."
                    class="mb-4"
                >
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="badge bg-primary-subtle text-primary p-2 rounded">
                                <i class="bi bi-clock-history fs-6"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 small">Horário Estratégico</h6>
                                <p class="text-secondary small mb-0">Disparar entre 08:30 e 11:30 proporciona até 25% mais abertura no WhatsApp do que envios noturnos.</p>
                            </div>
                        </div>

                        <div class="d-flex gap-3 align-items-start">
                            <div class="badge bg-info-subtle text-info p-2 rounded">
                                <i class="bi bi-send fs-6"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 small">Régua Preventiva</h6>
                                <p class="text-secondary small mb-0">Enviar lembretes 3 e 1 dia antes do vencimento reduz a taxa de esquecimento sem gerar incômodo ao pagador.</p>
                            </div>
                        </div>

                        <div class="d-flex gap-3 align-items-start">
                            <div class="badge bg-warning-subtle text-warning-emphasis p-2 rounded">
                                <i class="bi bi-chat-quote fs-6"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 small">Cobrança Não-Invasiva</h6>
                                <p class="text-secondary small mb-0">Cobranças após 1, 3 e 7 dias mantêm a cordialidade e evitam bloqueios ou denúncias de spam no WhatsApp.</p>
                            </div>
                        </div>

                        <div class="d-flex gap-3 align-items-start">
                            <div class="badge bg-success-subtle text-success p-2 rounded">
                                <i class="bi bi-credit-card fs-6"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 small">Transparência em Repasse</h6>
                                <p class="text-secondary small mb-0">Caso opte por repassar taxas de cartão, mantenha essa informação clara na proposta e contrato.</p>
                            </div>
                        </div>
                    </div>
                </x-ui.premium-card>

                {{-- Links Rápidos do Financeiro --}}
                <x-ui.premium-card
                    title="Atalhos Financeiros"
                    subtitle="Navegue rapidamente para áreas relacionadas."
                >
                    <div class="d-flex flex-column gap-3">
                        <a href="{{ route('tenant.gateways') }}" class="text-decoration-none d-flex gap-3 align-items-start p-2 rounded-3 tenant-quick-link">
                            <div class="badge bg-primary-subtle text-primary p-2 rounded flex-shrink-0">
                                <i class="bi bi-credit-card fs-6"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="fw-bold mb-1 small text-body">Gateways de Pagamento</h6>
                                    <i class="bi bi-chevron-right text-secondary fs-6"></i>
                                </div>
                                <p class="text-secondary small mb-0">Configure contas Mercado Pago, Asaas, EFI e chaves PIX.</p>
                            </div>
                        </a>

                        <a href="{{ route('tenant.templates') }}" class="text-decoration-none d-flex gap-3 align-items-start p-2 rounded-3 tenant-quick-link">
                            <div class="badge bg-info-subtle text-info p-2 rounded flex-shrink-0">
                                <i class="bi bi-chat-square-text fs-6"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="fw-bold mb-1 small text-body">Templates de Notificação</h6>
                                    <i class="bi bi-chevron-right text-secondary fs-6"></i>
                                </div>
                                <p class="text-secondary small mb-0">Personalize modelos de mensagens de lembrete e cobrança.</p>
                            </div>
                        </a>

                        <a href="{{ route('tenant.invoices') }}" class="text-decoration-none d-flex gap-3 align-items-start p-2 rounded-3 tenant-quick-link">
                            <div class="badge bg-success-subtle text-success p-2 rounded flex-shrink-0">
                                <i class="bi bi-file-earmark-text fs-6"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="fw-bold mb-1 small text-body">Gestão de Faturas</h6>
                                    <i class="bi bi-chevron-right text-secondary fs-6"></i>
                                </div>
                                <p class="text-secondary small mb-0">Consulte cobranças geradas, status e emissões avulsas.</p>
                            </div>
                        </a>

                        <a href="{{ route('tenant.billing.history') }}" class="text-decoration-none d-flex gap-3 align-items-start p-2 rounded-3 tenant-quick-link">
                            <div class="badge bg-warning-subtle text-warning-emphasis p-2 rounded flex-shrink-0">
                                <i class="bi bi-clock-history fs-6"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="fw-bold mb-1 small text-body">Histórico de Disparos</h6>
                                    <i class="bi bi-chevron-right text-secondary fs-6"></i>
                                </div>
                                <p class="text-secondary small mb-0">Acompanhe o log de disparos e relatórios de entrega.</p>
                            </div>
                        </a>
                    </div>
                </x-ui.premium-card>

            </div>
        </div>
    </form>

</div>

<style>
/* Chips interativos para a régua de dias */
.billing-day-chip {
    cursor: pointer;
    user-select: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1.5px solid var(--bs-border-color);
    background: var(--app-surface-bg);
    border-radius: 8px;
    padding: 0.5rem 0.85rem;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--bs-body-color);
}
.billing-day-chip:hover {
    border-color: var(--bs-primary);
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.billing-day-chip input[type="checkbox"] {
    display: none;
}
.billing-day-chip.active-before {
    background: color-mix(in srgb, var(--bs-primary) 12%, var(--app-surface-bg));
    border-color: var(--bs-primary);
    color: var(--bs-primary);
    box-shadow: 0 0 0 1px var(--bs-primary);
}
.billing-day-chip.active-after {
    background: color-mix(in srgb, var(--bs-warning) 15%, var(--app-surface-bg));
    border-color: var(--bs-warning);
    color: var(--bs-warning-text-emphasis, #b26a00);
    box-shadow: 0 0 0 1px var(--bs-warning);
}
[data-bs-theme="dark"] .billing-day-chip.active-after {
    color: #ffc107;
}
.billing-day-chip .chip-icon {
    font-size: 0.9rem;
    transition: transform 0.2s ease;
}
.billing-day-chip:hover .chip-icon {
    transform: scale(1.15);
}
.border-end-md {
    border-right: none;
}
@media (min-width: 768px) {
    .border-end-md {
        border-right: 1px solid var(--bs-border-color);
    }
}
/* Links Rápidos no padrão do card de dicas */
.tenant-quick-link {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid transparent;
}
.tenant-quick-link:hover {
    background: color-mix(in srgb, var(--bs-primary) 8%, var(--app-surface-bg));
    border-color: color-mix(in srgb, var(--bs-primary) 22%, transparent);
    transform: translateX(3px);
}
.tenant-quick-link:hover .bi-chevron-right {
    color: var(--bs-primary) !important;
    transform: translateX(3px);
}
.tenant-quick-link .bi-chevron-right {
    transition: transform 0.2s ease, color 0.2s ease;
}
</style>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('frm-billing-config');
    const saveTriggers = document.querySelectorAll('.btn-save-billing-trigger');

    // Interatividade dos Chips Antes do Vencimento
    document.querySelectorAll('.billing-day-chip-before').forEach(chip => {
        const input = chip.querySelector('input[type="checkbox"]');
        const icon = chip.querySelector('.chip-icon');
        input.addEventListener('change', () => {
            if (input.checked) {
                chip.classList.add('active-before');
                icon.classList.remove('bi-circle');
                icon.classList.add('bi-check-circle-fill');
            } else {
                chip.classList.remove('active-before');
                icon.classList.remove('bi-check-circle-fill');
                icon.classList.add('bi-circle');
            }
        });
    });

    // Interatividade dos Chips Após o Vencimento
    document.querySelectorAll('.billing-day-chip-after').forEach(chip => {
        const input = chip.querySelector('input[type="checkbox"]');
        const icon = chip.querySelector('.chip-icon');
        input.addEventListener('change', () => {
            if (input.checked) {
                chip.classList.add('active-after');
                icon.classList.remove('bi-circle');
                icon.classList.add('bi-check-circle-fill');
            } else {
                chip.classList.remove('active-after');
                icon.classList.remove('bi-check-circle-fill');
                icon.classList.add('bi-circle');
            }
        });
    });

    // Toggle de opções de Parcelamento
    const installmentSwitch = document.getElementById('installment-enabled');
    const installmentOptions = document.getElementById('installment-options');
    const maxInstallmentsInput = document.getElementById('max_installments');
    installmentSwitch.addEventListener('change', () => {
        if (installmentSwitch.checked) {
            installmentOptions.classList.remove('opacity-50');
            maxInstallmentsInput.disabled = false;
        } else {
            installmentOptions.classList.add('opacity-50');
            maxInstallmentsInput.disabled = true;
        }
    });

    // Toggle de opções de Repasse de Taxas
    const feeSwitch = document.getElementById('fee-passthrough');
    const feeOptions = document.getElementById('fee-options');
    const feeInput = document.getElementById('fee_percentage');
    feeSwitch.addEventListener('change', () => {
        if (feeSwitch.checked) {
            feeOptions.classList.remove('opacity-50');
            feeInput.disabled = false;
        } else {
            feeOptions.classList.add('opacity-50');
            feeInput.disabled = true;
        }
    });

    // Função de Salvar Configuração
    async function saveConfig() {
        // Coletar dias antes
        const daysBefore = [];
        form.querySelectorAll('input[name="days_before_due[]"]:checked').forEach(cb => {
            daysBefore.push(parseInt(cb.value));
        });

        // Coletar dias após
        const daysAfter = [];
        form.querySelectorAll('input[name="days_after_due[]"]:checked').forEach(cb => {
            daysAfter.push(parseInt(cb.value));
        });

        const data = {
            cron_time: form.querySelector('[name=cron_time]').value,
            invoice_creation_days: parseInt(form.querySelector('[name=invoice_creation_days]').value) || 5,
            charge_type: form.querySelector('[name=charge_type]').value,
            days_before_due: daysBefore,
            days_after_due: daysAfter,
            fee_passthrough: form.querySelector('[name=fee_passthrough]').checked,
            fee_percentage: window.adminUI?.parseMoney
                ? window.adminUI.parseMoney(form.querySelector('[name=fee_percentage]').value)
                : (form.querySelector('[name=fee_percentage]').value || '0').replace(',', '.'),
            installment_enabled: form.querySelector('[name=installment_enabled]').checked,
            max_installments: parseInt(form.querySelector('[name=max_installments]').value) || 12,
            skip_weekends_holidays: form.querySelector('[name=skip_weekends_holidays]').checked,
        };

        // Estado visual de loading nos botões
        saveTriggers.forEach(btn => {
            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Salvando...';
        });

        try {
            const response = await fetch('{{ route("tenant.billing.save") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': window.csrf || document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.ok) {
                if (window.Toastify) {
                    Toastify({
                        text: "✓ Configurações de cobrança salvas com sucesso!",
                        duration: 3500,
                        gravity: "top",
                        position: "right",
                        style: {
                            background: "linear-gradient(to right, #00b09b, #96c93d)",
                            borderRadius: "8px",
                            fontWeight: "500"
                        }
                    }).showToast();
                } else if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Salvo com sucesso!',
                        text: 'As configurações de cobrança foram atualizadas.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            } else {
                const errorMsg = result.error || result.message || 'Erro ao salvar configurações.';
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Atenção',
                        text: errorMsg,
                        confirmButtonText: 'Entendido'
                    });
                } else if (window.Toastify) {
                    Toastify({
                        text: "✕ " + errorMsg,
                        duration: 4000,
                        gravity: "top",
                        position: "right",
                        style: { background: "#dc3545" }
                    }).showToast();
                }
            }
        } catch (error) {
            console.error('Erro na requisição:', error);
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Falha de Conexão',
                    text: 'Não foi possível se comunicar com o servidor. Verifique sua conexão.',
                    confirmButtonText: 'Ok'
                });
            }
        } finally {
            saveTriggers.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml || '<i class="bi bi-check2-circle me-1"></i> Salvar Configuração';
            });
        }
    }

    saveTriggers.forEach(btn => {
        btn.addEventListener('click', saveConfig);
    });
});
</script>
@endpush
