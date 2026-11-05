@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Integração WHMCS')

@section('content')
@php
    $stats = [
        'total' => $connections->count(),
        'active' => $connections->where('active', true)->count(),
        'signed' => $connections->where('webhook_require_signature', true)->count(),
        'last_sync' => $connections->max('last_sync_at'),
    ];

    $gatewayLabels = [
        'mercadopago' => 'Mercado Pago',
        'efipro' => 'Efí Pro',
        'sumup' => 'SumUp',
        'manual' => 'Manual',
    ];

    $statusLabels = [
        'Unpaid' => 'Em aberto',
        'Paid' => 'Pagas',
        'Cancelled' => 'Canceladas',
        'Refunded' => 'Reembolsadas',
        'Collections' => 'Cobrança',
    ];

    $serviceStatusLabels = [
        'Active' => 'Ativos',
        'Suspended' => 'Suspensos',
        'Pending' => 'Pendentes',
        'Terminated' => 'Encerrados',
        'Cancelled' => 'Cancelados',
    ];

    $connectionPayload = $connections->mapWithKeys(fn ($connection) => [
        $connection->id => [
            'id' => $connection->id,
            'name' => $connection->name,
            'api_url' => $connection->api_url,
            'identifier' => $connection->identifier,
            'tenant_id' => $connection->tenant_id,
            'active' => (bool) $connection->active,
            'sync_customers' => (bool) $connection->sync_customers,
            'sync_invoices' => (bool) $connection->sync_invoices,
            'sync_services' => (bool) $connection->sync_services,
            'auto_send_invoices' => (bool) $connection->auto_send_invoices,
            'auto_suspend_overdue_services' => (bool) $connection->auto_suspend_overdue_services,
            'auto_unsuspend_paid_services' => (bool) $connection->auto_unsuspend_paid_services,
            'suspend_after_days' => (int) $connection->suspend_after_days,
            'sync_paid_invoices' => (bool) $connection->sync_paid_invoices,
            'sync_cancelled_invoices' => (bool) $connection->sync_cancelled_invoices,
            'webhook_require_signature' => (bool) $connection->webhook_require_signature,
            'default_gateway' => $connection->default_gateway ?: 'mercadopago',
            'settings' => $connection->settings ?: [],
            'sync_statuses' => $connection->sync_statuses ?: ['Unpaid'],
            'sync_service_statuses' => $connection->sync_service_statuses ?: ['Active', 'Suspended', 'Pending'],
            'webhook_url' => $connection->webhook_url,
            'webhook_secret' => $connection->webhook_secret,
        ],
    ]);
@endphp

<x-ui.page-header
    title="Integração WHMCS"
    subtitle="Conecte o WHMCS ao sistema para importar clientes, sincronizar faturas, agendar cobranças via WhatsApp e processar webhooks com assinatura."
    eyebrow="{{ request()->is('painel*') ? 'Painel do Cliente' : 'Superadmin' }}"
    class="mb-4"
>
    <x-slot:actions>
        @if(!request()->is('painel*'))
            <button type="button" class="btn btn-outline-danger" data-clean-orphans>
                <i class="bi bi-trash me-1"></i> Limpar Dados Órfãos
            </button>
        @endif
        <button type="button" class="btn btn-primary" data-open-create>
            <i class="bi bi-plus-lg me-1"></i> Nova conexão
        </button>
    </x-slot:actions>
</x-ui.page-header>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <x-ui.stats-card label="Conexões" :value="$stats['total']" icon="bi-hdd-network" color="primary" />
    </div>
    <div class="col-md-6 col-xl-3">
        <x-ui.stats-card label="Ativas" :value="$stats['active']" icon="bi-check2-circle" color="success" />
    </div>
    <div class="col-md-6 col-xl-3">
        <x-ui.stats-card label="Webhooks assinados" :value="$stats['signed']" icon="bi-shield-check" color="info" />
    </div>
    <div class="col-md-6 col-xl-3">
        <x-ui.stats-card
            label="Serviços WHMCS"
            :value="($serviceStats['active'] ?? 0).' ativos / '.($serviceStats['suspended'] ?? 0).' suspensos'"
            icon="bi-boxes"
            color="warning"
        />
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <x-ui.premium-card
            title="Conexões WHMCS"
            subtitle="Use uma conexão global ou vincule a um tenant específico. O webhook deve ser configurado no WHMCS com o token desta tela."
        >
            <x-slot:header>
                <button type="button" class="btn btn-outline-primary btn-sm" data-open-create>
                    <i class="bi bi-plus-lg me-1"></i> Adicionar
                </button>
            </x-slot:header>

            <div class="p-0">
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-whmcs-connections" data-export-title="Conexões WHMCS" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1">Conexão</th>
                            @if(!request()->is('painel*'))
                                <th class="all">Tenant</th>
                            @endif
                            <th class="desktop">Sincronização</th>
                            <th class="desktop">Webhook</th>
                            <th class="desktop">Últimos testes</th>
                            <th class="min-tablet" data-priority="2">Status</th>
                            <th class="all text-end no-export" data-priority="1" style="width: 160px; min-width: 150px; max-width: 170px; white-space: nowrap;">Ações</th>
                        </tr>
                    </thead>
                <tbody>
                    @forelse($connections as $connection)
                        <tr id="conn-{{ $connection->id }}">
                            <td>
                                <strong class="d-block">{{ $connection->name }}</strong>
                                <span class="text-muted small text-break">{{ $connection->api_url }}</span>
                            </td>
                            @if(!request()->is('painel*'))
                                <td>
                                    @if($connection->tenant)
                                        <span class="fw-semibold d-block">{{ $connection->tenant->company_name }}</span>
                                        <span class="text-muted small">Tenant #{{ $connection->tenant_id }}</span>
                                    @else
                                        <x-ui.status-badge status="sandbox" label="Global" />
                                    @endif
                                </td>
                            @endif
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @if($connection->sync_customers)
                                        <x-ui.status-badge status="info" label="Clientes" />
                                    @endif
                                    @if($connection->sync_invoices)
                                        <x-ui.status-badge status="warning" label="Faturas" />
                                    @endif
                                    @if($connection->sync_services)
                                        <x-ui.status-badge status="info" label="Serviços" />
                                    @endif
                                    @if($connection->auto_send_invoices)
                                        <x-ui.status-badge status="success" label="Auto-envio" />
                                    @endif
                                    @if($connection->auto_suspend_overdue_services)
                                        <x-ui.status-badge status="danger" label="Suspensão" />
                                    @endif
                                    @if($connection->auto_unsuspend_paid_services)
                                        <x-ui.status-badge status="active" label="Reativação" />
                                    @endif
                                </div>
                                <span class="text-muted small d-block mt-1">
                                    Gateway: {{ $gatewayLabels[$connection->default_gateway] ?? $connection->default_gateway ?? 'Mercado Pago' }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <x-ui.status-badge
                                        :status="$connection->webhook_require_signature ? 'active' : 'inactive'"
                                        :label="$connection->webhook_require_signature ? 'HMAC ativo' : 'Token simples'"
                                    />
                                </div>
                                <button type="button" class="btn btn-link btn-sm px-0 mt-1" data-copy-webhook="{{ $connection->id }}">
                                    <i class="bi bi-clipboard me-1"></i> Copiar URL
                                </button>
                            </td>
                            <td>
                                <div class="small">
                                    <span class="d-block">
                                        Teste:
                                        <strong>{{ $connection->last_test_at?->format('d/m H:i') ?? 'Nunca' }}</strong>
                                    </span>
                                    <span class="d-block">
                                        Sync:
                                        <strong>{{ $connection->last_sync_at?->format('d/m H:i') ?? 'Nunca' }}</strong>
                                    </span>
                                </div>
                                @if($connection->last_error)
                                    <span class="text-danger small d-block text-truncate" title="{{ $connection->last_error }}">{{ $connection->last_error }}</span>
                                @endif
                            </td>
                            <td>
                                <x-ui.status-badge
                                    :status="$connection->active ? 'active' : 'inactive'"
                                    :label="$connection->active ? 'Ativa' : 'Inativa'"
                                />
                                @if($connection->last_sync_status)
                                    <span class="text-muted small d-block mt-1">Sync: {{ $connection->last_sync_status }}</span>
                                @endif
                            </td>
                            <td class="all text-end no-export" style="width: 160px; min-width: 150px; max-width: 170px; white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" data-edit="{{ $connection->id }}" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary p-1 px-2" data-test="{{ $connection->id }}" title="Testar credenciais">
                                        <i class="bi bi-plug"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-info p-1 px-2" data-sync="{{ $connection->id }}" title="Sincronizar agora">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" data-logs="{{ $connection->id }}" title="Ver logs">
                                        <i class="bi bi-list-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" data-delete="{{ $connection->id }}" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ request()->is('painel*') ? 6 : 7 }}" class="text-center text-muted py-5">
                                <i class="bi bi-hdd-network fs-1 d-block mb-2 opacity-50"></i>
                                Nenhuma conexão WHMCS configurada.
                                <div class="mt-3">
                                    <button type="button" class="btn btn-primary btn-sm" data-open-create>
                                        <i class="bi bi-plus-lg me-1"></i> Conectar WHMCS
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </x-ui.premium-card>
    </div>

    <div class="col-xl-6">
        <x-ui.premium-card title="Logs de sincronização" subtitle="Últimas chamadas feitas contra a API do WHMCS. Tokens e segredos são mascarados.">
            <div class="p-0">
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-whmcs-sync-logs" data-export-title="Logs de Sincronização WHMCS" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1">Quando</th>
                            <th class="all">Conexão</th>
                            <th class="desktop">Entidade</th>
                            <th class="min-tablet" data-priority="2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentLogs as $log)
                            <tr>
                                <td class="small">{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $log->connection_name ?? '#'.$log->connection_id }}</td>
                                <td>{{ ucfirst($log->entity) }}</td>
                                <td>
                                    <x-ui.status-badge
                                        :status="$log->status === 'ok' ? 'active' : ($log->status === 'skipped' ? 'inactive' : 'danger')"
                                        :label="$log->status"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-list-check fs-2 d-block mb-1 opacity-50"></i>
                                    Nenhum log de sincronização ainda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.premium-card>
    </div>

    <div class="col-xl-6">
        <x-ui.premium-card title="Eventos de webhook" subtitle="Eventos recebidos do WHMCS, com proteção opcional por HMAC e bloqueio de replay.">
            <div class="p-0">
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-whmcs-webhook-events" data-export-title="Eventos de Webhook WHMCS" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1">Quando</th>
                            <th class="all">Evento</th>
                            <th class="desktop">Fatura</th>
                            <th class="min-tablet" data-priority="2">Assinatura</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentWebhookEvents as $event)
                            <tr>
                                <td class="small">{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $event->event ?: 'unknown' }}</td>
                                <td>{{ $event->invoice_id ?: '-' }}</td>
                                <td>
                                    @if($event->signature_valid === null)
                                        <x-ui.status-badge status="inactive" label="Não exigida" />
                                    @else
                                        <x-ui.status-badge
                                            :status="$event->signature_valid ? 'active' : 'danger'"
                                            :label="$event->signature_valid ? 'Válida' : 'Inválida'"
                                        />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-broadcast fs-2 d-block mb-1 opacity-50"></i>
                                    Nenhum webhook recebido ainda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.premium-card>
    </div>
</div>

<div class="modal fade" id="modalWhmcsConnection" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="whmcs-form">
                @csrf
                <input type="hidden" name="_method" value="POST">
                <input type="hidden" name="connection_id">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Nova conexão WHMCS</h5>
                        <p class="text-muted small mb-0">Informe as credenciais API e escolha como a sincronização deve operar.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-0">
                    <ul class="nav nav-tabs nav-tabs-custom px-3 pt-3 border-bottom-0" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-medium" id="whmcs-tab-geral" data-bs-toggle="tab" data-bs-target="#whmcs-pane-geral" type="button" role="tab"><i class="bi bi-gear me-1"></i> Geral</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-medium" id="whmcs-tab-sync" data-bs-toggle="tab" data-bs-target="#whmcs-pane-sync" type="button" role="tab"><i class="bi bi-arrow-repeat me-1"></i> Sincronização</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-medium" id="whmcs-tab-auto" data-bs-toggle="tab" data-bs-target="#whmcs-pane-auto" type="button" role="tab"><i class="bi bi-lightning-charge me-1"></i> Automação & Webhook</button>
                        </li>
                    </ul>

                    <div class="tab-content p-3 border-top bg-body-tertiary bg-opacity-50">
                        <!-- Aba Geral -->
                        <div class="tab-pane fade show active" id="whmcs-pane-geral" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nome da conexão</label>
                                    <input name="name" class="form-control" maxlength="80" required placeholder="WHMCS Produção">
                                </div>
                                @if(request()->is('painel*'))
                                    <input type="hidden" name="tenant_id" value="">
                                @else
                                    <div class="col-md-6">
                                        <label class="form-label">Tenant</label>
                                        <select name="tenant_id" class="form-select">
                                            <option value="">Global / todos</option>
                                            @foreach($tenants as $tenant)
                                                <option value="{{ $tenant->id }}">{{ $tenant->company_name }} (#{{ $tenant->id }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md-8">
                                    <label class="form-label">URL da API</label>
                                    <input name="api_url" type="url" class="form-control" required placeholder="https://seudominio.com/whmcs/includes/api.php">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Gateway padrão</label>
                                    <select name="default_gateway" class="form-select">
                                        @foreach($gatewayLabels as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">API Identifier</label>
                                    <input name="identifier" class="form-control" required autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">API Secret</label>
                                    <input name="secret" type="password" class="form-control" autocomplete="new-password" data-secret-input required>
                                    <div class="form-text">Na edição, deixe vazio para manter o segredo já salvo.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Aba Sincronização -->
                        <div class="tab-pane fade" id="whmcs-pane-sync" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="border rounded-3 p-3 bg-body">
                                        <h6 class="mb-3"><i class="bi bi-arrow-repeat me-1"></i> Sincronização</h6>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="active" value="1" id="whmcs-active" checked>
                                            <label class="form-check-label" for="whmcs-active">Conexão ativa</label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="sync_customers" value="1" id="whmcs-sync-customers" checked>
                                            <label class="form-check-label" for="whmcs-sync-customers">Importar clientes</label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="sync_invoices" value="1" id="whmcs-sync-invoices" checked>
                                            <label class="form-check-label" for="whmcs-sync-invoices">Importar faturas</label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="sync_services" value="1" id="whmcs-sync-services" checked>
                                            <label class="form-check-label" for="whmcs-sync-services">Importar serviços/produtos</label>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" name="auto_send_invoices" value="1" id="whmcs-auto-send" checked>
                                            <label class="form-check-label" for="whmcs-auto-send">Agendar cobranças automáticas</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="border rounded-3 p-3 bg-body">
                                        <h6 class="mb-3"><i class="bi bi-receipt me-1"></i> Status importados</h6>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="small text-muted text-uppercase mb-2">Faturas</div>
                                                @foreach($statusLabels as $value => $label)
                                                    <div class="form-check mb-1">
                                                        <input class="form-check-input" type="checkbox" name="sync_statuses[]" value="{{ $value }}" id="status-{{ $value }}" @checked(in_array($value, ['Unpaid', 'Paid'], true))>
                                                        <label class="form-check-label small" for="status-{{ $value }}">{{ $label }}</label>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="col-6">
                                                <div class="small text-muted text-uppercase mb-2">Serviços</div>
                                                @foreach($serviceStatusLabels as $value => $label)
                                                    <div class="form-check mb-1">
                                                        <input class="form-check-input" type="checkbox" name="sync_service_statuses[]" value="{{ $value }}" id="service-status-{{ $value }}" @checked(in_array($value, ['Active', 'Suspended', 'Pending'], true))>
                                                        <label class="form-check-label small" for="service-status-{{ $value }}">{{ $label }}</label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <hr class="my-3">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="sync_paid_invoices" value="1" id="whmcs-paid" checked>
                                            <label class="form-check-label small" for="whmcs-paid">Manter pagas sincronizadas</label>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" name="sync_cancelled_invoices" value="1" id="whmcs-cancelled" checked>
                                            <label class="form-check-label small" for="whmcs-cancelled">Manter cancel/reembolsadas</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="border rounded-3 p-3 bg-body">
                                        <h6 class="mb-3"><i class="bi bi-cash-coin me-1"></i> Pagamento espelhado do WHMCS</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" name="settings[generate_local_gateway_charge]" value="1" id="whmcs-generate-charge" checked>
                                                    <label class="form-check-label small" for="whmcs-generate-charge">Gerar cobrança local (Gateway)</label>
                                                </div>
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" name="settings[respect_payment_method]" value="1" id="whmcs-respect-method" checked>
                                                    <label class="form-check-label small" for="whmcs-respect-method">Respeitar método do WHMCS</label>
                                                </div>
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" name="settings[attach_boleto_pdf]" value="1" id="whmcs-attach-boleto" checked>
                                                    <label class="form-check-label small" for="whmcs-attach-boleto">Anexar boleto PDF (se houver)</label>
                                                </div>
                                                <p class="text-muted small mt-2 mb-0" style="font-size: 0.75rem;">
                                                    Pix segue com QR Code. Boleto usa anexo PDF quando fornecido.
                                                </p>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="row g-3">
                                                    <div class="col-md-3">
                                                        <label class="form-label small">Carência</label>
                                                        <input name="settings[late_fee_grace_days]" type="number" min="0" max="365" step="1" class="form-control form-control-sm" value="0">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small">Multa fixa (R$)</label>
                                                        <input name="settings[late_fee_fixed_amount]" type="number" min="0" step="0.01" class="form-control form-control-sm" value="0.00">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small">Multa (%)</label>
                                                        <input name="settings[late_fee_fixed_percent]" type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" value="0.00">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small">Juro dia (R$)</label>
                                                        <input name="settings[late_fee_daily_amount]" type="number" min="0" step="0.01" class="form-control form-control-sm" value="0.00">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small">Juro dia (%)</label>
                                                        <input name="settings[late_fee_daily_percent]" type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" value="0.00">
                                                    </div>
                                                    <div class="col-md-9">
                                                        <p class="text-muted mb-0" style="font-size: 0.75rem;">
                                                            O valor local da fatura é recalculado na sincronização com base nestas taxas de juros e multas.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Aba Automação & Webhook -->
                        <div class="tab-pane fade" id="whmcs-pane-auto" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="border rounded-3 p-3 bg-body h-100">
                                        <h6 class="mb-3"><i class="bi bi-power me-1"></i> Automação de serviços</h6>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="auto_suspend_overdue_services" value="1" id="whmcs-auto-suspend">
                                            <label class="form-check-label" for="whmcs-auto-suspend">Suspender serviço vencido</label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="auto_unsuspend_paid_services" value="1" id="whmcs-auto-unsuspend" checked>
                                            <label class="form-check-label" for="whmcs-auto-unsuspend">Reativar automaticamente ao pagar</label>
                                        </div>
                                        <label class="form-label">Dias de tolerância antes da suspensão</label>
                                        <input name="suspend_after_days" type="number" min="0" max="365" step="1" class="form-control" value="0">
                                        <p class="text-muted small mt-2 mb-0">
                                            A suspensão roda no cron interno em background.
                                        </p>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="border rounded-3 p-3 bg-body h-100">
                                        <h6 class="mb-3"><i class="bi bi-shield-check me-1"></i> Webhook</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="webhook_require_signature" value="1" id="whmcs-signature" checked>
                                            <label class="form-check-label" for="whmcs-signature">Exigir assinatura HMAC</label>
                                            <div class="form-text">Proteção para bloquear webhooks sem assinatura.</div>
                                        </div>
                                        <label class="form-label">URL do webhook</label>
                                        <div class="input-group">
                                            <input class="form-control" data-webhook-preview readonly value="Salve a conexão para gerar a URL">
                                            <button type="button" class="btn btn-outline-secondary" data-copy-current-webhook>
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                        <label class="form-label mt-3">Segredo HMAC</label>
                                        <div class="input-group">
                                            <input class="form-control" data-webhook-secret-preview readonly value="Salve a conexão para gerar o segredo">
                                            <button type="button" class="btn btn-outline-secondary" data-copy-current-secret>
                                                <i class="bi bi-shield-lock"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Salvar conexão
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const baseUrl = window.location.pathname.includes('/painel/') ? @json(url('/painel/whmcs')) : @json(url('/admin/whmcs'));
    const connections = @json($connectionPayload);
    const modalEl = document.getElementById('modalWhmcsConnection');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('whmcs-form');
    const secretInput = form.querySelector('[data-secret-input]');
    const webhookPreview = form.querySelector('[data-webhook-preview]');
    const webhookSecretPreview = form.querySelector('[data-webhook-secret-preview]');
    const booleanFields = [
        'active',
        'sync_customers',
        'sync_invoices',
        'sync_services',
        'auto_send_invoices',
        'auto_suspend_overdue_services',
        'auto_unsuspend_paid_services',
        'sync_paid_invoices',
        'sync_cancelled_invoices',
        'webhook_require_signature',
        'settings[generate_local_gateway_charge]',
        'settings[respect_payment_method]',
        'settings[attach_boleto_pdf]',
    ];

    const notify = (type, message) => {
        if (window.toast?.[type]) {
            window.toast[type](message);
            return;
        }
        if (window.Toastify) {
            Toastify({ text: message, duration: 3500, gravity: 'top', position: 'right' }).showToast();
            return;
        }
        if (window.Swal) {
            Swal.fire({
                icon: type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'),
                text: message,
                timer: 3500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
            });
            return;
        }
        console.log(message);
    };

    const setButtonLoading = (button, loadingText = 'Processando...') => {
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${loadingText}`;

        return () => {
            button.disabled = false;
            button.innerHTML = original;
        };
    };

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                ...(options.headers || {}),
            },
            ...options,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.ok === false) {
            throw new Error(data.error || data.message || 'Operação não concluída.');
        }

        return data;
    };

    const resetForm = () => {
        form.reset();
        form.querySelector('[name="_method"]').value = 'POST';
        form.querySelector('[name="connection_id"]').value = '';
        form.querySelector('.modal-title').textContent = 'Nova conexão WHMCS';
        
        // Ativa a primeira aba ao abrir (reset)
        const firstTab = new bootstrap.Tab(document.querySelector('#whmcs-tab-geral'));
        firstTab.show();

        secretInput.required = true;
        secretInput.placeholder = '';
        webhookPreview.value = 'Salve a conexão para gerar a URL';
        webhookSecretPreview.value = 'Salve a conexão para gerar o segredo';
        booleanFields.forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) input.checked = true;
        });
        form.querySelector('[name="webhook_require_signature"]').checked = true;
        form.querySelector('[name="auto_suspend_overdue_services"]').checked = false;
        form.querySelector('[name="auto_unsuspend_paid_services"]').checked = true;
        form.querySelector('[name="suspend_after_days"]').value = '0';
        form.querySelector('[name="settings[late_fee_grace_days]"]').value = '0';
        form.querySelector('[name="settings[late_fee_fixed_amount]"]').value = '0.00';
        form.querySelector('[name="settings[late_fee_fixed_percent]"]').value = '0.00';
        form.querySelector('[name="settings[late_fee_daily_amount]"]').value = '0.00';
        form.querySelector('[name="settings[late_fee_daily_percent]"]').value = '0.00';
        form.querySelectorAll('[name="sync_statuses[]"]').forEach((input) => {
            input.checked = ['Unpaid', 'Paid'].includes(input.value);
        });
        form.querySelectorAll('[name="sync_service_statuses[]"]').forEach((input) => {
            input.checked = ['Active', 'Suspended', 'Pending'].includes(input.value);
        });
        form.querySelector('[name="default_gateway"]').value = 'mercadopago';
    };

    const fillForm = (connection) => {
        resetForm();
        form.querySelector('[name="_method"]').value = 'PUT';
        form.querySelector('[name="connection_id"]').value = connection.id;
        form.querySelector('.modal-title').textContent = `Editar conexão #${connection.id}`;

        // Ativa a primeira aba ao abrir para edição
        const firstTab = new bootstrap.Tab(document.querySelector('#whmcs-tab-geral'));
        firstTab.show();

        form.querySelector('[name="name"]').value = connection.name || '';
        form.querySelector('[name="api_url"]').value = connection.api_url || '';
        form.querySelector('[name="identifier"]').value = connection.identifier || '';
        form.querySelector('[name="tenant_id"]').value = connection.tenant_id || '';
        form.querySelector('[name="default_gateway"]').value = connection.default_gateway || 'mercadopago';
        form.querySelector('[name="suspend_after_days"]').value = connection.suspend_after_days ?? 0;
        form.querySelector('[name="settings[late_fee_grace_days]"]').value = connection.settings?.late_fee_grace_days ?? 0;
        form.querySelector('[name="settings[late_fee_fixed_amount]"]').value = connection.settings?.late_fee_fixed_amount ?? '0.00';
        form.querySelector('[name="settings[late_fee_fixed_percent]"]').value = connection.settings?.late_fee_fixed_percent ?? '0.00';
        form.querySelector('[name="settings[late_fee_daily_amount]"]').value = connection.settings?.late_fee_daily_amount ?? '0.00';
        form.querySelector('[name="settings[late_fee_daily_percent]"]').value = connection.settings?.late_fee_daily_percent ?? '0.00';
        secretInput.required = false;
        secretInput.placeholder = 'Deixe vazio para manter o segredo atual';
        webhookPreview.value = connection.webhook_url || '';
        webhookSecretPreview.value = connection.webhook_secret || '';

        booleanFields.forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (!input) return;
            if (field === 'webhook_require_signature') {
                input.checked = true;
                return;
            }

            if (field.startsWith('settings[')) {
                const key = field.replace('settings[', '').replace(']', '');
                input.checked = Boolean(connection.settings?.[key]);
                return;
            }

            input.checked = Boolean(connection[field]);
        });

        const statuses = connection.sync_statuses || ['Unpaid'];
        form.querySelectorAll('[name="sync_statuses[]"]').forEach((input) => {
            input.checked = statuses.includes(input.value);
        });

        const serviceStatuses = connection.sync_service_statuses || ['Active', 'Suspended', 'Pending'];
        form.querySelectorAll('[name="sync_service_statuses[]"]').forEach((input) => {
            input.checked = serviceStatuses.includes(input.value);
        });
    };

    document.querySelectorAll('[data-open-create]').forEach((button) => {
        button.addEventListener('click', () => {
            resetForm();
            modal.show();
        });
    });

    document.querySelectorAll('[data-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            fillForm(connections[button.dataset.edit]);
            modal.show();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = form.querySelector('button[type="submit"]');
        const restore = setButtonLoading(submit, 'Salvando...');
        const formData = new FormData(form);
        const connectionId = formData.get('connection_id');

        booleanFields.forEach((field) => {
            if (!formData.has(field)) formData.set(field, '0');
        });

        if (!formData.has('sync_statuses[]')) {
            formData.append('sync_statuses[]', 'Unpaid');
        }

        if (!formData.has('sync_service_statuses[]')) {
            formData.append('sync_service_statuses[]', 'Active');
        }

        try {
            const url = connectionId ? `${baseUrl}/${connectionId}` : baseUrl;
            const data = await requestJson(url, { method: 'POST', body: formData });
            notify('success', data.message || 'Conexão salva com sucesso.');
            setTimeout(() => window.location.reload(), 700);
        } catch (error) {
            notify('error', error.message);
        } finally {
            restore();
        }
    });

    document.querySelectorAll('[data-test]').forEach((button) => {
        button.addEventListener('click', async () => {
            const restore = setButtonLoading(button, '');
            try {
                const data = await requestJson(`${baseUrl}/${button.dataset.test}/test`, { method: 'POST' });
                notify('success', data.message || 'Credenciais validadas com sucesso.');
            } catch (error) {
                notify('error', error.message);
            } finally {
                restore();
            }
        });
    });

    const statCard = (title, icon, color, dataObj) => {
        if (!dataObj) return '';
        return `
        <div class="col-md-6">
            <div class="card border border-light-subtle shadow-sm text-start h-100">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-${color} mb-3"><i class="bi ${icon} me-1"></i> ${title}</h6>
                    <div class="d-flex justify-content-between small border-bottom pb-2 mb-2">
                        <span class="text-muted">Importados/Novos:</span> 
                        <span class="fw-bold text-success">${dataObj.imported || 0}</span>
                    </div>
                    <div class="d-flex justify-content-between small border-bottom pb-2 mb-2">
                        <span class="text-muted">Atualizados:</span> 
                        <span class="fw-bold text-primary">${dataObj.updated || 0}</span>
                    </div>
                    <div class="d-flex justify-content-between small border-bottom pb-2 mb-2">
                        <span class="text-muted">Ignorados:</span> 
                        <span class="fw-bold text-secondary">${dataObj.skipped || 0}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Erros:</span> 
                        <span class="fw-bold text-danger">${dataObj.errors || 0}</span>
                    </div>
                </div>
            </div>
        </div>`;
    };

    const renderSyncResultModal = (s) => {
        const html = `
            <div class="row g-3 mb-3">
                ${statCard('Clientes', 'bi-people', 'primary', s.customers)}
                ${statCard('Faturas', 'bi-receipt', 'success', s.invoices)}
                ${statCard('Serviços', 'bi-box', 'info', s.services)}
                ${s.service_automation ? `
                <div class="col-md-6">
                    <div class="card border border-light-subtle shadow-sm text-start h-100">
                        <div class="card-body p-3">
                            <h6 class="fw-bold text-warning mb-3"><i class="bi bi-robot me-1"></i> Automação</h6>
                            <div class="d-flex justify-content-between small border-bottom pb-2 mb-2">
                                <span class="text-muted">Suspensos:</span> 
                                <span class="fw-bold text-danger">${s.service_automation.suspended || 0}</span>
                            </div>
                            <div class="d-flex justify-content-between small border-bottom pb-2 mb-2">
                                <span class="text-muted">Reativados:</span> 
                                <span class="fw-bold text-success">${s.service_automation.unsuspended || 0}</span>
                            </div>
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted">Erros:</span> 
                                <span class="fw-bold text-danger">${s.service_automation.errors || 0}</span>
                            </div>
                        </div>
                    </div>
                </div>` : ''}
            </div>
            ${s.billing_dispatches_scheduled !== undefined ? `
            <div class="alert alert-success border-success-subtle bg-success-subtle text-success-emphasis text-start mb-0">
                <i class="bi bi-send-check-fill me-2"></i> <strong>${s.billing_dispatches_scheduled}</strong> envios de cobrança agendados no sistema.
            </div>` : ''}
        `;

        Swal.fire({
            icon: 'success',
            title: 'Sincronização concluída!',
            html: html,
            width: 720,
            confirmButtonText: 'OK',
        }).then(() => window.location.reload());
    };

    document.querySelectorAll('[data-sync]').forEach((button) => {
        button.addEventListener('click', async () => {
            const restore = setButtonLoading(button, '');
            const connectionId = button.dataset.sync;

            Swal.fire({
                title: 'Sincronizando WHMCS...',
                html: 'Importando clientes, faturas e serviços.<br><br>Por favor, aguarde.',
                allowOutsideClick: false,
                allowEscapeKey: true,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const res = await requestJson(`${baseUrl}/${connectionId}/sync`, { method: 'POST' });

                // Se o backend já retornou as estatísticas diretamente
                if (res.stats) {
                    renderSyncResultModal(res.stats);
                    restore();
                    return;
                }

                // Fallback com limite de polling para evitar qualquer loop infinito
                let attempts = 0;
                const maxAttempts = 30; // 30 * 2s = 60s max
                const pollTimer = setInterval(async () => {
                    attempts++;
                    if (attempts > maxAttempts) {
                        clearInterval(pollTimer);
                        Swal.close();
                        notify('error', 'Tempo limite de sincronização excedido. Verifique os logs.');
                        restore();
                        return;
                    }

                    try {
                        const statusData = await requestJson(`${baseUrl}/${connectionId}/sync/status`, { method: 'GET' });
                        if (statusData.status === 'error') {
                            clearInterval(pollTimer);
                            Swal.close();
                            notify('error', statusData.error || 'Erro desconhecido');
                            restore();
                        } else if (statusData.status === 'completed') {
                            clearInterval(pollTimer);
                            renderSyncResultModal(statusData.stats || {});
                            restore();
                        }
                    } catch (err) {
                        clearInterval(pollTimer);
                        Swal.close();
                        notify('error', 'Erro ao verificar status da sincronização.');
                        restore();
                    }
                }, 2000);

            } catch (error) {
                Swal.close();
                notify('error', error.message || 'Erro ao sincronizar.');
                restore();
            }
        });
    });

    const btnOrphans = document.querySelector('[data-clean-orphans]');
    if (btnOrphans) {
        btnOrphans.addEventListener('click', async () => {
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Limpar dados órfãos?',
                text: 'Isso vai apagar todos os clientes, faturas e serviços importados do WHMCS que não pertencem a nenhuma conexão ativa no momento.',
                showCancelButton: true,
                confirmButtonText: 'Sim, limpar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: 'var(--bs-danger)',
            });

            if (!result.isConfirmed) return;

            const restore = setButtonLoading(btnOrphans, '');
            try {
                const data = await requestJson(`${baseUrl}/clean-orphans`, { method: 'DELETE' });
                notify('success', data.message || 'Dados órfãos removidos com sucesso.');
                setTimeout(() => window.location.reload(), 1500);
            } catch (error) {
                notify('error', error.message);
            } finally {
                restore();
            }
        });
    }

    document.querySelectorAll('[data-delete]').forEach((button) => {
        button.addEventListener('click', async () => {
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Excluir conexão e limpar dados?',
                text: 'A conexão será removida e TODOS os clientes, faturas e serviços importados dela também serão apagados permanentemente.',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir tudo',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: 'var(--bs-danger)',
            });

            if (!result.isConfirmed) return;

            const restore = setButtonLoading(button, '');
            try {
                const data = await requestJson(`${baseUrl}/${button.dataset.delete}`, { method: 'DELETE' });
                notify('success', data.message || 'Conexão e dados excluídos com sucesso.');
                setTimeout(() => window.location.reload(), 1500);
            } catch (error) {
                notify('error', error.message);
            } finally {
                restore();
            }
        });
    });

    document.querySelectorAll('[data-copy-webhook]').forEach((button) => {
        button.addEventListener('click', async () => {
            const connection = connections[button.dataset.copyWebhook];
            if (!connection?.webhook_url) return;
            await navigator.clipboard.writeText(connection.webhook_url);
            notify('success', 'URL do webhook copiada.');
        });
    });

    form.querySelector('[data-copy-current-webhook]').addEventListener('click', async () => {
        if (!webhookPreview.value || webhookPreview.value.startsWith('Salve')) return;
        await navigator.clipboard.writeText(webhookPreview.value);
        notify('success', 'URL do webhook copiada.');
    });

    form.querySelector('[data-copy-current-secret]').addEventListener('click', async () => {
        if (!webhookSecretPreview.value || webhookSecretPreview.value.startsWith('Salve')) return;
        await navigator.clipboard.writeText(webhookSecretPreview.value);
        notify('success', 'Segredo HMAC copiado.');
    });

    document.querySelectorAll('[data-logs]').forEach((button) => {
        button.addEventListener('click', async () => {
            const restore = setButtonLoading(button, '');
            try {
                const data = await requestJson(`${baseUrl}/${button.dataset.logs}/logs`);
                const syncRows = (data.sync_logs || []).map((log) => `
                    <tr>
                        <td>${log.created_at || '-'}</td>
                        <td>${log.entity || '-'}</td>
                        <td>${log.status || '-'}</td>
                        <td>${log.message || ''}</td>
                    </tr>
                `).join('');
                const webhookRows = (data.webhook_events || []).map((event) => `
                    <tr>
                        <td>${event.created_at || '-'}</td>
                        <td>${event.event || '-'}</td>
                        <td>${event.invoice_id || '-'}</td>
                        <td>${event.error || event.status || ''}</td>
                    </tr>
                `).join('');

                Swal.fire({
                    title: 'Logs WHMCS',
                    width: 900,
                    html: `
                        <div class="text-start">
                            <h6>Sincronização</h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm">
                                    <tbody>${syncRows || '<tr><td class="text-muted">Sem logs.</td></tr>'}</tbody>
                                </table>
                            </div>
                            <h6>Webhooks</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>${webhookRows || '<tr><td class="text-muted">Sem webhooks.</td></tr>'}</tbody>
                                </table>
                            </div>
                        </div>
                    `,
                });
            } catch (error) {
                notify('error', error.message);
            } finally {
                restore();
            }
        });
    });
})();
</script>
@endpush
