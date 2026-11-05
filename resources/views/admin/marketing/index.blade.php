@extends('admin.layouts.app')

@section('title', 'Marketing & Captação de Leads')

@section('content')
<div class="container-fluid">
    {{-- Header da Pagina --}}
    <x-ui.page-header
        title="Página de Marketing & Leads"
        eyebrow="Tráfego e Conversão"
        subtitle="Monitore as visitas, origens e campanhas da página exclusiva de captação de clientes. Esta página opera isolada dos menus públicos para campanhas e anúncios."
    >
        <x-slot:actions>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('marketing.promocao') }}" target="_blank" class="btn btn-outline-info">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Ver Página (/promocao)
                </a>
                <a href="{{ route('admin.marketing.leads.export') }}" class="btn btn-outline-success">
                    <i class="bi bi-download me-1"></i> Exportar Leads (CSV)
                </a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Cards de Metricas (KPIs) - Design Elegante Original --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">Total de Visitas</span>
                        <h2 class="fw-bold mb-0 text-primary">{{ number_format($analytics['total_visits'], 0, ',', '.') }}</h2>
                        <small class="text-muted">Acessos registrados</small>
                    </div>
                    <div class="rounded-4 p-3 bg-primary bg-opacity-10 text-primary fs-3">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">Visitantes Únicos</span>
                        <h2 class="fw-bold mb-0 text-info">{{ number_format($analytics['unique_visits'], 0, ',', '.') }}</h2>
                        <small class="text-muted">Pessoas diferentes</small>
                    </div>
                    <div class="rounded-4 p-3 bg-info bg-opacity-10 text-info fs-3">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">Leads Capturados</span>
                        <h2 class="fw-bold mb-0 text-success">{{ number_format($analytics['total_leads'], 0, ',', '.') }}</h2>
                        <small class="text-success fw-bold">{{ $analytics['new_leads'] }} novos</small>
                    </div>
                    <div class="rounded-4 p-3 bg-success bg-opacity-10 text-success fs-3">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">Taxa de Conversão</span>
                        <h2 class="fw-bold mb-0 text-warning">{{ $analytics['conversion_rate'] }}%</h2>
                        <small class="text-muted">Visitas que viraram lead</small>
                    </div>
                    <div class="rounded-4 p-3 bg-warning bg-opacity-10 text-warning fs-3">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Origens do Tráfego & Gerador de Links com UTMs --}}
    <div class="row g-4 mb-4">
        {{-- Origens do Trafego (De onde vieram os clientes) --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-transparent py-3 d-flex flex-column align-items-start">
                    <h5 class="fw-bold mb-1 d-flex align-items-center gap-2 text-wrap w-100">
                        <i class="bi bi-signpost-split-fill text-primary"></i> Rotas de Origem (De onde vieram)
                    </h5>
                    <small class="text-muted d-block text-wrap w-100">Classificação inteligente do tráfego que acessou a página de marketing.</small>
                </div>
                <div class="card-body">
                    @if($analytics['origins']->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-compass fs-1 d-block mb-2 opacity-50"></i>
                            Nenhuma visita registrada ainda na página promocional.
                        </div>
                    @else
                        <div class="d-flex flex-column gap-3">
                            @foreach($analytics['origins'] as $orig)
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold d-flex align-items-center gap-2">
                                        <i class="bi {{ $orig['icon'] }}" style="color: {{ $orig['color'] }}; font-size: 1.15rem;"></i>
                                        {{ $orig['platform'] }}
                                    </span>
                                    <span class="small fw-bold">
                                        {{ $orig['count'] }} visitas ({{ $orig['percentage'] }}%)
                                    </span>
                                </div>
                                <div class="progress" style="height: 10px; border-radius: 6px;">
                                    <div class="progress-bar" role="progressbar" style="width: {{ $orig['percentage'] }}%; background-color: {{ $orig['color'] }};"></div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Gerador de Links com UTMs Prontas --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-transparent py-3 d-flex flex-column align-items-start">
                    <h5 class="fw-bold mb-1 d-flex align-items-center gap-2 text-wrap w-100">
                        <i class="bi bi-link-45deg text-info"></i> Gerador de Links Prontos (Com UTMs)
                    </h5>
                    <small class="text-muted d-block text-wrap w-100">Copie links já rastreados com 1 clique para postar em suas redes e anúncios.</small>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">
                                <i class="bi bi-instagram text-danger me-1"></i> Instagram (Bio ou Stories)
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace" id="link-instagram" value="{{ url('/promocao') }}?utm_source=instagram&utm_medium=bio" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyLink('link-instagram')">
                                    <i class="bi bi-clipboard me-1"></i> Copiar
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">
                                <i class="bi bi-whatsapp text-success me-1"></i> WhatsApp (Disparos e Mensagens)
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace" id="link-whatsapp" value="{{ url('/promocao') }}?utm_source=whatsapp&utm_medium=mensagem" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyLink('link-whatsapp')">
                                    <i class="bi bi-clipboard me-1"></i> Copiar
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">
                                <i class="bi bi-facebook text-primary me-1"></i> Meta / Facebook Ads
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace" id="link-facebook" value="{{ url('/promocao') }}?utm_source=facebook&utm_medium=cpc&utm_campaign=oferta" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyLink('link-facebook')">
                                    <i class="bi bi-clipboard me-1"></i> Copiar
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">
                                <i class="bi bi-google text-danger me-1"></i> Google Ads / Search
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace" id="link-google" value="{{ url('/promocao') }}?utm_source=google&utm_medium=cpc&utm_campaign=pesquisa" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyLink('link-google')">
                                    <i class="bi bi-clipboard me-1"></i> Copiar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabela 1: Leads Capturados no Modelo Oficial DataTables Responsive AdminLTE 3 --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-column align-items-start flex-grow-1" style="min-width: 200px;">
                <h5 class="fw-bold mb-1 d-flex align-items-center gap-2 text-wrap w-100">
                    <i class="bi bi-person-lines-fill text-primary"></i> Leads Capturados na Campanha
                </h5>
                <small class="text-muted d-block text-wrap w-100">Pessoas que preencheram o formulário de conversão na landing page promocional.</small>
            </div>
            <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0 ms-auto">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAllLeads()" title="Limpar todos os leads">
                    <i class="bi bi-trash3 me-1"></i> Limpar Leads
                </button>
            </div>
        </div>

        <div class="card-body p-2 p-md-3">
            {{-- Tabela no padrão oficial https://datatables.net/manual/extensions/responsive/ com aparência AdminLTE 3 --}}
            <table id="table-marketing-leads" class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" style="width: 100% !important;" data-export-title="Leads de Marketing">
                <thead>
                    <tr>
                        <th class="all" data-priority="1">Nome</th>
                        <th class="min-tablet">WhatsApp</th>
                        <th class="desktop">E-mail</th>
                        <th class="desktop">Interesse / Ramo</th>
                        <th class="desktop">Origem (Rota)</th>
                        <th class="desktop">Data</th>
                        <th class="min-tablet">Status</th>
                        <th class="all text-end no-export" data-priority="1" style="width: 85px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leads as $lead)
                    <tr id="lead-row-{{ $lead->id }}">
                        <td>
                            <span class="fw-bold text-truncate d-inline-block align-middle" style="max-width: 100%;" title="{{ $lead->name }}">{{ $lead->name }}</span>
                        </td>
                        <td>
                            <a href="{{ $lead->whatsapp_url }}" target="_blank" class="btn btn-sm btn-outline-success fw-bold d-inline-flex align-items-center gap-1">
                                <i class="bi bi-whatsapp"></i> {{ $lead->whatsapp }}
                            </a>
                        </td>
                        <td>
                            @if($lead->email)
                                <a href="mailto:{{ $lead->email }}" class="text-decoration-none small text-muted">
                                    <i class="bi bi-envelope me-1"></i>{{ $lead->email }}
                                </a>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $lead->interest ?: 'Promocional' }}</span>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                {{ $lead->origin_platform }}
                            </span>
                            @if($lead->utm_campaign)
                                <small class="text-muted d-block font-monospace" style="font-size: 0.72rem;">
                                    campanha: {{ $lead->utm_campaign }}
                                </small>
                            @endif
                        </td>
                        <td data-order="{{ $lead->created_at?->timestamp ?? 0 }}">
                            <small class="text-muted">{{ $lead->created_at?->format('d/m/Y H:i') }}</small>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" onchange="updateLeadStatus({{ $lead->id }}, this.value)">
                                <option value="new" {{ $lead->status === 'new' ? 'selected' : '' }}>Novo</option>
                                <option value="contacted" {{ $lead->status === 'contacted' ? 'selected' : '' }}>Em Contato</option>
                                <option value="converted" {{ $lead->status === 'converted' ? 'selected' : '' }}>Convertido</option>
                                <option value="lost" {{ $lead->status === 'lost' ? 'selected' : '' }}>Perdido</option>
                            </select>
                        </td>
                        <td class="text-end no-export">
                            <a href="{{ $lead->whatsapp_url }}" target="_blank" class="btn btn-sm btn-success me-1" title="Chamar no WhatsApp">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteLead({{ $lead->id }})" title="Excluir Lead">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tabela 2: Últimos Acessos e Rotas no Modelo Oficial DataTables Responsive AdminLTE 3 --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent py-3 d-flex flex-column align-items-start">
            <h5 class="fw-bold mb-1 d-flex align-items-center gap-2 text-wrap w-100">
                <i class="bi bi-clock-history text-info"></i> Últimos Acessos e Rotas de Entrada
            </h5>
            <small class="text-muted d-block text-wrap w-100">Registro em tempo real de cada visitante que entrou na página de marketing.</small>
        </div>

        <div class="card-body p-2 p-md-3">
            {{-- Tabela no padrão oficial https://datatables.net/manual/extensions/responsive/ com aparência AdminLTE 3 --}}
            <table id="table-marketing-visits" class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" style="width: 100% !important;" data-export-title="Acessos e Rotas de Marketing">
                <thead>
                    <tr>
                        <th class="all" data-priority="1" style="width: 110px;">Horário</th>
                        <th data-priority="2">Plataforma</th>
                        <th class="desktop">Dispositivo</th>
                        <th class="desktop">Rota / Origem (Referer)</th>
                        <th class="desktop">UTM Campanha</th>
                        <th class="min-tablet">Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentVisits as $v)
                    <tr>
                        <td data-order="{{ $v->created_at?->timestamp ?? 0 }}" class="text-muted">
                            <span class="d-none d-sm-inline">{{ $v->created_at?->format('d/m/Y H:i:s') }}</span>
                            <span class="d-inline d-sm-none">
                                <span class="fw-bold d-block text-body lh-1 mb-1">{{ $v->created_at?->format('H:i:s') }}</span>
                                <small class="text-muted d-block lh-1" style="font-size: 0.72rem;">{{ $v->created_at?->format('d/m/Y') }}</small>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 text-truncate d-inline-block align-middle" style="max-width: 100%;" title="{{ $v->origin_platform }}">
                                {{ $v->origin_platform }}
                            </span>
                        </td>
                        <td>
                            @if($v->device_type === 'Mobile')
                                <i class="bi bi-phone text-primary" title="Mobile"></i> Mobile
                            @elseif($v->device_type === 'Tablet')
                                <i class="bi bi-tablet text-info" title="Tablet"></i> Tablet
                            @else
                                <i class="bi bi-laptop text-secondary" title="Desktop"></i> Desktop
                            @endif
                        </td>
                        <td class="text-truncate" style="max-width: 320px;" title="{{ $v->referer ?: 'Acesso Direto' }}">
                            {{ $v->referer ?: 'Acesso direto / link privado' }}
                        </td>
                        <td>
                            {{ $v->utm_campaign ?: '-' }}
                        </td>
                        <td>
                            @if($v->is_unique)
                                <span class="badge bg-success-subtle text-success">Único</span>
                            @else
                                <span class="badge bg-light text-muted">Recorrente</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Configurações da Campanha Promocional - Layout Limpo Original --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent py-3 d-flex flex-column align-items-start">
            <h5 class="fw-bold mb-1 d-flex align-items-center gap-2 text-wrap w-100">
                <i class="bi bi-sliders text-warning"></i> Configurações da Campanha Promocional
            </h5>
            <small class="text-muted d-block text-wrap w-100">Ajuste os textos, títulos, descontos e o WhatsApp de atendimento da página.</small>
        </div>
        <div class="card-body">
            <form id="campaign-settings-form">
                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="form-check form-switch p-3 bg-light rounded-3 border">
                            <input class="form-check-input ms-0 me-3" type="checkbox" id="campaign-enabled" name="enabled" value="1" {{ ($campaign['enabled'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="campaign-enabled">
                                Página de Marketing Ativa
                                <small class="text-muted d-block fw-normal">Se desativada, a rota redirecionará suavemente os visitantes para a página inicial.</small>
                            </label>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-bold">Título / Headline Principal</label>
                        <input type="text" class="form-control" name="headline" value="{{ $campaign['headline'] }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Selo de Desconto / Destaque</label>
                        <input type="text" class="form-control" name="discount_badge" value="{{ $campaign['discount_badge'] }}">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-bold">Subtítulo / Descrição da Oferta</label>
                        <textarea class="form-control" name="subtitle" rows="2">{{ $campaign['subtitle'] }}</textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Texto do Botão CTA</label>
                        <input type="text" class="form-control" name="cta_text" value="{{ $campaign['cta_text'] }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">WhatsApp de Atendimento da Campanha</label>
                        <input type="text" class="form-control" name="whatsapp" value="{{ $campaign['whatsapp'] }}" placeholder="Ex: 5511999998888">
                        <small class="text-muted">Para onde o lead é direcionado após preencher o form.</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Tempo do Cronômetro de Urgência (Horas)</label>
                        <input type="number" class="form-control" name="countdown_hours" value="{{ $campaign['countdown_hours'] }}" min="1" max="72">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-bold">Aviso da Barra Superior de Urgência</label>
                        <input type="text" class="form-control" name="banner_alert" value="{{ $campaign['banner_alert'] }}">
                    </div>

                    {{-- Seleção dos até 3 Planos Exibidos na Campanha --}}
                    <div class="col-12 mt-4 pt-3 border-top">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <div>
                                <h6 class="fw-bold mb-1 text-primary d-flex align-items-center gap-2">
                                    <i class="bi bi-ui-checks-grid"></i> Planos Exibidos na Campanha (Máximo 3)
                                </h6>
                                <small class="text-muted">Selecione no máximo 3 planos para exibir na página promocional. O sistema aplicará a condição comercial configurada.</small>
                            </div>
                            <span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm" id="selected-plans-count-badge">
                                <span id="plan-count-num">0</span> / 3 selecionados
                            </span>
                        </div>

                        <div class="row g-3 mt-1">
                            @php
                                $selectedPlanIds = array_map('intval', (array)($campaign['featured_plans'] ?? []));
                            @endphp
                            @forelse($allPlans as $p)
                                @php
                                    $isSelected = in_array((int)$p->id, $selectedPlanIds, true);
                                @endphp
                                <div class="col-md-4">
                                    <div class="card h-100 border p-3 plan-selector-card {{ $isSelected ? 'border-primary bg-primary bg-opacity-10 shadow-sm' : '' }}" id="plan-card-{{ $p->id }}" style="cursor: pointer; transition: all 0.2s;" onclick="togglePlanFromCard({{ $p->id }})">
                                        <div class="d-flex align-items-start justify-content-between mb-2">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input js-featured-plan-check" type="checkbox" name="featured_plans[]" value="{{ $p->id }}" id="plan-check-{{ $p->id }}" {{ $isSelected ? 'checked' : '' }} onclick="event.stopPropagation(); handlePlanCheckChange(this);">
                                                <label class="form-check-label fw-bold text-dark ms-1" for="plan-check-{{ $p->id }}">
                                                    {{ $p->name }}
                                                </label>
                                            </div>
                                            @if($p->highlight)
                                                <span class="badge bg-danger rounded-pill" style="font-size: 0.7rem;">Destaque</span>
                                            @elseif($p->badge)
                                                <span class="badge bg-info text-dark rounded-pill" style="font-size: 0.7rem;">{{ $p->badge }}</span>
                                            @endif
                                        </div>
                                        <div class="small text-muted mb-2">
                                            {{ \Illuminate\Support\Str::limit($p->description ?? 'Plano oficial com ativação rápida.', 75) }}
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
                                            <span class="text-muted small">Valor Base:</span>
                                            <strong class="text-dark">R$ {{ number_format($p->price, 2, ',', '.') }}/mês</strong>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="alert alert-warning mb-0">Nenhum plano ativo encontrado para seleção.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Regras Comerciais da Campanha aplicadas aos Planos --}}
                    <div class="col-md-6 mt-3">
                        <label class="form-label fw-bold">Desconto da Campanha nos Planos (%)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="discount_percent" value="{{ $campaign['discount_percent'] ?? 50 }}" min="1" max="99" required>
                            <span class="input-group-text fw-bold">% OFF</span>
                        </div>
                        <small class="text-muted">Calcula os valores promocionais (De / Por) dos planos exibidos.</small>
                    </div>

                    <div class="col-md-6 mt-3">
                        <label class="form-label fw-bold">Modo de Apresentação do Desconto</label>
                        <select class="form-select" name="pricing_mode">
                            <option value="apply_discount" {{ ($campaign['pricing_mode'] ?? 'apply_discount') === 'apply_discount' ? 'selected' : '' }}>
                                Aplicar desconto sobre o valor cadastrado (Ex: R$ 97,00 - 50% = De R$ 97,00 por R$ 48,50)
                            </option>
                            <option value="nominal_is_promo" {{ ($campaign['pricing_mode'] ?? '') === 'nominal_is_promo' ? 'selected' : '' }}>
                                O valor cadastrado já é o promocional (Ex: R$ 97,00 promocional = De R$ 194,00 por R$ 97,00)
                            </option>
                        </select>
                        <small class="text-muted">Define a fórmula visual de cálculo do desconto no card do plano.</small>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="btn-save-settings">
                        <i class="bi bi-save me-1"></i> Salvar Configurações da Campanha
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Inicialização direta DataTables AdminLTE 3 Responsive
    $(document).ready(function() {
        if (typeof window.initAppDataTable === 'function') {
            window.initAppDataTable('#table-marketing-leads');
            window.initAppDataTable('#table-marketing-visits');
        }
        updatePlanCountDisplay();
    });

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // Controle de Seleção de Planos (Máximo 3)
    function updatePlanCountDisplay() {
        const checked = document.querySelectorAll('.js-featured-plan-check:checked');
        const countSpan = document.getElementById('plan-count-num');
        const badge = document.getElementById('selected-plans-count-badge');
        if (countSpan) countSpan.textContent = checked.length;
        if (badge) {
            if (checked.length > 3) {
                badge.className = 'badge bg-danger px-3 py-2 rounded-pill shadow-sm';
            } else if (checked.length === 3) {
                badge.className = 'badge bg-success px-3 py-2 rounded-pill shadow-sm';
            } else {
                badge.className = 'badge bg-primary px-3 py-2 rounded-pill shadow-sm';
            }
        }
    }

    function togglePlanFromCard(planId) {
        const checkbox = document.getElementById('plan-check-' + planId);
        if (!checkbox) return;
        checkbox.checked = !checkbox.checked;
        handlePlanCheckChange(checkbox);
    }

    function handlePlanCheckChange(checkbox) {
        const checked = document.querySelectorAll('.js-featured-plan-check:checked');
        if (checkbox.checked && checked.length > 3) {
            checkbox.checked = false;
            Swal.fire({
                icon: 'warning',
                title: 'Limite Máximo: 3 Planos',
                text: 'Você pode selecionar no máximo 3 planos para exibir nesta página de campanha.',
                confirmButtonColor: '#0d6efd',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        // Atualiza a aparência do card pai
        const card = document.getElementById('plan-card-' + checkbox.value);
        if (card) {
            if (checkbox.checked) {
                card.classList.add('border-primary', 'bg-primary', 'bg-opacity-10', 'shadow-sm');
            } else {
                card.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10', 'shadow-sm');
            }
        }

        updatePlanCountDisplay();
    }

    // Copiar Link para a Área de Transferência
    function copyLink(elementId) {
        const input = document.getElementById(elementId);
        if (!input) return;
        navigator.clipboard.writeText(input.value).then(() => {
            if (window.toast) {
                window.toast.success('Link copiado para a área de transferência!');
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Copiado!',
                    text: 'Link copiado para a área de transferência!',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    }

    // Salvar Configurações da Campanha via AJAX
    document.getElementById('campaign-settings-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-settings');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        data.enabled = document.getElementById('campaign-enabled').checked ? 1 : 0;

        // Coleta de forma explícita os planos selecionados (máximo 3)
        const selectedPlans = [];
        document.querySelectorAll('.js-featured-plan-check:checked').forEach(cb => {
            selectedPlans.push(parseInt(cb.value, 10));
        });
        data.featured_plans = selectedPlans;

        if (selectedPlans.length > 3) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            Swal.fire({
                icon: 'warning',
                title: 'Limite Excedido',
                text: 'Selecione no máximo 3 planos para a campanha.',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }

        try {
            const resp = await fetch('{{ route('admin.marketing.settings') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            const result = await resp.json();
            if (resp.ok && result.ok) {
                if (window.toast) {
                    window.toast.success(result.message || 'Configurações salvas!');
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'Salvo com sucesso!',
                        text: result.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            } else {
                Swal.fire({ icon: 'error', title: 'Falha ao salvar', text: result.message || 'Verifique os dados informados.' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Erro de rede', text: 'Não foi possível salvar as configurações.' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });

    // Atualizar Status do Lead via AJAX
    async function updateLeadStatus(id, newStatus) {
        try {
            const resp = await fetch(`{{ url('/admin/marketing/leads') }}/${id}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ status: newStatus })
            });
            const data = await resp.json();
            if (resp.ok && data.ok) {
                if (window.toast) window.toast.success(data.message);
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Erro ao atualizar status.' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Erro de rede', text: 'Falha ao atualizar status do lead.' });
        }
    }

    // Limpar Todos os Leads com SweetAlert2 (Sem alert nativo)
    async function clearAllLeads() {
        const confirm = await Swal.fire({
            title: 'Limpar todos os leads?',
            text: 'Esta ação excluirá permanentemente todos os leads cadastrados nesta campanha.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, limpar tudo!',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        });

        if (!confirm.isConfirmed) return;

        try {
            const resp = await fetch('{{ route('admin.marketing.leads.clear_all') }}', {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                }
            });
            const data = await resp.json();
            if (resp.ok && data.ok) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Leads Limpos!',
                    text: data.message || 'Todos os leads foram removidos com sucesso.',
                    timer: 2000,
                    showConfirmButton: false
                });
                location.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Falha ao limpar leads.' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Erro de rede', text: 'Não foi possível limpar os leads.' });
        }
    }

    // Excluir Lead com Confirmacao SweetAlert2
    async function deleteLead(id) {
        const confirm = await Swal.fire({
            title: 'Excluir Lead?',
            text: 'Essa ação removerá os dados deste lead permanentemente.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        });

        if (!confirm.isConfirmed) return;

        try {
            const resp = await fetch(`{{ url('/admin/marketing/leads') }}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                }
            });
            const data = await resp.json();
            if (resp.ok && data.ok) {
                document.getElementById(`lead-row-${id}`)?.remove();
                if (window.toast) window.toast.success('Lead removido!');
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Falha ao excluir.' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Erro de rede', text: 'Não foi possível excluir o lead.' });
        }
    }
</script>
@endpush
