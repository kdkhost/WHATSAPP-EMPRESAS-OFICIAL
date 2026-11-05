@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'Contratos Digitais')

@section('content')
@php
    $contractRoutePrefix = $contractRoutePrefix ?? (request()->is('painel*') ? 'tenant.contracts.' : 'admin.contracts.');
    $contractBaseUrl = $contractBaseUrl ?? url(request()->is('painel*') ? '/painel/contratos' : '/admin/contracts');
    $allContracts = $contracts->getCollection();
    $totalCount = $contracts->total();
    $sentCount = $allContracts->where('status', 'sent')->count();
    $signedCount = $allContracts->where('status', 'signed')->count();
    $pendingCount = $allContracts->whereIn('status', ['draft', 'viewed'])->count();
@endphp
<div class="container-fluid">

    {{-- ========== HEADER ========== --}}
    <x-ui.page-header
        title="Contratos Digitais"
        subtitle="Gerencie, envie e acompanhe contratos com assinatura eletrônica para seus clientes."
        eyebrow="Gestão Contratual"
        class="mb-4"
    >
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <a href="{{ route($contractRoutePrefix . 'templates') }}" class="btn btn-outline-secondary btn-sm" title="Modelos de contratos">
                    <i class="bi bi-collection me-1"></i> Modelos / Templates
                </a>
                <a href="{{ route($contractRoutePrefix . 'create') }}" class="btn btn-primary btn-sm" title="Criar novo contrato">
                    <i class="bi bi-plus-lg me-1"></i> Novo Contrato
                </a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <x-ui.stats-card
                label="Total de Contratos"
                :value="$totalCount"
                icon="bi-file-earmark-text"
                color="primary"
                meta="Registrados na base"
                class="h-100"
            />
        </div>
        <div class="col-6 col-md-3">
            <x-ui.stats-card
                label="Enviados"
                :value="$sentCount"
                icon="bi-send"
                color="info"
                meta="Aguardando assinatura"
                class="h-100"
            />
        </div>
        <div class="col-6 col-md-3">
            <x-ui.stats-card
                label="Assinados"
                :value="$signedCount"
                icon="bi-check2-circle"
                color="success"
                meta="Finalizados com sucesso"
                class="h-100"
            />
        </div>
        <div class="col-6 col-md-3">
            <x-ui.stats-card
                label="Pendentes / Rascunhos"
                :value="$pendingCount"
                icon="bi-hourglass-split"
                color="warning"
                meta="Em elaboração ou vistos"
                class="h-100"
            />
        </div>
    </div>

    {{-- ========== FILTROS ========== --}}
    <x-ui.premium-card title="Filtros e Busca" subtitle="Refine os contratos por status de assinatura" class="mb-4">
        <form method="GET" action="{{ route($contractRoutePrefix . 'index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-sm-auto">
                <select name="status" class="form-select form-select-sm" style="min-width: 200px;" aria-label="Filtrar por status">
                    <option value="">Todos os status</option>
                    @foreach(['draft'=>'Rascunho', 'sent'=>'Enviado', 'viewed'=>'Visualizado', 'signed'=>'Assinado', 'rejected'=>'Rejeitado', 'expired'=>'Expirado'] as $k => $v)
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                @if(request('status'))
                    <a href="{{ route($contractRoutePrefix . 'index') }}" class="btn btn-outline-secondary btn-sm" title="Limpar filtros">
                        <i class="bi bi-x-lg me-1"></i> Limpar
                    </a>
                @endif
            </div>
        </form>
    </x-ui.premium-card>

    {{-- ========== TABELA PADRONIZADA COM DATATABLES ========== --}}
    <x-ui.premium-card title="Lista de Contratos" :subtitle="$contracts->total() . ' contrato(s) no total.'">
        <div class="contracts-table-wrapper p-0">
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-admin-contracts" data-export-title="Contratos Digitais" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all col-title" data-priority="1">Nº / Contrato</th>
                        <th class="all col-customer" data-priority="1">Cliente</th>
                        <th class="min-tablet col-status" data-priority="2" style="width: 120px;">Status</th>
                        <th class="desktop col-date" style="width: 140px;">Enviado em</th>
                        <th class="desktop col-date" style="width: 140px;">Assinado em</th>
                        <th class="all text-end no-export col-actions" data-priority="1" style="width: 155px; min-width: 150px; max-width: 165px; white-space: nowrap;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contracts as $c)
                    @php
                        $statusConfig = [
                            'draft'    => ['secondary', 'Rascunho', 'pencil'],
                            'sent'     => ['info',      'Enviado',  'send'],
                            'viewed'   => ['primary',   'Visualizado', 'eye'],
                            'signed'   => ['success',   'Assinado', 'check-circle-fill'],
                            'rejected' => ['danger',    'Rejeitado', 'x-circle'],
                            'expired'  => ['dark',      'Expirado', 'clock-history'],
                        ];
                        [$sc, $sl, $si] = $statusConfig[$c->status] ?? ['secondary', '—', 'circle'];
                    @endphp
                    <tr id="row-{{ $c->id }}">
                        <td>
                            <div class="d-flex flex-column min-w-0" style="max-width: 100%;">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <span class="fw-semibold text-body text-truncate" title="{{ $c->title ?: ($c->template?->name ?: 'Contrato de Prestação de Serviços') }}">
                                        {{ $c->title ?: ($c->template?->name ?: 'Contrato de Prestação de Serviços') }}
                                    </span>
                                    @if($c->template?->category)
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-0 px-1" style="font-size: 10px;">
                                            <i class="bi bi-tag me-1"></i>{{ $c->template->category }}
                                        </span>
                                    @endif
                                    @if($c->invoice)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle py-0 px-1" style="font-size: 10px;" title="Fatura vinculada">
                                            <i class="bi bi-receipt me-1"></i>#{{ $c->invoice->number }}
                                        </span>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center gap-2 small text-muted">
                                    <code class="px-1 py-0 rounded bg-body-secondary text-body">{{ $c->number }}</code>
                                    @if($c->template?->name && $c->title !== $c->template->name)
                                        <span class="text-truncate" style="max-width: 250px;">&bull; Modelo: {{ $c->template->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column min-w-0">
                                <span class="fw-medium text-body text-truncate" title="{{ $c->customer?->name ?? 'Sem cliente vinculado' }}">
                                    {{ $c->customer?->name ?? '—' }}
                                </span>
                                @if($c->customer?->email)
                                    <small class="text-muted text-truncate">{{ $c->customer->email }}</small>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $sc }}-subtle text-{{ $sc }} border border-{{ $sc }}-subtle px-2 py-1">
                                <i class="bi bi-{{ $si }} me-1"></i>{{ $sl }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $c->sent_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="small text-muted">{{ $c->signed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="all text-end no-export" style="width: 155px; min-width: 150px; max-width: 165px; white-space: nowrap;">
                            <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                @if(in_array($c->status, ['draft','sent','viewed']))
                                    <a href="{{ route($contractRoutePrefix . 'edit', $c->id) }}" class="btn btn-sm btn-outline-warning p-1 px-2" title="Editar contrato">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-success p-1 px-2" data-send="{{ $c->id }}" title="Enviar para assinatura">
                                        <i class="bi bi-send"></i>
                                    </button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" data-link="{{ $c->public_url }}" title="Copiar link público">
                                    <i class="bi bi-link-45deg"></i>
                                </button>
                                <a href="{{ route($contractRoutePrefix . 'show', $c->id) }}" class="btn btn-sm btn-outline-primary p-1 px-2" title="Ver detalhes">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" data-delete="{{ $c->id }}" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($contracts->hasPages())
            <x-slot:footer>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
                    <small class="text-muted">Exibindo {{ $contracts->firstItem() ?? 0 }} a {{ $contracts->lastItem() ?? 0 }} de {{ $contracts->total() }} registros</small>
                    <div>{{ $contracts->links() }}</div>
                </div>
            </x-slot:footer>
        @endif
    </x-ui.premium-card>

</div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const contractBaseUrl = @json($contractBaseUrl);

    // Copiar link público
    document.querySelectorAll('[data-link]').forEach(btn => {
        btn.addEventListener('click', () => {
            const link = btn.dataset.link;
            if (!link) {
                if (window.toast) window.toast.error('Link indisponível.');
                return;
            }
            navigator.clipboard.writeText(link)
                .then(() => {
                    if (window.toast) window.toast.success('Link do contrato copiado com sucesso!');
                })
                .catch(() => {
                    if (window.toast) window.toast.error('Erro ao copiar link.');
                });
        });
    });

    // Enviar contrato para assinatura
    document.querySelectorAll('[data-send]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const contractId = btn.dataset.send;
            const { value: channel } = await Swal.fire({
                title: 'Enviar para assinatura',
                text: 'Selecione o canal para notificar o cliente:',
                input: 'select',
                inputOptions: {
                    both: '📱 WhatsApp + 📧 Email',
                    whatsapp: '📱 Apenas WhatsApp',
                    email: '📧 Apenas Email'
                },
                inputValue: 'both',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-send me-1"></i> Enviar agora',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754',
                showLoaderOnConfirm: true,
                preConfirm: async (ch) => {
                    try {
                        const fd = new FormData();
                        fd.append('channel', ch);
                        const r = await fetch(`${contractBaseUrl}/${contractId}/enviar`, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json'
                            },
                            body: fd,
                        });
                        const data = await r.json();
                        if (!r.ok || data.ok === false) {
                            throw new Error(data.message || 'Erro ao enviar contrato.');
                        }
                        return data;
                    } catch (err) {
                        Swal.showValidationMessage(err.message || 'Falha na comunicação.');
                    }
                },
                allowOutsideClick: () => !Swal.isLoading()
            });

            if (channel) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Contrato Enviado!',
                    html: `Canal processado com sucesso.<br><small class="text-muted">A página será atualizada.</small>`,
                    timer: 2000,
                    showConfirmButton: false
                });
                location.reload();
            }
        });
    });

    // Excluir contrato
    document.querySelectorAll('[data-delete]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const contractId = btn.dataset.delete;
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Excluir contrato?',
                text: 'Esta ação não poderá ser desfeita.',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });

            if (!result.isConfirmed) return;

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const r = await fetch(`${contractBaseUrl}/${contractId}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                });
                const data = await r.json();
                if (r.ok && (data.ok !== false)) {
                    const row = document.getElementById(`row-${contractId}`);
                    if (row) {
                        row.classList.add('table-danger');
                        setTimeout(() => row.remove(), 300);
                    }
                    if (window.toast) window.toast.success(data.message || 'Contrato excluído com sucesso!');
                } else {
                    if (window.toast) window.toast.error(data.message || 'Erro ao excluir contrato.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (err) {
                if (window.toast) window.toast.error('Erro de conexão ao excluir.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    });
})();
</script>
@endpush
