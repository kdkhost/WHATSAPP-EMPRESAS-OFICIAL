@extends('admin.layouts.app')
@section('title', 'Detalhes do Tenant - ' . $tenant->company_name)

@push('styles')
<style>
.tenant-status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}
.tenant-status-dot.active {
    background-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
    animation: pulseStatus 2s infinite;
}
.tenant-status-dot.trial {
    background-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
}
.tenant-status-dot.suspended {
    background-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25);
}
@keyframes pulseStatus {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.kpi-card-premium {
    position: relative;
    overflow: hidden;
    border-radius: 14px;
    transition: transform .2s ease, box-shadow .2s ease;
}
.kpi-card-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.kpi-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
}

.nav-tabs-premium {
    border-bottom: 2px solid var(--bs-border-color);
    gap: .5rem;
}
.nav-tabs-premium .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--bs-secondary-color);
    font-weight: 600;
    padding: .75rem 1.25rem;
    border-radius: 0;
    transition: all .2s;
    background: transparent;
}
.nav-tabs-premium .nav-link:hover {
    color: var(--bs-primary);
    border-bottom-color: rgba(var(--bs-primary-rgb), 0.4);
}
.nav-tabs-premium .nav-link.active {
    color: var(--bs-primary);
    border-bottom-color: var(--bs-primary);
    background: transparent;
}

.referral-link-box {
    background: color-mix(in srgb, var(--bs-body-bg) 90%, var(--bs-primary) 10%);
    border: 1px dashed var(--bs-primary);
    border-radius: 10px;
    padding: .75rem 1rem;
}

.chart-card-container {
    position: relative;
    min-height: 260px;
}
</style>
@endpush

@section('content')
<div class="container-fluid">

    {{-- ========== HEADER / HERO PREMIUM ========== --}}
    <x-ui.page-header
        :title="$tenant->company_name"
        subtitle="Painel operacional completo, métricas de faturamento, instâncias de WhatsApp e gestão avançada de afiliados."
        :eyebrow="'Tenant #' . $tenant->id . ' • ' . ucfirst($tenant->status)"
        class="mb-4"
    >
        <x-slot:actions>
            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <a href="{{ route('admin.tenancy.tenants.edit', $tenant->id) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil-square me-1"></i> Editar Tenant
                </a>

                @if($tenant->user_id)
                    <form method="POST" action="{{ route('admin.tenancy.tenants.assist.start', $tenant->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm shadow-sm">
                            <i class="bi bi-person-check me-1"></i> Login Supervisionado
                        </button>
                    </form>
                    @if($tenant->user?->two_factor_enabled)
                        <button type="button" class="btn btn-warning btn-sm" id="btn-disable-2fa">
                            <i class="bi bi-shield-x me-1"></i> Desativar 2FA
                        </button>
                    @endif
                @endif

                @if($tenant->status !== 'suspended')
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-suspend">
                        <i class="bi bi-pause-circle me-1"></i> Suspender
                    </button>
                @endif

                @if($tenant->status !== 'active')
                    <button type="button" class="btn btn-success btn-sm" id="btn-activate">
                        <i class="bi bi-play-circle me-1"></i> Ativar
                    </button>
                @endif

                <a href="{{ route('admin.affiliates.index') }}" class="btn btn-outline-info btn-sm" title="Central de Afiliados">
                    <i class="bi bi-people-fill me-1"></i> Central Afiliados
                </a>

                <a href="{{ route('admin.tenancy.tenants') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Voltar
                </a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== TOP KPI CARDS ========== --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: Status & Plano --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100 kpi-card-premium border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon-wrapper bg-primary-subtle text-primary">
                        <i class="bi bi-building"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-uppercase small text-muted fw-semibold">Plano Contratado</div>
                        <div class="fs-5 fw-bold text-truncate">{{ $tenant->plan->name ?? 'Sem Plano' }}</div>
                        <div class="small text-muted d-flex align-items-center gap-1 mt-1">
                            <span class="tenant-status-dot {{ $tenant->status }}"></span>
                            <span class="fw-medium text-capitalize">{{ $tenant->status }}</span>
                            @if($tenant->current_period_end)
                                <span>• Até {{ $tenant->current_period_end->format('d/m/Y') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Instâncias WhatsApp --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100 kpi-card-premium border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon-wrapper bg-success-subtle text-success">
                        <i class="bi bi-whatsapp"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-uppercase small text-muted fw-semibold">Instâncias WhatsApp</div>
                        <div class="fs-5 fw-bold text-truncate">
                            {{ $tenant->instances->count() }} <span class="fs-6 text-muted fw-normal">/ {{ $tenant->plan->max_instances ?? '∞' }}</span>
                        </div>
                        <div class="small text-muted mt-1">
                            @php
                                $connectedCount = $tenant->instances->where('status', 'connected')->count();
                            @endphp
                            <span class="text-success fw-semibold">{{ $connectedCount }} conectada(s)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 3: Faturamento & Faturas --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100 kpi-card-premium border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon-wrapper bg-info-subtle text-info">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-uppercase small text-muted fw-semibold">Total Faturado</div>
                        <div class="fs-5 fw-bold text-truncate">
                            R$ {{ number_format($analytics['total_paid'] ?? 0, 2, ',', '.') }}
                        </div>
                        <div class="small text-muted mt-1">
                            <span class="text-success fw-medium">{{ $analytics['paid_count'] ?? 0 }} pagas</span>
                            @if(($analytics['pending_count'] ?? 0) > 0)
                                <span class="text-warning fw-medium">• {{ $analytics['pending_count'] }} pendente(s)</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 4: Programa de Afiliado --}}
        <div class="col-sm-6 col-xl-3">
            @php
                $isPromoter = $affiliateStats['is_promoter'] ?? false;
                $aff = $affiliateStats['affiliate'] ?? null;
            @endphp
            <div class="card shadow-sm h-100 kpi-card-premium border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon-wrapper {{ $isPromoter ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary' }}">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-uppercase small text-muted fw-semibold">Programa Afiliado</div>
                        @if($isPromoter)
                            <div class="fs-5 fw-bold text-truncate text-success">
                                {{ $aff->conversions ?? 0 }} <span class="fs-6 text-muted fw-normal">vendas</span>
                            </div>
                            <div class="small text-muted mt-1">
                                <span>{{ $aff->clicks ?? 0 }} cliques • CR {{ $aff->conversion_rate ?? 0 }}%</span>
                            </div>
                        @else
                            <div class="fs-6 fw-bold text-truncate text-muted">Não Ativado</div>
                            <div class="small text-primary mt-1" style="cursor: pointer;" onclick="document.getElementById('tab-affiliate-btn').click();">
                                <i class="bi bi-plus-circle me-1"></i> Ativar como Divulgador
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== NAVEGAÇÃO EM ABAS ========== --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent p-0 border-0">
            <ul class="nav nav-tabs nav-tabs-premium px-3 pt-2" id="tenantTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-overview-btn" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab">
                        <i class="bi bi-speedometer2 me-1"></i> Visão Geral & Cadastro
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-affiliate-btn" data-bs-toggle="tab" data-bs-target="#tab-affiliate" type="button" role="tab">
                        <i class="bi bi-people-fill me-1"></i> Programa de Afiliados
                        @if($isPromoter)
                            <span class="badge bg-success ms-1">Ativo</span>
                        @else
                            <span class="badge bg-secondary ms-1">Configurar</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-instances-btn" data-bs-toggle="tab" data-bs-target="#tab-instances" type="button" role="tab">
                        <i class="bi bi-phone me-1"></i> Instâncias WhatsApp ({{ $tenant->instances->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-billing-btn" data-bs-toggle="tab" data-bs-target="#tab-billing" type="button" role="tab">
                        <i class="bi bi-credit-card me-1"></i> Cobrança & Gateways
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-invoices-btn" data-bs-toggle="tab" data-bs-target="#tab-invoices" type="button" role="tab">
                        <i class="bi bi-receipt me-1"></i> Faturas ({{ $tenant->tenantInvoices->count() }})
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="tenantTabsContent">

                {{-- ==================== ABA 1: VISÃO GERAL ==================== --}}
                <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
                    <div class="row g-4">
                        {{-- Informações Cadastrais --}}
                        <div class="col-lg-6">
                            <div class="card border h-100">
                                <div class="card-header bg-transparent fw-semibold">
                                    <i class="bi bi-info-circle me-1 text-primary"></i> Dados Cadastrais da Empresa
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="text-muted small">Razão Social / Empresa</label>
                                            <div class="fw-bold">{{ $tenant->company_name }}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted small">Slug / Identificador</label>
                                            <div class="fw-bold"><code>{{ $tenant->slug }}</code></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted small">Usuário Responsável</label>
                                            <div class="fw-bold">{{ $tenant->user->name ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted small">E-mail Principal</label>
                                            <div class="fw-bold">{{ $tenant->user->email ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted small">Telefone / WhatsApp</label>
                                            <div class="fw-bold">{{ $tenant->user->phone ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted small">CPF / CNPJ</label>
                                            <div class="fw-bold">{{ $tenant->user->document ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="text-muted small">Status da Conta</label>
                                            <div>
                                                @php $statusColors = ['active'=>'success','trial'=>'info','suspended'=>'danger','cancelled'=>'secondary']; @endphp
                                                <span class="badge bg-{{ $statusColors[$tenant->status] ?? 'secondary' }} text-uppercase">
                                                    {{ $tenant->status }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="text-muted small">Autenticação 2FA</label>
                                            <div>
                                                <span class="badge bg-{{ $tenant->user?->two_factor_enabled ? 'success' : 'secondary' }}">
                                                    {{ $tenant->user?->two_factor_enabled ? 'Ativado' : 'Desativado' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="text-muted small">Cadastrado em</label>
                                            <div class="fw-bold">{{ $tenant->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                        </div>
                                    </div>

                                    @if($tenant->custom_domain)
                                    <hr class="my-3">
                                    <div class="alert alert-info py-2 px-3 mb-0 small">
                                        <i class="bi bi-globe me-1"></i> Domínio Personalizado:
                                        <strong>{{ $tenant->custom_domain }}</strong>
                                        <span class="badge bg-info ms-1">{{ $tenant->custom_domain_status ?? 'Pendente' }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Gráfico de Faturamento dos Últimos 6 Meses --}}
                        <div class="col-lg-6">
                            <div class="card border h-100">
                                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold"><i class="bi bi-graph-up me-1 text-success"></i> Faturamento Histórico (Últimos 6 Meses)</span>
                                    <span class="badge bg-success-subtle text-success">R$ {{ number_format($analytics['total_paid'] ?? 0, 2, ',', '.') }}</span>
                                </div>
                                <div class="card-body chart-card-container">
                                    <canvas id="chartTenantRevenue" height="220"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== ABA 2: PROGRAMA DE AFILIADOS ==================== --}}
                <div class="tab-pane fade" id="tab-affiliate" role="tabpanel">
                    
                    {{-- SEÇÃO 1: ESTE TENANT COMO DIVULGADOR / PROMOTOR --}}
                    <div class="card border mb-4">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="fw-bold fs-6"><i class="bi bi-megaphone-fill text-warning me-1"></i> Este Tenant como Promotor / Divulgador</span>
                                <small class="text-muted d-block">Configuração de ganhos, descontos a compradores, tipo de comissão e links de vendas.</small>
                            </div>
                            <div>
                                @if($isPromoter)
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Afiliado Ativo</span>
                                @else
                                    <span class="badge bg-secondary">Afiliado Não Configurado</span>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            @if($isPromoter && $aff)
                                {{-- Link e Código de Afiliado --}}
                                <div class="row g-3 mb-4">
                                    <div class="col-lg-8">
                                        <label class="form-label small fw-bold text-muted mb-1">Link de Divulgação Oficial</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-body-tertiary"><i class="bi bi-link-45deg"></i></span>
                                            <input type="text" class="form-control" id="promoter-link-input" value="{{ $aff->referral_url }}" readonly>
                                            <button class="btn btn-primary" type="button" id="btn-copy-aff-link">
                                                <i class="bi bi-clipboard me-1"></i> Copiar Link
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <label class="form-label small fw-bold text-muted mb-1">Cupom / Código de Indicação</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control fw-bold text-uppercase" value="{{ $aff->referral_code }}" readonly>
                                            <span class="input-group-text bg-success-subtle text-success"><i class="bi bi-tag-fill"></i></span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Mini KPIs do Promotor --}}
                                <div class="row g-3 mb-4">
                                    <div class="col-6 col-md-3">
                                        <div class="p-3 border rounded text-center bg-body-tertiary">
                                            <div class="text-muted small text-uppercase fw-semibold">Cliques / Visitas</div>
                                            <div class="fs-4 fw-bold text-primary">{{ number_format($aff->clicks, 0, ',', '.') }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="p-3 border rounded text-center bg-body-tertiary">
                                            <div class="text-muted small text-uppercase fw-semibold">Vendas / Conversões</div>
                                            <div class="fs-4 fw-bold text-success">{{ number_format($aff->conversions, 0, ',', '.') }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="p-3 border rounded text-center bg-body-tertiary">
                                            <div class="text-muted small text-uppercase fw-semibold">Taxa de Conversão</div>
                                            <div class="fs-4 fw-bold text-info">{{ $aff->conversion_rate }}%</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="p-3 border rounded text-center bg-body-tertiary">
                                            <div class="text-muted small text-uppercase fw-semibold">Total Ganho</div>
                                            <div class="fs-4 fw-bold text-warning">R$ {{ number_format($aff->total_earned, 2, ',', '.') }}</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Gráfico Interativo do Afiliado --}}
                                <div class="card border mb-4">
                                    <div class="card-header bg-transparent py-2">
                                        <small class="fw-semibold text-muted"><i class="bi bi-bar-chart-line me-1"></i> Cliques e Conversões nos Últimos 14 Dias</small>
                                    </div>
                                    <div class="card-body chart-card-container" style="height: 200px;">
                                        <canvas id="chartTenantAffiliate" height="180"></canvas>
                                    </div>
                                </div>
                            @endif

                            {{-- Banner de Ação Rápida para Inscrições Pendentes ou Rejeitadas --}}
                            @if($aff && in_array($aff->status, ['pending', 'rejected']))
                                <div class="alert alert-{{ $aff->status === 'pending' ? 'warning' : 'danger' }} d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 mb-3 rounded border">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi {{ $aff->status === 'pending' ? 'bi-hourglass-split' : 'bi-exclamation-triangle-fill' }} fs-3"></i>
                                        <div>
                                            <div class="fw-bold fs-6">
                                                Status Atual: {{ $aff->status_label }}
                                            </div>
                                            <div class="small text-muted">
                                                Canal de divulgação informado: <strong>{{ $aff->promotion_channel ?: 'Geral' }}</strong>
                                                @if($aff->rejection_reason)
                                                    | Motivo/Observação: <em>"{{ $aff->rejection_reason }}"</em>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-success btn-sm fw-semibold" onclick="quickApproveAffiliate({{ $aff->id }})">
                                            <i class="bi bi-check-circle-fill me-1"></i> Aprovar Afiliação
                                        </button>
                                        <button type="button" class="btn btn-warning btn-sm fw-semibold text-dark" onclick="quickPendAffiliate({{ $aff->id }})">
                                            <i class="bi bi-hourglass-bottom me-1"></i> Marcar Pendência
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm fw-semibold" onclick="quickRejectAffiliate({{ $aff->id }})">
                                            <i class="bi bi-x-circle-fill me-1"></i> Rejeitar
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- Formulário de Configuração do Programa Afiliado deste Tenant --}}
                            <div class="border rounded p-3 bg-body-tertiary">
                                <h6 class="fw-bold mb-3"><i class="bi bi-sliders me-1 text-primary"></i> Configurar Regras de Ganhos, Descontos e Split Mercado Pago</h6>
                                <form id="form-tenant-affiliate">
                                    <div class="row g-3">
                                        {{-- Status --}}
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Status do Divulgador</label>
                                            <select name="status" class="form-select">
                                                <option value="active" @selected(($aff->status ?? 'active') === 'active')>Ativo (Aprovado)</option>
                                                <option value="pending" @selected(($aff->status ?? '') === 'pending')>Pendente de Aprovação</option>
                                                <option value="rejected" @selected(($aff->status ?? '') === 'rejected')>Rejeitado / Pendência</option>
                                                <option value="suspended" @selected(($aff->status ?? '') === 'suspended')>Suspenso (Bloqueado)</option>
                                            </select>
                                        </div>

                                        {{-- Slug --}}
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold">Slug / Link URL (/a/...)</label>
                                            <input type="text" name="slug" class="form-control" value="{{ $aff->slug ?? Str::slug($tenant->company_name) }}" placeholder="ex: empresa-promo">
                                        </div>

                                        {{-- Código/Cupom --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Código / Cupom</label>
                                            <input type="text" name="referral_code" class="form-control text-uppercase" value="{{ $aff->referral_code ?? strtoupper(Str::random(8)) }}">
                                        </div>

                                        {{-- GANHOS DO DIVULGADOR --}}
                                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-primary"><i class="bi bi-cash-coin me-1"></i> 1. Ganhos do Divulgador (Comissão)</strong></div>

                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Tipo de Cálculo de Ganho</label>
                                            <select name="commission_mode" id="promoter_commission_mode" class="form-select">
                                                <option value="percentage" @selected(($aff->commission_mode ?? 'percentage') === 'percentage')>Porcentagem (%)</option>
                                                <option value="fixed" @selected(($aff->commission_mode ?? '') === 'fixed')>Valor Fixo em Reais (R$)</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4" id="box_promoter_commission_pct">
                                            <label class="form-label small fw-bold">Porcentagem de Comissão (%)</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" max="100" name="commission_pct" class="form-control" value="{{ $aff->commission_pct ?? 20.00 }}">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>

                                        <div class="col-md-4" id="box_promoter_commission_fixed">
                                            <label class="form-label small fw-bold">Comissão Fixa por Venda (R$)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="commission_fixed" class="form-control mask-money" value="{{ number_format($aff->commission_fixed ?? 0, 2, ',', '.') }}">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Frequência da Comissão</label>
                                            <select name="commission_type" class="form-select">
                                                <option value="recurring" @selected(($aff->commission_type ?? 'recurring') === 'recurring')>Recorrente (Todas as Mensalidades)</option>
                                                <option value="first_payment" @selected(($aff->commission_type ?? '') === 'first_payment')>Única Vez (Apenas 1º Pagamento)</option>
                                                <option value="lifetime" @selected(($aff->commission_type ?? '') === 'lifetime')>Vitalícia</option>
                                            </select>
                                        </div>

                                        {{-- DESCONTOS PARA QUEM COMPRA --}}
                                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-success"><i class="bi bi-tag-fill me-1"></i> 2. Desconto Oferecido ao Comprador (Incentivo ao Cliente)</strong></div>

                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Tipo de Cálculo de Desconto</label>
                                            <select name="discount_mode" id="promoter_discount_mode" class="form-select">
                                                <option value="percentage" @selected(($aff->discount_mode ?? 'percentage') === 'percentage')>Porcentagem (%)</option>
                                                <option value="fixed" @selected(($aff->discount_mode ?? '') === 'fixed')>Valor Fixo em Reais (R$)</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4" id="box_promoter_discount_pct">
                                            <label class="form-label small fw-bold">Desconto ao Comprador (%)</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" max="100" name="discount_pct" class="form-control" value="{{ $aff->discount_pct ?? 10.00 }}">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>

                                        <div class="col-md-4" id="box_promoter_discount_fixed">
                                            <label class="form-label small fw-bold">Desconto Fixo ao Comprador (R$)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="discount_fixed" class="form-control mask-money" value="{{ number_format($aff->discount_fixed ?? 0, 2, ',', '.') }}">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Aplicação do Desconto</label>
                                            <select name="discount_type" class="form-select">
                                                <option value="first_payment" @selected(($aff->discount_type ?? 'first_payment') === 'first_payment')>Apenas na 1ª Mensalidade</option>
                                                <option value="recurring" @selected(($aff->discount_type ?? '') === 'recurring')>Recorrente (Em todas as faturas)</option>
                                            </select>
                                        </div>

                                        {{-- SPLIT MERCADO PAGO --}}
                                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-info"><i class="bi bi-lightning-charge-fill me-1"></i> 3. Split Automático Mercado Pago</strong></div>

                                        <div class="col-md-4 d-flex align-items-center">
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="split_enabled" value="1" id="tenant_split_enabled" @checked($aff->split_enabled ?? false)>
                                                <label class="form-check-label fw-bold small" for="tenant_split_enabled">Ativar Split Direto MP</label>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Percentual de Split (%)</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" max="100" name="split_pct" class="form-control" value="{{ $aff->split_pct ?? ($aff->commission_pct ?? 20.00) }}">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Mercado Pago Collector ID</label>
                                            <input type="text" name="mp_collector_id" class="form-control" value="{{ $aff->mp_collector_id ?? '' }}" placeholder="ID da conta MP do afiliado">
                                        </div>

                                        {{-- Observações e Saque Mínimo --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Saque Mínimo PIX (R$)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="min_withdrawal" class="form-control mask-money" value="{{ number_format($aff->min_withdrawal ?? 50, 2, ',', '.') }}">
                                            </div>
                                        </div>

                                        <div class="col-md-8">
                                            <label class="form-label small fw-bold">Motivo da Rejeição / Instruções de Pendência</label>
                                            <input type="text" name="rejection_reason" class="form-control" value="{{ $aff->rejection_reason ?? '' }}" placeholder="Orientações visíveis ao cliente se rejeitado">
                                        </div>

                                        <div class="col-12 text-end mt-3">
                                            <button type="submit" class="btn btn-primary" id="btn-save-tenant-affiliate">
                                                <i class="bi bi-check-lg me-1"></i> Salvar Regras do Afiliado
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- SEÇÃO 2: QUEM INDICOU ESTE TENANT --}}
                    <div class="card border">
                        <div class="card-header bg-transparent fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1 text-info"></i> Origem de Indicação deste Tenant
                        </div>
                        <div class="card-body">
                            @php
                                $ref = $affiliateStats['referred_by'] ?? null;
                            @endphp
                            @if($ref && $ref->affiliate)
                                <div class="row align-items-center g-3">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center fw-bold" style="width: 48px; height: 48px; font-size: 1.2rem;">
                                                <i class="bi bi-person-badge"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold">{{ $ref->affiliate->user->name ?? 'Afiliado #' . $ref->affiliate_id }}</h6>
                                                <small class="text-muted d-block">{{ $ref->affiliate->user->email ?? '-' }}</small>
                                                <span class="badge bg-secondary-subtle text-secondary border mt-1">Código: {{ $ref->affiliate->referral_code }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-muted small">Data da Indicação</div>
                                        <div class="fw-bold">{{ $ref->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-muted small">Comissões Geradas por este Tenant</div>
                                        <div class="fw-bold text-success fs-5">R$ {{ number_format($affiliateStats['commissions_paid_for_referrer'] ?? 0, 2, ',', '.') }}</div>
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-3 text-muted">
                                    <i class="bi bi-person-x fs-3 d-block mb-1 text-secondary"></i>
                                    <span>Este tenant realizou cadastro direto na plataforma sem indicação de afiliados.</span>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>

                {{-- ==================== ABA 3: INSTÂNCIAS WHATSAPP ==================== --}}
                <div class="tab-pane fade" id="tab-instances" role="tabpanel">
                    @if($tenant->instances->count())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle m-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Instância</th>
                                        <th>Perfil WhatsApp</th>
                                        <th>Telefone / JID</th>
                                        <th>Status</th>
                                        <th>Cobrança</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tenant->instances as $inst)
                                    <tr>
                                        <td>
                                            <code>{{ $inst->instance_name }}</code>
                                            @if($inst->is_default)
                                                <span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i> Padrão</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                @if($inst->profile_avatar_url)
                                                    <img src="{{ $inst->profile_avatar_url }}" alt="{{ $inst->whatsapp_profile_name }}" referrerpolicy="no-referrer" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('d-none');">
                                                    <div class="rounded-circle bg-primary-subtle text-primary d-none align-items-center justify-content-center fw-bold" style="width:36px;height:36px;">
                                                        {{ mb_strtoupper(mb_substr($inst->whatsapp_profile_name ?: 'W', 0, 1)) }}
                                                    </div>
                                                @else
                                                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;">
                                                        {{ mb_strtoupper(mb_substr($inst->whatsapp_profile_name ?: 'W', 0, 1)) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <strong class="d-block">{{ $inst->whatsapp_profile_name ?: 'Sem nome sincronizado' }}</strong>
                                                    <small class="text-muted d-block">{{ $inst->profile_status ?: '-' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-medium">{{ $inst->profile_number ?: ($inst->phone ?? '-') }}</span>
                                            <small class="text-muted d-block">{{ $inst->profile_jid ?: '-' }}</small>
                                        </td>
                                        <td>
                                            @if($inst->status === 'connected')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <span class="tenant-status-dot active me-1"></span> Conectado
                                                </span>
                                            @elseif($inst->status === 'disconnected')
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                    Desconectado
                                                </span>
                                            @else
                                                <span class="badge bg-secondary">{{ ucfirst($inst->status ?? 'Desconhecido') }}</span>
                                            @endif
                                            <small class="text-muted d-block mt-1">{{ is_array($inst->profile_devices) ? count($inst->profile_devices) : 0 }} dispositivo(s)</small>
                                        </td>
                                        <td>
                                            @if($inst->is_extra_billable)
                                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                    Extra {{ \App\Support\Format::money($inst->recurring_amount ?? 0) }}/mês
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inclusa no Plano</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-sync-tenant-instance me-1" data-id="{{ $inst->id }}" data-name="{{ $inst->instance_name }}" title="Sincronizar Perfil e Foto do WhatsApp">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-tenant-instance" data-id="{{ $inst->id }}" data-name="{{ $inst->instance_name }}" title="Excluir Instância">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-phone display-5 text-secondary d-block mb-2"></i>
                            <p class="mb-0">Nenhuma instância cadastrada para este tenant.</p>
                        </div>
                    @endif
                </div>

                {{-- ==================== ABA 4: COBRANÇA & GATEWAYS ==================== --}}
                <div class="tab-pane fade" id="tab-billing" role="tabpanel">
                    <div class="row g-4">
                        {{-- Gateways Configurados --}}
                        <div class="col-lg-6">
                            <div class="card border h-100">
                                <div class="card-header bg-transparent fw-semibold">
                                    <i class="bi bi-credit-card me-1 text-primary"></i> Gateways de Pagamento
                                </div>
                                <div class="card-body p-0">
                                    @if($tenant->gateways->count())
                                        <div class="table-responsive">
                                            <table class="table table-hover m-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Gateway</th>
                                                        <th>Status</th>
                                                        <th>Padrão</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($tenant->gateways as $gw)
                                                    <tr>
                                                        <td>
                                                            <i class="bi bi-credit-card-2-front me-2 text-primary"></i>
                                                            <strong class="text-capitalize">{{ $gw->gateway }}</strong>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-{{ $gw->active ? 'success' : 'secondary' }}">
                                                                {{ $gw->active ? 'Ativo' : 'Inativo' }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @if($gw->is_default)
                                                                <span class="badge bg-success"><i class="bi bi-check-lg"></i> Padrão</span>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="text-center py-4 text-muted">
                                            <i class="bi bi-credit-card display-6 text-secondary d-block mb-2"></i>
                                            <p class="mb-0">Usando gateway global padrão do sistema.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Régua de Cobrança --}}
                        <div class="col-lg-6">
                            <div class="card border h-100">
                                <div class="card-header bg-transparent fw-semibold">
                                    <i class="bi bi-gear me-1 text-primary"></i> Parâmetros de Faturamento
                                </div>
                                <div class="card-body">
                                    @if($tenant->billingConfig)
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <label class="text-muted small">Horário de Execução</label>
                                                <div class="fw-bold">{{ $tenant->billingConfig->cron_time ?? '09:00' }}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="text-muted small">Avisos Antes do Vencimento</label>
                                                <div class="fw-bold">{{ is_array($tenant->billingConfig->days_before_due) ? implode(', ', $tenant->billingConfig->days_before_due) : ($tenant->billingConfig->days_before_due ?? '3') }} dias</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="text-muted small">Criação Antecipada</label>
                                                <div class="fw-bold">{{ $tenant->billingConfig->invoice_creation_days ?? '5' }} dias antes</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="text-muted small">Repasse de Taxas</label>
                                                <div>
                                                    <span class="badge bg-{{ $tenant->billingConfig->fee_passthrough ? 'success' : 'secondary' }}">
                                                        {{ $tenant->billingConfig->fee_passthrough ? 'Sim (' . number_format($tenant->billingConfig->fee_percentage ?? 0, 2, ',', '.') . '%)' : 'Não' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <label class="text-muted small">Parcelamento de Faturas</label>
                                                <div>
                                                    <span class="badge bg-{{ $tenant->billingConfig->installment_enabled ? 'success' : 'secondary' }}">
                                                        {{ $tenant->billingConfig->installment_enabled ? 'Habilitado (até ' . ($tenant->billingConfig->max_installments ?? 12) . 'x)' : 'Desabilitado' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-center py-4 text-muted">
                                            <i class="bi bi-clock-history display-6 text-secondary d-block mb-2"></i>
                                            <p class="mb-0">Régua de cobrança automática padrão ativa.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== ABA 5: FATURAS ==================== --}}
                <div class="tab-pane fade" id="tab-invoices" role="tabpanel">
                    @if($tenant->tenantInvoices->count())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle m-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Número</th>
                                        <th>Descrição</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Vencimento</th>
                                        <th>Pago em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tenant->tenantInvoices->sortByDesc('id')->take(30) as $inv)
                                    <tr>
                                        <td><code>{{ $inv->number ?? $inv->id }}</code></td>
                                        <td>
                                            <strong>{{ $inv->description ?: 'Assinatura' }}</strong>
                                            @if(is_array($inv->meta ?? null) && count($inv->meta['line_items'] ?? []))
                                                <div class="text-muted small">
                                                    @foreach($inv->meta['line_items'] as $item)
                                                        <span>• {{ $item['description'] ?? 'Item' }} ({{ \App\Support\Format::money($item['amount'] ?? 0) }})</span><br>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td><strong class="text-body">{{ \App\Support\Format::money($inv->amount ?? 0) }}</strong></td>
                                        <td>
                                            @php
                                                $statusClasses = [
                                                    'paid'      => 'bg-success',
                                                    'pending'   => 'bg-warning text-dark',
                                                    'overdue'   => 'bg-danger',
                                                    'cancelled' => 'bg-secondary',
                                                    'canceled'  => 'bg-secondary',
                                                ];
                                            @endphp
                                            <span class="badge {{ $statusClasses[$inv->status] ?? 'bg-secondary' }} text-uppercase">
                                                {{ $inv->status }}
                                            </span>
                                        </td>
                                        <td>{{ $inv->due_date?->format('d/m/Y') ?? '-' }}</td>
                                        <td>{{ $inv->paid_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-receipt display-5 text-secondary d-block mb-2"></i>
                            <p class="mb-0">Nenhuma fatura gerada para este tenant.</p>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    // 1. Gráfico Histórico de Faturamento do Tenant
    const ctxRev = document.getElementById('chartTenantRevenue');
    if (ctxRev && typeof Chart !== 'undefined') {
        const labelsRev = @json($analytics['months_labels'] ?? []);
        const dataRev = @json($analytics['months_revenue'] ?? []);

        const gradient = ctxRev.getContext('2d').createLinearGradient(0, 0, 0, 220);
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
        gradient.addColorStop(1, 'rgba(16, 185, 129, 0.02)');

        new Chart(ctxRev, {
            type: 'line',
            data: {
                labels: labelsRev,
                datasets: [{
                    label: 'Faturamento Pago (R$)',
                    data: dataRev,
                    borderColor: '#10b981',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#10b981',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' Pago: R$ ' + ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor } },
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            callback: function (val) { return 'R$ ' + val; }
                        }
                    }
                }
            }
        });
    }

    // 2. Gráfico do Afiliado (Cliques x Conversões nos últimos 14 dias)
    @if($isPromoter && isset($affiliateStats['promoter_stats']))
    const ctxAff = document.getElementById('chartTenantAffiliate');
    if (ctxAff && typeof Chart !== 'undefined') {
        const affLabels = @json($affiliateStats['promoter_stats']['chart_labels'] ?? []);
        const affClicks = @json($affiliateStats['promoter_stats']['chart_clicks'] ?? []);
        const affConvs = @json($affiliateStats['promoter_stats']['chart_conversions'] ?? []);

        new Chart(ctxAff, {
            type: 'bar',
            data: {
                labels: affLabels,
                datasets: [
                    {
                        label: 'Cliques / Visitas',
                        data: affClicks,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderRadius: 4,
                    },
                    {
                        label: 'Vendas / Conversões',
                        data: affConvs,
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { color: textColor, font: { size: 11 } } },
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor } },
                    y: { grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 } }
                }
            }
        });
    }
    @endif

    // Toggle de exibição de campos % vs fixo no form do promotor
    function togglePromoterFields() {
        const commMode = document.getElementById('promoter_commission_mode')?.value;
        const boxCommPct = document.getElementById('box_promoter_commission_pct');
        const boxCommFixed = document.getElementById('box_promoter_commission_fixed');
        if (boxCommPct && boxCommFixed) {
            boxCommPct.style.display = commMode === 'fixed' ? 'none' : 'block';
            boxCommFixed.style.display = commMode === 'fixed' ? 'block' : 'none';
        }

        const discMode = document.getElementById('promoter_discount_mode')?.value;
        const boxDiscPct = document.getElementById('box_promoter_discount_pct');
        const boxDiscFixed = document.getElementById('box_promoter_discount_fixed');
        if (boxDiscPct && boxDiscFixed) {
            boxDiscPct.style.display = discMode === 'fixed' ? 'none' : 'block';
            boxDiscFixed.style.display = discMode === 'fixed' ? 'block' : 'none';
        }
    }
    document.getElementById('promoter_commission_mode')?.addEventListener('change', togglePromoterFields);
    document.getElementById('promoter_discount_mode')?.addEventListener('change', togglePromoterFields);
    togglePromoterFields();

    // Salvar Regras de Afiliado deste Tenant via AJAX
    document.getElementById('form-tenant-affiliate')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-tenant-affiliate');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';

        const formData = new FormData(this);
        const json = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('{{ route("admin.tenancy.tenants.affiliate.save", $tenant->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(json)
            });
            const data = await res.json();
            if (res.ok && data.ok) {
                if (typeof Toastify === 'function') {
                    Toastify({ text: data.message, duration: 3500, gravity: "top", position: "right", style: { background: "linear-gradient(to right, #00b09b, #96c93d)" } }).showToast();
                }
                setTimeout(() => location.reload(), 1000);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro ao salvar',
                    text: data.error || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Não foi possível salvar as configurações.'),
                    confirmButtonColor: '#dc3545',
                });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Erro de conexão', text: err.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Salvar Regras do Afiliado';
        }
    });

    // Ações Rápidas de Moderação de Afiliado (Aprovar / Rejeitar / Pendência)
    window.quickApproveAffiliate = async function (affId) {
        const { isConfirmed } = await Swal.fire({
            title: 'Aprovar Cadastro de Afiliado?',
            text: 'O cliente receberá imediatamente mensagem no WhatsApp e e-mail com seus links exclusivos e cupons liberados.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-circle-fill me-1"></i> Sim, Aprovar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754'
        });

        if (!isConfirmed) return;

        try {
            const res = await fetch(`/admin/affiliates/${affId}/approve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.ok) {
                if (typeof Toastify === 'function') {
                    Toastify({ text: data.message, duration: 4000, style: { background: "#198754" } }).showToast();
                }
                Swal.fire({ icon: 'success', title: 'Afiliado Aprovado!', text: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.error || 'Não foi possível aprovar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro de Comunicação', text: e.message });
        }
    };

    window.quickRejectAffiliate = async function (affId) {
        const { value: reason } = await Swal.fire({
            title: 'Rejeitar Inscrição de Afiliado',
            text: 'Informe o motivo detalhado para ser enviado ao cliente por WhatsApp e E-mail:',
            input: 'textarea',
            inputPlaceholder: 'Ex: Chave PIX inválida, canal de divulgação não atende aos termos...',
            showCancelButton: true,
            confirmButtonText: 'Confirmar Rejeição',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            inputValidator: (val) => {
                if (!val || val.trim().length < 5) {
                    return 'Por favor informe o motivo detalhado da rejeição.';
                }
            }
        });

        if (!reason) return;

        try {
            const res = await fetch(`/admin/affiliates/${affId}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ reason: reason.trim() })
            });
            const data = await res.json();
            if (data.ok) {
                if (typeof Toastify === 'function') {
                    Toastify({ text: data.message, duration: 4000, style: { background: "#dc3545" } }).showToast();
                }
                Swal.fire({ icon: 'success', title: 'Inscrição Rejeitada', text: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.error || 'Não foi possível rejeitar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro de Comunicação', text: e.message });
        }
    };

    window.quickPendAffiliate = async function (affId) {
        const { value: instructions } = await Swal.fire({
            title: 'Marcar com Pendência de Informações',
            text: 'Digite as instruções de correção para o cliente:',
            input: 'textarea',
            inputPlaceholder: 'Ex: Por favor atualize sua chave PIX para CPF e informe o link do perfil onde divulgará...',
            showCancelButton: true,
            confirmButtonText: 'Enviar Pendência',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ffc107',
            inputValidator: (val) => {
                if (!val || val.trim().length < 5) {
                    return 'Informe as instruções de pendência para o cliente.';
                }
            }
        });

        if (!instructions) return;

        try {
            const res = await fetch(`/admin/affiliates/${affId}/pend`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ instructions: instructions.trim() })
            });
            const data = await res.json();
            if (data.ok) {
                if (typeof Toastify === 'function') {
                    Toastify({ text: data.message, duration: 4000, style: { background: "#e0a800" } }).showToast();
                }
                Swal.fire({ icon: 'success', title: 'Pendência Registrada', text: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.error || 'Não foi possível registrar pendência.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro de Comunicação', text: e.message });
        }
    };

    // Copiar Link de Afiliado
    document.getElementById('btn-copy-aff-link')?.addEventListener('click', function () {
        const input = document.getElementById('promoter-link-input');
        if (!input) return;
        input.select();
        navigator.clipboard.writeText(input.value);
        if (typeof Toastify === 'function') {
            Toastify({ text: "Link de indicação copiado com sucesso!", duration: 2500, gravity: "top", position: "right", style: { background: "#10b981" } }).showToast();
        }
    });

    // Suspender Tenant
    @if($tenant->status !== 'suspended')
    document.getElementById('btn-suspend')?.addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'warning',
            title: 'Suspender este tenant?',
            text: 'O cliente perderá acesso ao sistema até nova ativação.',
            showCancelButton: true,
            confirmButtonText: 'Sim, suspender',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
        });
        if (!ok.isConfirmed) return;
        const r = await fetch('{{ route("admin.tenancy.tenants.suspend", $tenant->id) }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        if (r.ok) {
            if (typeof Toastify === 'function') Toastify({ text: 'Tenant suspenso com sucesso.', duration: 3000, style: { background: "#ef4444" } }).showToast();
            setTimeout(() => location.reload(), 800);
        } else {
            const d = await r.json();
            Swal.fire({ icon: 'error', title: 'Erro', text: d.message || 'Erro ao suspender.' });
        }
    });
    @endif

    // Ativar Tenant
    @if($tenant->status !== 'active')
    document.getElementById('btn-activate')?.addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'question',
            title: 'Ativar este tenant?',
            text: 'Um novo período será iniciado para o cliente.',
            showCancelButton: true,
            confirmButtonText: 'Sim, ativar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
        });
        if (!ok.isConfirmed) return;
        const r = await fetch('{{ route("admin.tenancy.tenants.activate", $tenant->id) }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        if (r.ok) {
            if (typeof Toastify === 'function') Toastify({ text: 'Tenant ativado com sucesso.', duration: 3000, style: { background: "#10b981" } }).showToast();
            setTimeout(() => location.reload(), 800);
        } else {
            const d = await r.json();
            Swal.fire({ icon: 'error', title: 'Erro', text: d.message || 'Erro ao ativar.' });
        }
    });
    @endif

    // Desativar 2FA
    document.getElementById('btn-disable-2fa')?.addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'warning',
            title: 'Desativar 2FA?',
            text: 'O usuário perderá a autenticação de dois fatores e poderá acessar apenas com e-mail e senha.',
            showCancelButton: true,
            confirmButtonText: 'Sim, desativar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#64748b',
        });
        if (!ok.isConfirmed) return;
        const r = await fetch('{{ route("admin.tenancy.tenants.disable-2fa", $tenant->id) }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            if (typeof Toastify === 'function') Toastify({ text: d.message || '2FA desativado.', duration: 3000, style: { background: "#10b981" } }).showToast();
            setTimeout(() => location.reload(), 800);
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: d.message || 'Erro ao desativar 2FA.' });
        }
    });

    // Sincronizar Perfil e Foto da Instância WhatsApp
    document.querySelectorAll('.btn-sync-tenant-instance').forEach(btn => {
        btn.addEventListener('click', async function () {
            const instId = this.dataset.id;
            const instName = this.dataset.name || '';
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

            try {
                const res = await fetch(`{{ url('/admin/tenancy/tenants/'.$tenant->id.'/instances') }}/${instId}/sync`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    }
                });
                const d = await res.json();
                if (res.ok && d.ok) {
                    if (typeof Toastify === 'function') {
                        Toastify({
                            text: d.message || `Instância ${instName} sincronizada com sucesso!`,
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            style: { background: "#10b981" }
                        }).showToast();
                    }
                    setTimeout(() => location.reload(), 900);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro na Sincronização',
                        text: d.error || d.message || 'Falha ao sincronizar dados com o WhatsApp Oficial.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Erro de Comunicação', text: err.message });
            } finally {
                this.disabled = false;
                this.innerHTML = originalHtml;
            }
        });
    });

    // Excluir Instância WhatsApp
    document.querySelectorAll('.btn-delete-tenant-instance').forEach(btn => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('tr');
            const connected = row && row.querySelector('.tenant-status-dot.active') !== null;
            const ok = await Swal.fire({
                icon: 'warning',
                title: 'Excluir instância?',
                text: connected
                    ? `A instância ${btn.dataset.name || ''} está conectada. Desconecte-a primeiro antes de excluir.`
                    : `A instância ${btn.dataset.name || ''} será removida do sistema.`,
                showCancelButton: !connected,
                confirmButtonText: connected ? 'OK' : 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#64748b',
            });
            if (connected || !ok.isConfirmed) return;

            const r = await fetch(`{{ url('/admin/tenancy/tenants/'.$tenant->id.'/instances') }}/${btn.dataset.id}`, {
                method: 'DELETE', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const d = await r.json();
            if (r.ok && d.ok) {
                if (typeof Toastify === 'function') Toastify({ text: 'Instância excluída.', duration: 2500, style: { background: "#10b981" } }).showToast();
                row?.remove();
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: d.error || 'Erro ao excluir instância.' });
            }
        });
    });
});
</script>
@endpush
