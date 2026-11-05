@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'Programa de Afiliados')

@push('head')
<style>
/* ==========================================================================
   ESTILOS PREMIUM PADRONIZADOS - SUPORTE TOTAL A DARK / LIGHT MODE
   ========================================================================== */
.affiliate-hero {
    border: 1px solid var(--app-border-soft, var(--bs-border-color));
    border-radius: var(--app-radius-premium, 16px);
    background: linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 8%, transparent), transparent 45%),
                var(--app-surface-bg, var(--bs-body-bg));
    padding: 1.5rem;
    box-shadow: var(--app-shadow-premium, 0 4px 20px rgba(0, 0, 0, 0.04));
    position: relative;
    overflow: hidden;
}

.affiliate-card {
    background: var(--app-surface-bg, var(--bs-body-bg));
    border: 1px solid var(--app-border-soft, var(--bs-border-color));
    border-radius: var(--app-radius-premium, 14px);
    box-shadow: var(--app-shadow-premium, 0 2px 12px rgba(0, 0, 0, 0.03));
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.affiliate-kpi-card {
    background: var(--app-surface-bg, var(--bs-body-bg));
    border: 1px solid var(--app-border-soft, var(--bs-border-color));
    border-radius: 14px;
    padding: 1.25rem;
    height: 100%;
    position: relative;
    transition: transform 0.2s ease, border-color 0.2s ease;
}

.affiliate-kpi-card:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--bs-primary) 40%, var(--app-border-soft, var(--bs-border-color)));
}

.kpi-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}

/* Stepper Responsivo e Adaptativo ao Tema */
.stepper-track-wrap {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 680px;
    margin: 1.5rem auto 1.5rem;
}

.stepper-line-bg {
    position: absolute;
    top: 22px;
    left: 12%;
    right: 12%;
    height: 3px;
    background: var(--bs-border-color);
    z-index: 1;
}

.stepper-line-fill {
    position: absolute;
    top: 22px;
    left: 12%;
    width: 50%;
    height: 3px;
    background: var(--bs-warning);
    z-index: 2;
    transition: width 0.4s ease;
}

.step-node {
    position: relative;
    z-index: 3;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    width: 140px;
}

.step-circle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    background: var(--app-surface-bg, var(--bs-body-bg));
    border: 2px solid var(--bs-border-color);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.step-node.completed .step-circle {
    background: var(--bs-success);
    border-color: var(--bs-success);
    color: #ffffff;
}

.step-node.active .step-circle {
    background: var(--bs-warning);
    border-color: var(--bs-warning);
    color: #1e293b;
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--bs-warning) 25%, transparent);
    animation: pulseStep 2s infinite;
}

.step-node.upcoming .step-circle {
    background: var(--app-surface-bg, var(--bs-body-bg));
    border-color: var(--bs-border-color);
    color: var(--bs-secondary-color);
}

@keyframes pulseStep {
    0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--bs-warning) 40%, transparent); }
    70% { box-shadow: 0 0 0 8px transparent; }
    100% { box-shadow: 0 0 0 0 transparent; }
}

/* Caixa de Código e Cupom */
.copy-pill {
    background: color-mix(in srgb, var(--bs-primary) 8%, var(--app-surface-bg, var(--bs-body-bg)));
    border: 1px dashed color-mix(in srgb, var(--bs-primary) 40%, transparent);
    border-radius: 8px;
    padding: 0.4rem 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Alertas e Caixas de Notificação */
.notice-box {
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 1px solid transparent;
}

.notice-box-warning {
    background: color-mix(in srgb, var(--bs-warning) 10%, var(--app-surface-bg, var(--bs-body-bg)));
    border-color: color-mix(in srgb, var(--bs-warning) 30%, transparent);
    color: var(--bs-body-color);
}

.notice-box-danger {
    background: color-mix(in srgb, var(--bs-danger) 10%, var(--app-surface-bg, var(--bs-body-bg)));
    border-color: color-mix(in srgb, var(--bs-danger) 30%, transparent);
    color: var(--bs-body-color);
}

.notice-box-info {
    background: color-mix(in srgb, var(--bs-info) 10%, var(--app-surface-bg, var(--bs-body-bg)));
    border-color: color-mix(in srgb, var(--bs-info) 30%, transparent);
    color: var(--bs-body-color);
}

.benefit-card {
    background: color-mix(in srgb, var(--bs-primary) 4%, var(--app-surface-bg, var(--bs-body-bg)));
    border: 1px solid var(--app-border-soft, var(--bs-border-color));
    border-radius: 12px;
    padding: 1rem;
    height: 100%;
}
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

    {{-- =========================================================================
         TOPO: HERO COM TÍTULO, SUBTÍTULO E BADGE DE STATUS GLOBAL
         ========================================================================= --}}
    <div class="affiliate-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-inline-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill small fw-semibold">
                        <i class="bi bi-stars me-1"></i> Programa de Parceiros
                    </span>
                    @if($affiliate && $affiliate->split_enabled)
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill small fw-semibold">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Split Mercado Pago Ativo
                        </span>
                    @endif
                </div>
                <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2 text-body">
                    <i class="bi bi-people-fill text-primary"></i> Programa de Afiliados
                </h1>
                <p class="text-secondary mb-0 small">
                    Indique clientes para a plataforma e receba comissões automáticas recorrentes com transparência e split direto no Mercado Pago.
                </p>
            </div>

            <div>
                @if($affiliate && $affiliate->isActive())
                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-6 px-3 py-2 shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i> Cadastro Aprovado e Ativo
                    </span>
                @elseif($affiliate && $affiliate->isPending())
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-6 px-3 py-2 shadow-sm">
                        <i class="bi bi-hourglass-split me-1"></i> Cadastro em Análise
                    </span>
                @elseif($affiliate && $affiliate->isRejected())
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-6 px-3 py-2 shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Pendência / Revisão Necessária
                    </span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-6 px-3 py-2 shadow-sm">
                        <i class="bi bi-info-circle me-1"></i> Inscrição Aberta
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- =========================================================================
         CENÁRIO 1: O CLIENTE AINDA NÃO SE INSCREVEU NO PROGRAMA
         ========================================================================= --}}
    @if(! $affiliate)
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="affiliate-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-primary px-3 py-1 rounded-pill">Parceria Oficial</span>
                        <span class="badge bg-info-subtle text-info-emphasis rounded-pill">Split Automático Mercado Pago</span>
                    </div>

                    <h2 class="h4 fw-bold mb-3 text-body">Monetize sua rede com a melhor plataforma de cobrança e automação via WhatsApp</h2>
                    <p class="text-secondary small leading-relaxed">
                        Como afiliado oficial, você recebe um link exclusivo de divulgação (`/a/seunome`) e um cupom promocional que garante desconto na 1ª compra dos seus indicados. Você acompanha cada clique, visita e cliente convertido em tempo real pelo seu painel.
                    </p>

                    <div class="row g-3 mt-1 mb-4">
                        <div class="col-sm-6">
                            <div class="benefit-card">
                                <div class="fs-4 text-primary mb-2"><i class="bi bi-cash-coin"></i></div>
                                <h6 class="fw-bold mb-1 text-body">Comissões Recorrentes</h6>
                                <p class="text-secondary small mb-0">Ganhe até <strong>{{ number_format((float)($globalSettings['default_commission_pct'] ?? 20), 0) }}%</strong> em cada mensalidade paga pelos seus indicados.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="benefit-card">
                                <div class="fs-4 text-success mb-2"><i class="bi bi-lightning-charge-fill"></i></div>
                                <h6 class="fw-bold mb-1 text-body">Split Direto Mercado Pago</h6>
                                <p class="text-secondary small mb-0">Receba a sua comissão automaticamente creditada na sua conta Mercado Pago no momento da compra.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="benefit-card">
                                <div class="fs-4 text-warning mb-2"><i class="bi bi-tag-fill"></i></div>
                                <h6 class="fw-bold mb-1 text-body">Cupom Promocional</h6>
                                <p class="text-secondary small mb-0">Seus indicados recebem cupom de desconto automático na 1ª fatura, acelerando a conversão.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="benefit-card">
                                <div class="fs-4 text-info mb-2"><i class="bi bi-graph-up-arrow"></i></div>
                                <h6 class="fw-bold mb-1 text-body">KPIs e Métricas em Tempo Real</h6>
                                <p class="text-secondary small mb-0">Acompanhe cliques, cadastros, vendas e saldos com relatórios e gráficos interativos 24h.</p>
                            </div>
                        </div>
                    </div>

                    <div class="notice-box notice-box-info d-flex align-items-center gap-3">
                        <i class="bi bi-shield-check fs-2 text-info flex-shrink-0"></i>
                        <div class="small">
                            <strong class="d-block text-body mb-1">Processo de Inscrição Ágil e Seguro:</strong>
                            Seu cadastro passará por uma rápida revisão da equipe de suporte. Assim que aprovado, você receberá uma confirmação automática por <strong>WhatsApp</strong> e <strong>E-mail</strong> com seus links e cupons liberados!
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="affiliate-card">
                    <div class="p-3 border-bottom d-flex align-items-center gap-2">
                        <i class="bi bi-pencil-square text-primary fs-5"></i>
                        <h5 class="h6 fw-bold mb-0 text-body">Formulário de Inscrição</h5>
                    </div>
                    <div class="p-4">
                        <form id="frm-affiliate-apply" class="frm-affiliate-apply">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-body">Canal Principal de Divulgação <span class="text-danger">*</span></label>
                                <select name="promotion_channel" class="form-select" required>
                                    <option value="">Selecione o canal principal...</option>
                                    <option value="Redes Sociais (Instagram, TikTok, Facebook)">Redes Sociais (Instagram, TikTok, Facebook)</option>
                                    <option value="WhatsApp / Grupos e Listas de Transmissão">WhatsApp / Grupos e Listas de Transmissão</option>
                                    <option value="YouTube / Produção de Conteúdo e Vídeos">YouTube / Produção de Conteúdo e Vídeos</option>
                                    <option value="Tráfego Pago (Google Ads / Meta Ads)">Tráfego Pago (Google Ads / Meta Ads)</option>
                                    <option value="Indicação Direta para Meus Próprios Clientes">Indicação Direta para Meus Próprios Clientes</option>
                                    <option value="Agência / Consultoria de Marketing">Agência / Consultoria de Marketing</option>
                                    <option value="Outro">Outro Canal</option>
                                </select>
                                <div class="form-text small text-secondary">Onde você pretende concentrar a divulgação do seu link.</div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-sm-5">
                                    <label class="form-label fw-semibold small text-body">Tipo Chave PIX <span class="text-danger">*</span></label>
                                    <select name="pix_type" class="form-select" required>
                                        <option value="cpf">CPF</option>
                                        <option value="cnpj">CNPJ</option>
                                        <option value="email">E-mail</option>
                                        <option value="phone">Telefone</option>
                                        <option value="random">Aleatória</option>
                                    </select>
                                </div>
                                <div class="col-sm-7">
                                    <label class="form-label fw-semibold small text-body">Chave PIX p/ Recebimento <span class="text-danger">*</span></label>
                                    <input type="text" name="pix_key" class="form-control" placeholder="Chave para comissões" required>
                                </div>
                            </div>

                            <div class="mb-3 p-3 rounded border bg-body-tertiary">
                                <label class="form-label fw-semibold small d-flex justify-content-between align-items-center mb-1 text-body">
                                    <span><i class="bi bi-wallet2 text-success me-1"></i> Conta Mercado Pago (Split Automático)</span>
                                    <span class="badge bg-secondary-subtle text-secondary">Opcional</span>
                                </label>
                                <input type="text" name="mp_collector_id" class="form-control" placeholder="Ex: Collector ID da sua conta MP">
                                <div class="form-text small text-secondary">
                                    Informe seu ID de vendedor ou da conta Mercado Pago para receber comissões via split instantâneo direto no checkout.
                                </div>
                            </div>

                            <div class="mb-4 form-check">
                                <input type="checkbox" name="terms_accepted" value="1" class="form-check-input" id="check-terms" required>
                                <label class="form-check-label small text-secondary" for="check-terms">
                                    Declaro que li e concordo com as diretrizes do programa de afiliados, comprometendo-me a não praticar spam ou publicidade enganosa.
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                                <i class="bi bi-send-fill"></i> Submeter Inscrição para Análise
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    {{-- =========================================================================
         CENÁRIO 2: INSCRIÇÃO PENDENTE DE ANÁLISE
         ========================================================================= --}}
    @elseif($affiliate->isPending())
        <div class="affiliate-card p-4 p-md-5 mb-4 text-center">
            <div class="d-inline-flex p-3 rounded-circle bg-warning-subtle text-warning-emphasis mb-3 shadow-sm">
                <i class="bi bi-hourglass-split display-5"></i>
            </div>
            <h2 class="h4 fw-bold mb-2 text-body">Sua Inscrição está em Análise pela Administração</h2>
            <p class="text-secondary mx-auto mb-4 small" style="max-width: 650px;">
                Recebemos sua solicitação de afiliação com sucesso! Nossa equipe está validando os canais e informações informadas para liberar seus links oficiais e seu cupom exclusivo de desconto.
            </p>

            {{-- STEPPER VISUAL DINÂMICO --}}
            <div class="stepper-track-wrap">
                <div class="stepper-line-bg"></div>
                <div class="stepper-line-fill"></div>

                <div class="step-node completed">
                    <div class="step-circle"><i class="bi bi-check-lg"></i></div>
                    <div class="small fw-bold text-body">Inscrição Enviada</div>
                    <div class="text-secondary smaller">{{ $affiliate->applied_at ? $affiliate->applied_at->format('d/m/Y H:i') : 'Submetida' }}</div>
                </div>

                <div class="step-node active">
                    <div class="step-circle"><i class="bi bi-hourglass-split"></i></div>
                    <div class="small fw-bold text-warning-emphasis">Análise da Equipe</div>
                    <div class="text-secondary smaller">Em andamento</div>
                </div>

                <div class="step-node upcoming">
                    <div class="step-circle"><i class="bi bi-link-45deg"></i></div>
                    <div class="small fw-semibold text-secondary">Links Liberados</div>
                    <div class="text-secondary smaller">Após aprovação</div>
                </div>
            </div>

            {{-- RESUMO DOS DADOS INFORMADOS --}}
            <div class="card bg-body-tertiary border text-start mx-auto p-3 p-md-4 mb-4" style="max-width: 640px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 small text-uppercase text-secondary d-flex align-items-center gap-1">
                        <i class="bi bi-info-circle text-primary"></i> Resumo da Solicitação Cadastrada
                    </h6>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEditPending">
                        <i class="bi bi-pencil me-1"></i> Corrigir Dados
                    </button>
                </div>

                <div class="row g-3 small">
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Canal de Divulgação:</span>
                        <strong class="text-body">{{ $affiliate->promotion_channel ?: 'Divulgação Geral' }}</strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Chave PIX Cadastrada:</span>
                        <strong class="text-body">{{ $affiliate->payment_info['pix_key'] ?? 'Não informada' }}</strong>
                        <span class="badge bg-secondary-subtle text-secondary ms-1">{{ strtoupper($affiliate->payment_info['pix_type'] ?? 'PIX') }}</span>
                    </div>

                    @if(!empty($affiliate->mp_collector_id))
                    <div class="col-12 pt-2 border-top">
                        <span class="text-secondary d-block">Conta Mercado Pago p/ Split:</span>
                        <strong class="text-body"><i class="bi bi-wallet2 text-success me-1"></i> Collector ID: {{ $affiliate->mp_collector_id }}</strong>
                    </div>
                    @endif

                    @if($affiliate->rejection_reason)
                    <div class="col-12 pt-2 border-top">
                        <div class="notice-box notice-box-warning p-2">
                            <strong class="d-block mb-1 text-warning-emphasis"><i class="bi bi-chat-left-dots-fill me-1"></i> Orientações da Administração:</strong>
                            <span class="small">{{ $affiliate->rejection_reason }}</span>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- FORMULÁRIO DE RETIFICAÇÃO EXPANSÍVEL --}}
                <div class="collapse mt-3 pt-3 border-top" id="collapseEditPending">
                    <form id="frm-affiliate-apply-pending" class="frm-affiliate-apply">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-body">Canal de Divulgação</label>
                                <input type="text" name="promotion_channel" class="form-control form-control-sm" value="{{ $affiliate->promotion_channel }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-body">Tipo PIX</label>
                                <select name="pix_type" class="form-select form-select-sm" required>
                                    @php $cType = $affiliate->payment_info['pix_type'] ?? 'cpf'; @endphp
                                    <option value="cpf" {{ $cType === 'cpf' ? 'selected' : '' }}>CPF</option>
                                    <option value="cnpj" {{ $cType === 'cnpj' ? 'selected' : '' }}>CNPJ</option>
                                    <option value="email" {{ $cType === 'email' ? 'selected' : '' }}>E-mail</option>
                                    <option value="phone" {{ $cType === 'phone' ? 'selected' : '' }}>Telefone</option>
                                    <option value="random" {{ $cType === 'random' ? 'selected' : '' }}>Aleatória</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-body">Chave PIX</label>
                                <input type="text" name="pix_key" class="form-control form-control-sm" value="{{ $affiliate->payment_info['pix_key'] ?? '' }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-body">Mercado Pago Collector ID (Split)</label>
                                <input type="text" name="mp_collector_id" class="form-control form-control-sm" value="{{ $affiliate->mp_collector_id }}">
                            </div>
                            <input type="hidden" name="terms_accepted" value="1">
                            <div class="col-12 mt-2 text-end">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-save me-1"></i> Atualizar Solicitação
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="notice-box notice-box-info d-inline-flex align-items-center gap-2 p-2 px-3 small">
                <i class="bi bi-bell-fill text-info"></i>
                <span>Você receberá uma mensagem automática no <strong>WhatsApp</strong> e no seu <strong>E-mail</strong> assim que seu cadastro for aprovado!</span>
            </div>
        </div>

    {{-- =========================================================================
         CENÁRIO 3: INSCRIÇÃO REJEITADA OU COM PENDÊNCIA APONTADA PELO ADMIN
         ========================================================================= --}}
    @elseif($affiliate->isRejected())
        <div class="affiliate-card p-4 mb-4">
            <div class="notice-box notice-box-danger d-flex align-items-start gap-3 mb-4">
                <i class="bi bi-exclamation-octagon-fill fs-2 text-danger flex-shrink-0 mt-1"></i>
                <div>
                    <h3 class="h5 fw-bold mb-1 text-danger">Inscrição Não Aprovada ou com Pendência</h3>
                    <p class="small text-secondary mb-2">
                        Sua solicitação de afiliação não pôde ser ativada pelos nossos administradores pelo seguinte motivo:
                    </p>
                    <div class="p-3 rounded border border-danger-subtle bg-body fw-semibold text-body mb-2 small">
                        "{{ $affiliate->rejection_reason ?: 'Dados de divulgação ou recebimento insuficientes para validação.' }}"
                    </div>
                    <p class="small text-secondary mb-0">
                        Você pode corrigir ou atualizar suas informações no formulário abaixo e reenviar para uma nova análise sem complicações.
                    </p>
                </div>
            </div>

            <div class="card border bg-body-tertiary">
                <div class="card-header bg-transparent py-3">
                    <h4 class="h6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-repeat text-primary"></i> Atualizar Dados e Reenviar para Análise
                    </h4>
                </div>
                <div class="card-body p-4">
                    <form id="frm-affiliate-apply-rejected" class="frm-affiliate-apply">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-body">Canal Principal de Divulgação <span class="text-danger">*</span></label>
                                <input type="text" name="promotion_channel" class="form-control" value="{{ $affiliate->promotion_channel }}" placeholder="Ex: Instagram, WhatsApp, Tráfego Pago" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small text-body">Tipo Chave PIX <span class="text-danger">*</span></label>
                                <select name="pix_type" class="form-select" required>
                                    @php $rejType = $affiliate->payment_info['pix_type'] ?? 'cpf'; @endphp
                                    <option value="cpf" {{ $rejType === 'cpf' ? 'selected' : '' }}>CPF</option>
                                    <option value="cnpj" {{ $rejType === 'cnpj' ? 'selected' : '' }}>CNPJ</option>
                                    <option value="email" {{ $rejType === 'email' ? 'selected' : '' }}>E-mail</option>
                                    <option value="phone" {{ $rejType === 'phone' ? 'selected' : '' }}>Telefone</option>
                                    <option value="random" {{ $rejType === 'random' ? 'selected' : '' }}>Aleatória</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small text-body">Chave PIX <span class="text-danger">*</span></label>
                                <input type="text" name="pix_key" class="form-control" value="{{ $affiliate->payment_info['pix_key'] ?? '' }}" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold small text-body">Conta Mercado Pago p/ Split Automático (Collector ID)</label>
                                <input type="text" name="mp_collector_id" class="form-control" value="{{ $affiliate->mp_collector_id }}" placeholder="Opcional: ID de usuário ou conta Mercado Pago">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" name="terms_accepted" value="1" class="form-check-input" id="check-reterms" checked required>
                                    <label class="form-check-label small text-secondary" for="check-reterms">
                                        Concordo com os termos do programa de afiliados e confirmo a veracidade dos dados informados.
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-warning text-dark px-4 py-2 fw-semibold shadow-sm">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Reenviar para Nova Análise
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    {{-- =========================================================================
         CENÁRIO 4: AFILIADO ATIVO E HOMOLOGADO
         ========================================================================= --}}
    @else
        {{-- CARD PRINCIPAL: LINK DE DIVULGAÇÃO & CUPOM --}}
        <div class="affiliate-card p-4 mb-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7 border-end-lg">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label fw-bold mb-0 text-primary d-flex align-items-center gap-2">
                            <i class="bi bi-link-45deg fs-5"></i> Seu Link Exclusivo de Indicação
                        </label>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                            <i class="bi bi-circle-fill me-1 small"></i> Rastreamento Ativo
                        </span>
                    </div>

                    <div class="input-group mb-2">
                        <input type="text" class="form-control font-monospace bg-body-tertiary" value="{{ $affiliate->referral_url }}" readonly id="ref-url">
                        <button class="btn btn-primary px-3 shadow-sm" onclick="copyRefUrl()">
                            <i class="bi bi-clipboard me-1"></i> Copiar Link
                        </button>
                        <a href="{{ $affiliate->referral_url }}" target="_blank" class="btn btn-outline-secondary px-3" title="Testar link em nova aba">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
                    <p class="small text-secondary mb-0">
                        Os visitantes que acessarem este link terão seu cupom de desconto aplicado automaticamente na contratação de qualquer plano.
                    </p>
                </div>

                <div class="col-lg-5">
                    <div class="p-3 border rounded bg-body-tertiary">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-secondary text-uppercase">Cupom de Desconto</span>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Aplica no Cadastro</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-4 font-monospace fw-bold text-primary">{{ $affiliate->referral_code }}</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="copyRefCode('{{ $affiliate->referral_code }}')">
                                <i class="bi bi-clipboard me-1"></i> Copiar
                            </button>
                        </div>
                        <div class="small text-secondary mt-2">
                            Comissão: <strong class="text-body">{{ $affiliate->commission_formatted }}</strong> ({{ $affiliate->commission_type_label }}) |
                            Desconto cliente: <strong class="text-body">{{ $affiliate->discount_formatted }}</strong>
                        </div>
                        @if($affiliate->split_enabled)
                            <div class="mt-2 pt-2 border-top smaller text-success d-flex align-items-center gap-1">
                                <i class="bi bi-lightning-charge-fill"></i>
                                <span><strong>Split Automático:</strong> Vinculado na conta Mercado Pago (Collector ID: {{ $affiliate->mp_collector_id ?: 'Conectado' }})</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- CARDS DE KPIS --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="affiliate-kpi-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-secondary small fw-medium">Cliques (30d)</div>
                            <div class="fs-3 fw-bold text-body mt-1">{{ number_format($stats['clicks_30d'] ?? 0, 0, ',', '.') }}</div>
                            <div class="text-secondary smaller mt-1"><i class="bi bi-eye me-1"></i>Visitantes únicos</div>
                        </div>
                        <div class="kpi-icon-wrapper bg-primary-subtle text-primary">
                            <i class="bi bi-cursor-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="affiliate-kpi-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-secondary small fw-medium">Conversões (30d)</div>
                            <div class="fs-3 fw-bold text-body mt-1">{{ number_format($stats['conversions_30d'] ?? 0, 0, ',', '.') }}</div>
                            <div class="text-secondary smaller mt-1"><i class="bi bi-person-check me-1"></i>Clientes indicados</div>
                        </div>
                        <div class="kpi-icon-wrapper bg-success-subtle text-success">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="affiliate-kpi-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-secondary small fw-medium">Comissão Pendente</div>
                            <div class="fs-3 fw-bold text-warning-emphasis mt-1">R$ {{ number_format($stats['commission_pending'] ?? 0, 2, ',', '.') }}</div>
                            <div class="text-secondary smaller mt-1"><i class="bi bi-clock-history me-1"></i>Em processamento</div>
                        </div>
                        <div class="kpi-icon-wrapper bg-warning-subtle text-warning-emphasis">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="affiliate-kpi-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-secondary small fw-medium">Saldo Disponível</div>
                            <div class="fs-3 fw-bold text-success mt-1">R$ {{ number_format($affiliate->balance ?? 0, 2, ',', '.') }}</div>
                            <div class="text-secondary smaller mt-1"><i class="bi bi-wallet2 me-1"></i>Pronto p/ saque PIX</div>
                        </div>
                        <div class="kpi-icon-wrapper bg-info-subtle text-info-emphasis">
                            <i class="bi bi-wallet-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- GRÁFICO INTERATIVO DE DESEMPENHO (CLIQUES VS CONVERSÕES) --}}
        @if(!empty($chartData))
        <div class="affiliate-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="h6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                        <i class="bi bi-graph-up text-primary"></i> Desempenho dos Últimos 15 Dias
                    </h5>
                    <p class="text-secondary smaller mb-0">Evolução diária de cliques no seu link e conversões de novos clientes.</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle small px-2 py-1">
                        <i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i> Cliques
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle small px-2 py-1">
                        <i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i> Conversões
                    </span>
                </div>
            </div>
            <div style="height: 250px; position: relative;">
                <canvas id="affiliatePerformanceChart"></canvas>
            </div>
        </div>
        @endif

        {{-- SEÇÃO DE INDICAÇÕES RECENTES E SOLICITAÇÃO DE SAQUE --}}
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="affiliate-card h-100 d-flex flex-column">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="h6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                            <i class="bi bi-people text-primary"></i> Indicações Recentes
                        </h5>
                        <span class="badge bg-secondary-subtle text-secondary">{{ count($recentReferrals ?? []) }} registros</span>
                    </div>

                    <div class="table-responsive flex-grow-1">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="small text-secondary text-uppercase border-bottom">
                                <tr>
                                    <th>Usuário Indicado</th>
                                    <th>Empresa / Tenant</th>
                                    <th>Data Cadastro</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentReferrals as $r)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center fw-bold small" style="width: 32px; height: 32px;">
                                                {{ strtoupper(substr($r->user?->name ?: 'U', 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-body small">{{ $r->user?->name ?: 'Usuário' }}</div>
                                                <div class="text-secondary smaller">{{ $r->user?->email ?: '-' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small text-body">{{ $r->tenant?->company_name ?? '-' }}</td>
                                    <td class="small text-secondary">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        @if($r->first_payment_done)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-check-circle me-1"></i> Convertido
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                <i class="bi bi-clock me-1"></i> Cadastrado
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-secondary text-center py-4">
                                        <i class="bi bi-inbox fs-2 text-secondary d-block mb-2"></i>
                                        Nenhuma indicação registrada ainda. Compartilhe seu link exclusivo para começar!
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="affiliate-card h-100 d-flex flex-column">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="h6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                            <i class="bi bi-cash-coin text-success"></i> Solicitar Saque via PIX
                        </h5>
                        <span class="text-secondary smaller">Mín: R$ {{ number_format($affiliate->min_withdrawal, 2, ',', '.') }}</span>
                    </div>

                    <div class="p-4 flex-grow-1 d-flex flex-column justify-content-between">
                        @if($affiliate->canWithdraw())
                            <form id="frm-withdraw">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-body">Valor do Saque (R$)</label>
                                    <input type="text" name="amount" class="form-control" inputmode="decimal" required placeholder="Ex: R$ {{ number_format($affiliate->min_withdrawal, 2, ',', '.') }}" max="{{ $affiliate->balance }}">
                                    <div class="form-text small text-secondary">Saldo disponível: R$ {{ number_format($affiliate->balance, 2, ',', '.') }}</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-body">Tipo de Chave PIX</label>
                                    <select name="pix_type" class="form-select">
                                        @php $pType = $affiliate->payment_info['pix_type'] ?? 'cpf'; @endphp
                                        <option value="cpf" {{ $pType === 'cpf' ? 'selected' : '' }}>CPF</option>
                                        <option value="cnpj" {{ $pType === 'cnpj' ? 'selected' : '' }}>CNPJ</option>
                                        <option value="email" {{ $pType === 'email' ? 'selected' : '' }}>E-mail</option>
                                        <option value="phone" {{ $pType === 'phone' ? 'selected' : '' }}>Telefone</option>
                                        <option value="random" {{ $pType === 'random' ? 'selected' : '' }}>Chave Aleatória</option>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-semibold text-body">Chave PIX de Destino</label>
                                    <input type="text" name="pix_key" class="form-control" value="{{ $affiliate->payment_info['pix_key'] ?? '' }}" required>
                                </div>
                                <button type="submit" class="btn btn-success w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                                    <i class="bi bi-send-check"></i> Confirmar Solicitação de Saque
                                </button>
                            </form>
                        @else
                            <div>
                                <div class="notice-box notice-box-info small mb-3">
                                    <i class="bi bi-info-circle me-1 text-info"></i> Saldo atual: <strong class="text-body">R$ {{ number_format($affiliate->balance, 2, ',', '.') }}</strong>. O valor mínimo para solicitar saque é de <strong class="text-body">R$ {{ number_format($affiliate->min_withdrawal, 2, ',', '.') }}</strong>.
                                </div>

                                {{-- BARRA DE PROGRESSO DE SAQUE MÍNIMO --}}
                                @php
                                    $minW = (float) ($affiliate->min_withdrawal ?: 50.00);
                                    $currB = (float) $affiliate->balance;
                                    $pct = $minW > 0 ? min(100, round(($currB / $minW) * 100)) : 100;
                                @endphp
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small text-secondary mb-1">
                                        <span>Meta de Saque Mínimo</span>
                                        <span>{{ $pct }}%</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pct }}%;" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>

                                <div class="p-3 border rounded bg-body-tertiary">
                                    <div class="small text-secondary mb-1">Dados de Recebimento Salvos:</div>
                                    <div class="small fw-semibold text-body">PIX: {{ $affiliate->payment_info['pix_key'] ?? 'Nenhum cadastrado' }} ({{ strtoupper($affiliate->payment_info['pix_type'] ?? 'PIX') }})</div>
                                    @if($affiliate->mp_collector_id)
                                        <div class="small text-secondary mt-2 mb-1">Mercado Pago Collector ID:</div>
                                        <div class="small fw-semibold text-body">{{ $affiliate->mp_collector_id }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 text-center">
                                <span class="smaller text-secondary"><i class="bi bi-shield-lock me-1"></i> Saques transferidos via PIX em até 24h úteis após aprovação.</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- HISTÓRICO DE COMISSÕES GERADAS --}}
        @if(isset($recentCommissions) && count($recentCommissions) > 0)
        <div class="affiliate-card mb-4">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="h6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                    <i class="bi bi-receipt text-primary"></i> Últimas Comissões Geradas
                </h5>
                <span class="badge bg-secondary-subtle text-secondary">{{ count($recentCommissions) }} registros</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="small text-secondary text-uppercase border-bottom">
                        <tr>
                            <th>Fatura / Descrição</th>
                            <th>Valor Base</th>
                            <th>Alíquota</th>
                            <th>Comissão</th>
                            <th>Status</th>
                            <th class="text-end">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentCommissions as $c)
                        <tr>
                            <td>
                                <div class="fw-semibold text-body small">Fatura #{{ $c->tenant_invoice_id ?: '-' }}</div>
                                <div class="text-secondary smaller">{{ $c->notes ?: 'Comissão automática' }}</div>
                            </td>
                            <td class="small text-body">R$ {{ number_format((float) $c->base_amount, 2, ',', '.') }}</td>
                            <td class="small text-secondary">{{ number_format((float) $c->commission_pct, 1, ',', '.') }}%</td>
                            <td class="small fw-bold text-success">R$ {{ number_format((float) $c->commission_amount, 2, ',', '.') }}</td>
                            <td>
                                @if($c->status === 'paid')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle-fill me-1"></i> Split MP Pago
                                    </span>
                                @elseif($c->status === 'approved')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <i class="bi bi-check-all me-1"></i> Aprovada
                                    </span>
                                @elseif($c->status === 'pending')
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                        <i class="bi bi-clock me-1"></i> Pendente
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">{{ ucfirst($c->status) }}</span>
                                @endif
                            </td>
                            <td class="small text-secondary text-end">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    @endif

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
// ==========================================================================
// FUNÇÕES GLOBAIS DE CÓPIA COM SWEETALERT2 / TOASTIFY
// ==========================================================================
function copyRefUrl() {
    const input = document.getElementById('ref-url');
    if (!input) return;
    navigator.clipboard.writeText(input.value);
    if (window.toast?.success) {
        window.toast.success('Link de afiliado copiado para a área de transferência!');
    } else if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Copiado!',
            text: 'Link de afiliado copiado com sucesso!',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
}

function copyRefCode(code) {
    if (!code) return;
    navigator.clipboard.writeText(code);
    if (window.toast?.success) {
        window.toast.success(`Cupom "${code}" copiado!`);
    } else if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Cupom Copiado!',
            text: `Código ${code} copiado com sucesso!`,
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
}

// ==========================================================================
// SUBMISSÃO DE INSCRIÇÃO / REENVIO EM AJAX
// ==========================================================================
document.querySelectorAll('.frm-affiliate-apply, #frm-affiliate-apply').forEach(function(form) {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const originalBtnHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando inscrição...';
        }

        try {
            const fd = new FormData(form);
            const res = await fetch('{{ route("tenant.affiliate.apply") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json'
                },
                body: fd
            });

            const data = await res.json();
            if (data.ok) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Inscrição Submetida!',
                        text: data.message,
                        confirmButtonText: 'Entendido'
                    }).then(() => location.reload());
                } else {
                    window.toast?.success(data.message);
                    setTimeout(() => location.reload(), 1500);
                }
            } else {
                const errorMsg = data.error || (data.errors ? Object.values(data.errors).flat().join('<br>') : 'Erro ao processar inscrição.');
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Atenção', html: errorMsg });
                } else {
                    window.toast?.error(errorMsg);
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalBtnHtml;
                }
            }
        } catch (err) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Erro de Comunicação', text: err.message || 'Falha ao conectar com o servidor.' });
            } else {
                window.toast?.error('Falha de conexão.');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
        }
    });
});

// ==========================================================================
// SOLICITAÇÃO DE SAQUE PIX COM CONFIRMAÇÃO SWEETALERT2
// ==========================================================================
document.getElementById('frm-withdraw')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;

    const proceed = await (typeof Swal !== 'undefined'
        ? Swal.fire({
            title: 'Confirmar Solicitação de Saque?',
            text: 'Deseja transferir o saldo disponível via PIX para a chave informada?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, solicitar saque',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981'
        }).then(r => r.isConfirmed)
        : Promise.resolve(true));

    if (!proceed) return;

    const fd = new FormData(form);
    if (window.adminUI?.parseMoney) {
        fd.set('amount', window.adminUI.parseMoney(fd.get('amount')));
    }

    try {
        const r = await fetch('{{ route("tenant.affiliate.withdraw") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json'
            },
            body: fd,
        });
        const d = await r.json();
        if (d.ok) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Saque Solicitado!',
                    text: d.message,
                    timer: 2500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                window.toast?.success(d.message);
                setTimeout(() => location.reload(), 1500);
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Não foi possível sacar', text: d.error || 'Erro ao processar saque.' });
            } else {
                window.toast?.error(d.error || 'Erro ao processar saque.');
            }
        }
    } catch (err) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: err.message || 'Falha ao conectar com o servidor.' });
        } else {
            window.toast?.error(err.message || 'Erro de conexão.');
        }
    }
});

// ==========================================================================
// RENDERIZAÇÃO DO GRÁFICO CHART.JS COM CORES ADAPTATIVAS DARK/LIGHT
// ==========================================================================
@if(!empty($chartData))
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('affiliatePerformanceChart');
    if (!canvas) return;

    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark'
        || window.matchMedia('(prefers-color-scheme: dark)').matches;

    const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    const ctx = canvas.getContext('2d');

    const primaryGrad = ctx.createLinearGradient(0, 0, 0, 220);
    primaryGrad.addColorStop(0, isDark ? 'rgba(59, 130, 246, 0.35)' : 'rgba(59, 130, 246, 0.2)');
    primaryGrad.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

    const successGrad = ctx.createLinearGradient(0, 0, 0, 220);
    successGrad.addColorStop(0, isDark ? 'rgba(16, 185, 129, 0.35)' : 'rgba(16, 185, 129, 0.2)');
    successGrad.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($chartData['labels'] ?? []),
            datasets: [
                {
                    label: 'Cliques',
                    data: @json($chartData['clicks'] ?? []),
                    borderColor: '#3b82f6',
                    backgroundColor: primaryGrad,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#3b82f6'
                },
                {
                    label: 'Conversões',
                    data: @json($chartData['conversions'] ?? []),
                    borderColor: '#10b981',
                    backgroundColor: successGrad,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#10b981'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: isDark ? '#1e293b' : '#ffffff',
                    titleColor: isDark ? '#f8fafc' : '#0f172a',
                    bodyColor: isDark ? '#cbd5e1' : '#334155',
                    borderColor: isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                    usePointStyle: true
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            scales: {
                x: {
                    grid: {
                        color: gridColor,
                        drawBorder: false
                    },
                    ticks: {
                        color: textColor,
                        font: { size: 11 }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: gridColor,
                        drawBorder: false
                    },
                    ticks: {
                        color: textColor,
                        font: { size: 11 },
                        precision: 0
                    }
                }
            }
        }
    });
});
@endif
</script>
@endpush
