@extends('admin.layouts.app')
@section('title', 'Lista de Afiliados')

@section('content')
<div class="container-fluid">
    <x-ui.page-header
        title="Afiliados"
        subtitle="Lista operacional de afiliados cadastrados, cliques, referidos, comissões e status."
        eyebrow="Programa de afiliados"
        class="mb-4"
    />

    <x-ui.premium-card title="Lista de afiliados">
        @if(isset($list) && count($list) > 0)
            <div class="p-0 table-responsive">
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-admin-affiliates" data-export-title="Lista de Afiliados" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1">ID</th>
                            <th class="all" data-priority="1">Afiliado / Empresa</th>
                            <th class="desktop">Email</th>
                            <th class="min-tablet">Cupom / Link</th>
                            <th class="desktop text-center">Cliques</th>
                            <th class="desktop text-center">Vendas</th>
                            <th class="min-tablet text-end" data-priority="2">Total Ganho</th>
                            <th class="min-tablet text-center" data-priority="2">Status & Split</th>
                            <th class="all text-end" data-priority="1">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($list as $a)
                            <tr>
                                <td>{{ $a->id }}</td>
                                <td>
                                    <strong>{{ $a->user->name ?? 'Afiliado #' . $a->id }}</strong>
                                    @if($a->tenant)
                                        <div class="small text-muted">{{ $a->tenant->company_name }}</div>
                                    @endif
                                    @if($a->promotion_channel)
                                        <div class="smaller text-info"><i class="bi bi-megaphone me-1"></i> {{ $a->promotion_channel }}</div>
                                    @endif
                                </td>
                                <td>{{ $a->user->email ?? '-' }}</td>
                                <td>
                                    <code>{{ $a->referral_code }}</code>
                                    <div class="smaller text-muted">/a/{{ $a->slug }}</div>
                                </td>
                                <td class="text-center">{{ number_format($a->clicks ?? 0, 0, ',', '.') }}</td>
                                <td class="text-center fw-semibold text-success">{{ $a->conversions ?? 0 }}</td>
                                <td class="text-end fw-bold text-success">R$ {{ number_format($a->total_earned ?? 0, 2, ',', '.') }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $a->status_badge_class }}">
                                        {{ $a->status_label }}
                                    </span>
                                    @if($a->split_enabled)
                                        <div class="smaller text-info mt-1" title="Collector ID: {{ $a->mp_collector_id }}">
                                            <i class="bi bi-lightning-charge-fill"></i> Split MP
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if(in_array($a->status, ['pending', 'rejected']))
                                            <button type="button" class="btn btn-success" onclick="quickApproveFromList({{ $a->id }}, '{{ addslashes($a->user->name ?? 'Afiliado') }}')" title="Aprovar Afiliação">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="button" class="btn btn-warning text-dark" onclick="quickPendFromList({{ $a->id }})" title="Marcar Pendência">
                                                <i class="bi bi-hourglass-bottom"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger" onclick="quickRejectFromList({{ $a->id }})" title="Rejeitar">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.affiliates.index', ['q' => $a->referral_code]) }}" class="btn btn-outline-primary" title="Ver no Painel">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($list, 'hasPages') && $list->hasPages())
                <div class="p-3 border-top">
                    {{ $list->links() }}
                </div>
            @endif
        @else
            <x-ui.empty-state icon="bi-people" title="Nenhum afiliado cadastrado" />
        @endif
    </x-ui.premium-card>
</div>
@endsection

@push('scripts')
<script>
const listCsrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

window.quickApproveFromList = async function(id, name) {
    const { isConfirmed } = await Swal.fire({
        title: 'Aprovar Afiliado?',
        text: `Deseja aprovar ${name}? E-mail e WhatsApp serão enviados com os links ativados.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, Aprovar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754'
    });
    if (!isConfirmed) return;

    try {
        const res = await fetch(`{{ url('/admin/affiliates') }}/${id}/approve`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': listCsrf, 'Accept': 'application/json' }
        });
        const d = await res.json();
        if (d.ok) {
            Swal.fire({ icon: 'success', title: 'Aprovado!', text: d.message, timer: 2000, showConfirmButton: false }).then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: d.error || 'Falha ao aprovar.' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: e.message });
    }
};

window.quickRejectFromList = async function(id) {
    const { value: reason } = await Swal.fire({
        title: 'Rejeitar Inscrição',
        text: 'Informe o motivo detalhado para ser enviado ao cliente por WhatsApp e E-mail:',
        input: 'textarea',
        showCancelButton: true,
        confirmButtonText: 'Rejeitar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        inputValidator: (val) => (!val || val.trim().length < 5) ? 'Informe o motivo detalhado.' : null
    });
    if (!reason) return;

    try {
        const res = await fetch(`{{ url('/admin/affiliates') }}/${id}/reject`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': listCsrf, 'Accept': 'application/json' },
            body: JSON.stringify({ reason: reason.trim() })
        });
        const d = await res.json();
        if (d.ok) {
            Swal.fire({ icon: 'success', title: 'Rejeitado!', text: d.message, timer: 2000, showConfirmButton: false }).then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: d.error || 'Falha ao rejeitar.' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: e.message });
    }
};

window.quickPendFromList = async function(id) {
    const { value: instructions } = await Swal.fire({
        title: 'Marcar com Pendência',
        text: 'Informe as orientações para o cliente:',
        input: 'textarea',
        showCancelButton: true,
        confirmButtonText: 'Enviar Pendência',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ffc107',
        inputValidator: (val) => (!val || val.trim().length < 5) ? 'Informe as instruções de pendência.' : null
    });
    if (!instructions) return;

    try {
        const res = await fetch(`{{ url('/admin/affiliates') }}/${id}/pend`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': listCsrf, 'Accept': 'application/json' },
            body: JSON.stringify({ instructions: instructions.trim() })
        });
        const d = await res.json();
        if (d.ok) {
            Swal.fire({ icon: 'success', title: 'Pendência Registrada!', text: d.message, timer: 2000, showConfirmButton: false }).then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: d.error || 'Falha ao marcar pendência.' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: e.message });
    }
};
</script>
@endpush
