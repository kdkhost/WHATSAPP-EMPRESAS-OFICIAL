@extends('tenancy::tenant.layout')
@section('title', 'Dashboard')

@php
    $statusColors = ['active' => 'success', 'trial' => 'info', 'suspended' => 'danger', 'cancelled' => 'secondary'];
    $statusLabels = ['active' => 'Ativo', 'trial' => 'Teste', 'suspended' => 'Suspenso', 'cancelled' => 'Cancelado'];
    $planPrice = \App\Support\Format::money($tenant->plan->price ?? 0);
    $a = $analytics ?? [
        'monthlyRevenue' => 0,
        'monthlyInvoicesPaidCount' => 0,
        'monthlyInvoicesTotalCount' => 0,
        'collectionRate' => 100,
        'activeCustomersCount' => 0,
        'customersWithWhatsappCount' => 0,
        'monthlyMessagesCount' => 0,
        'deliveredCount' => 0,
        'deliveryRate' => 100,
        'healthScore' => 100,
        'healthStatus' => 'Excelente',
        'healthBadgeClass' => 'success',
        'healthChecklist' => [],
        'chartLabels' => [],
        'chartMessages' => [],
        'chartRevenue' => [],
    ];
@endphp

@section('content')
{{-- HERO DO CLIENTE --}}
<div class="tenant-premium-hero mb-4">
    <div>
        <span class="tenant-premium-hero__eyebrow"><i class="bi bi-speedometer2 me-2"></i>Painel do Cliente</span>
        <h1 class="tenant-premium-hero__title">Olá, {{ $tenant->company_name }}.</h1>
        <p class="tenant-premium-hero__text">Acompanhe seus KPIs em tempo real, fluxo de mensagens via WhatsApp, faturamento da sua carteira e a saúde da sua conta.</p>
    </div>
    <div class="tenant-premium-metric">
        <span class="tenant-premium-kicker">Plano & Assinatura</span>
        <div class="d-flex align-items-start justify-content-between gap-3">
            <div>
                <h2 class="tenant-premium-title">{{ $tenant->plan->name ?? '-' }}</h2>
                <p class="tenant-premium-subtitle mb-0">{{ $planPrice }} / {{ $tenant->plan->billing_cycle ?? 'mensal' }}</p>
            </div>
            <span class="badge rounded-pill text-bg-{{ $statusColors[$tenant->status] ?? 'secondary' }}">
                {{ $statusLabels[$tenant->status] ?? ucfirst((string) $tenant->status) }}
            </span>
        </div>
        <div class="small text-muted mt-3">
            <i class="bi bi-calendar3 me-1"></i>
            {{ $tenant->current_period_start?->format('d/m/Y') ?? '-' }}
            até {{ $tenant->current_period_end?->format('d/m/Y') ?? '-' }}
        </div>
    </div>
</div>

{{-- 4 KPIS PRINCIPAIS DO MÊS --}}
<div class="row g-3 mb-4">
    {{-- KPI 1: Faturamento Recebido --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div>
                    <div class="text-muted small">Receita no Mês</div>
                    <div class="fs-4 fw-bold text-success">R$ {{ number_format($a['monthlyRevenue'], 2, ',', '.') }}</div>
                    <small class="text-muted">{{ $a['monthlyInvoicesPaidCount'] }} fatura(s) recebida(s)</small>
                </div>
                <div class="stat-icon text-white bg-success"><i class="bi bi-currency-dollar"></i></div>
            </div>
        </div>
    </div>

    {{-- KPI 2: Clientes Ativos --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div>
                    <div class="text-muted small">Clientes Cadastrados</div>
                    <div class="fs-4 fw-bold">{{ $a['activeCustomersCount'] }}</div>
                    <small class="text-muted">{{ $a['customersWithWhatsappCount'] }} com WhatsApp ativo</small>
                </div>
                <div class="stat-icon text-white bg-primary"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
    </div>

    {{-- KPI 3: Disparos WhatsApp no Mês --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div>
                    <div class="text-muted small">Mensagens WhatsApp</div>
                    <div class="fs-4 fw-bold text-primary">{{ $a['monthlyMessagesCount'] }}</div>
                    <small class="text-muted">{{ $a['deliveryRate'] }}% taxa de entrega</small>
                </div>
                <div class="stat-icon text-white bg-info"><i class="bi bi-whatsapp"></i></div>
            </div>
        </div>
    </div>

    {{-- KPI 4: Taxa de Adimplência --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div>
                    <div class="text-muted small">Adimplência Carteira</div>
                    <div class="fs-4 fw-bold text-warning">{{ $a['collectionRate'] }}%</div>
                    <small class="text-muted">de {{ $a['monthlyInvoicesTotalCount'] }} cobranças emitidas</small>
                </div>
                <div class="stat-icon text-white bg-warning"><i class="bi bi-pie-chart-fill"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- GRÁFICOS DINÂMICOS & SAÚDE DO USUÁRIO --}}
<div class="row g-4 mb-4">
    {{-- Coluna Gráficos (8 colunas) --}}
    <div class="col-lg-8">
        {{-- Gráfico 1: Volume de Mensagens --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <div>
                    <span class="tenant-premium-kicker mb-0">Evolução Diária</span>
                    <h5 class="tenant-premium-title mb-0"><i class="bi bi-graph-up text-success me-2"></i>Disparos WhatsApp (Últimos 14 Dias)</h5>
                </div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Tempo Real
                </span>
            </div>
            <div class="card-body p-3">
                <div style="height: 250px; position: relative;">
                    <canvas id="chartMessagesCanvas"></canvas>
                </div>
            </div>
        </div>

        {{-- Gráfico 2: Receita Diária --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <div>
                    <span class="tenant-premium-kicker mb-0">Financeiro</span>
                    <h5 class="tenant-premium-title mb-0"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Receita Recebida por Dia (R$)</h5>
                </div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    Últimas 2 Semanas
                </span>
            </div>
            <div class="card-body p-3">
                <div style="height: 220px; position: relative;">
                    <canvas id="chartRevenueCanvas"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Coluna Lateral: Saúde do Usuário / Saúde da Operação (4 colunas) --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent py-3">
                <span class="tenant-premium-kicker mb-0">Monitoramento Contínuo</span>
                <h5 class="tenant-premium-title mb-0"><i class="bi bi-heart-pulse-fill text-danger me-2"></i>Saúde da Conta</h5>
            </div>
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                {{-- Score Visual Circular --}}
                <div class="text-center py-2">
                    <div class="health-score-dial mx-auto mb-2 border-{{ $a['healthBadgeClass'] }}">
                        <span class="health-score-number">{{ $a['healthScore'] }}%</span>
                        <small class="health-score-label">Score</small>
                    </div>
                    <div class="fw-bold fs-5 text-{{ $a['healthBadgeClass'] }}">
                        Status: {{ $a['healthStatus'] }}
                    </div>
                    <p class="text-muted small mb-3">Índice global de prontidão e performance operacional da sua conta.</p>
                </div>

                {{-- Checklist de Saúde --}}
                <div class="health-checklist mt-2 border-top pt-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">Pilares de Saúde</div>
                    @foreach($a['healthChecklist'] as $item)
                        <div class="d-flex align-items-start gap-2 py-2 border-bottom">
                            <div class="mt-1">
                                @if($item['status'])
                                    <i class="bi bi-check-circle-fill text-success fs-6"></i>
                                @else
                                    <i class="bi bi-exclamation-circle-fill text-warning fs-6"></i>
                                @endif
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="small text-truncate">{{ $item['title'] }}</strong>
                                    <span class="badge bg-light text-muted border">{{ $item['weight'] }}</span>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem; line-height: 1.3;">
                                    {{ $item['desc'] }}
                                </div>
                                @if(! $item['status'])
                                    <a href="{{ $item['url'] }}" class="btn btn-xs btn-outline-warning py-0 px-2 mt-1 rounded-pill" style="font-size: 0.7rem;">
                                        {{ $item['action'] }} &rarr;
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Dica Inteligente --}}
                <div class="mt-3 p-3 rounded-3 bg-body-tertiary border small">
                    <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                    <strong>Dica Pro:</strong> Mantenha seu WhatsApp conectado e configure seu gateway de pagamento para atingir 100% de saúde e automatizar suas cobranças.
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ATALHOS RÁPIDOS & OPERAÇÃO --}}
<div class="row g-3">
    <div class="col-12">
        <div class="premium-panel">
            <div class="premium-panel__header">
                <div>
                    <span class="premium-panel__kicker">Operação Rápida</span>
                    <h3 class="premium-panel__title">Acesso Rápido às Funcionalidades</h3>
                    <p class="premium-panel__subtitle">Atalhos diretos para gerenciar cobranças, clientes, mensagens e integrações.</p>
                </div>
            </div>
            <div class="premium-panel__body">
                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <a href="{{ route('tenant.invoices') }}?nova_avulsa=1" class="tenant-action-card text-decoration-none">
                            <i class="bi bi-file-earmark-plus-fill text-primary"></i>
                            <span>Nova Cobrança Avulsa</span>
                            <small>Emissão pontual à vista ou carnê parcelado</small>
                        </a>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <a href="{{ route('tenant.whatsapp.manual') }}" class="tenant-action-card text-decoration-none">
                            <i class="bi bi-chat-dots-fill text-success"></i>
                            <span>Envio Manual de Mensagem</span>
                            <small>Disparo rápido avulso com preview WhatsApp</small>
                        </a>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <a href="{{ route('tenant.instances') }}" class="tenant-action-card text-decoration-none">
                            <i class="bi bi-phone"></i>
                            <span>Gerenciar Instâncias</span>
                            <small>Conexão WhatsApp e QR Code</small>
                        </a>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <a href="{{ route('tenant.templates') }}" class="tenant-action-card text-decoration-none">
                            <i class="bi bi-chat-square-text"></i>
                            <span>Gerenciar Templates</span>
                            <small>Mensagens e modelos de cobrança</small>
                        </a>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <a href="{{ route('tenant.customers') }}" class="tenant-action-card text-decoration-none">
                            <i class="bi bi-people"></i>
                            <span>Clientes (CRM)</span>
                            <small>Cadastro e consulta de contatos</small>
                        </a>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <a href="{{ route('tenant.billing') }}" class="tenant-action-card text-decoration-none">
                            <i class="bi bi-sliders"></i>
                            <span>Configurar Cobrança</span>
                            <small>Regras, lembretes e parcelas</small>
                        </a>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <a href="{{ route('tenant.gateways') }}" class="tenant-action-card text-decoration-none">
                            <i class="bi bi-credit-card"></i>
                            <span>Configurar Gateways</span>
                            <small>PIX, Cartão e Boleto Automático</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* Health Score Dial */
.health-score-dial {
    width: 104px;
    height: 104px;
    border-radius: 50%;
    border: 7px solid var(--bs-success);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--app-surface-bg);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
    transition: transform .2s ease;
}
.health-score-dial:hover {
    transform: scale(1.05);
}
.health-score-dial.border-success { border-color: #198754 !important; }
.health-score-dial.border-info { border-color: #0dcaf0 !important; }
.health-score-dial.border-warning { border-color: #ffc107 !important; }
.health-score-dial.border-danger { border-color: #dc3545 !important; }

.health-score-number {
    font-size: 1.45rem;
    font-weight: 800;
    line-height: 1;
    color: var(--bs-body-color);
}
.health-score-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: var(--bs-secondary-color);
}

.tenant-action-card {
    min-height: 112px;
    border: 1px solid var(--app-border-soft);
    border-radius: 16px;
    background: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    color: var(--bs-body-color);
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: .25rem;
    padding: 1rem;
    transition: .18s ease;
}
.tenant-action-card i { color: var(--bs-primary); font-size: 1.45rem; }
.tenant-action-card span { font-weight: 700; }
.tenant-action-card small { color: var(--bs-secondary-color); }
.tenant-action-card:hover {
    border-color: color-mix(in srgb, var(--bs-primary) 45%, var(--app-border-soft));
    transform: translateY(-2px);
    box-shadow: var(--app-shadow-premium);
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.05)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    const labels = @json($a['chartLabels']);
    const messagesData = @json($a['chartMessages']);
    const revenueData = @json($a['chartRevenue']);

    // 1. Gráfico de Mensagens WhatsApp (Área / Linha Suave com Animação)
    const ctxMessages = document.getElementById('chartMessagesCanvas');
    if (ctxMessages && typeof Chart !== 'undefined') {
        const gradient = ctxMessages.getContext('2d').createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(37, 211, 102, 0.35)');
        gradient.addColorStop(1, 'rgba(37, 211, 102, 0.01)');

        new Chart(ctxMessages, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Mensagens Enviadas',
                    data: messagesData,
                    borderColor: '#25d366',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#25d366',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1200,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#0f172a',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                return `Disparos: ${context.parsed.y} mensagens`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: gridColor },
                        ticks: { color: textColor, font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            font: { size: 11 },
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    // 2. Gráfico de Receita Diária (Barras Arredondadas)
    const ctxRevenue = document.getElementById('chartRevenueCanvas');
    if (ctxRevenue && typeof Chart !== 'undefined') {
        new Chart(ctxRevenue, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Receita (R$)',
                    data: revenueData,
                    backgroundColor: 'rgba(59, 130, 246, 0.75)',
                    hoverBackgroundColor: '#3b82f6',
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000,
                    easing: 'easeOutCubic'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#0f172a',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                return `Receita: R$ ${context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor, font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            font: { size: 11 },
                            callback: function (val) {
                                return 'R$ ' + val;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush
